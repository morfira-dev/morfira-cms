<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Domain\EntryId;
use Cms\Content\Domain\EntryRepository;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageTitle;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Domain\ContentTypeNotFound;

/**
 * @phpstan-type Author array{login:string,name:string,externalId?:string}
 * @phpstan-type AuthorReference array{id:string,typeId:string,name:string}
 * @phpstan-type AuthorOutcome array{state:string,profileId?:string}
 */
final readonly class ImportWordPressAuthors
{
    private const string TYPE = 'wordpress-authors';

    public function __construct(
        private WordPressImportLedger $ledger,
        private EntryRepository $entries,
        private GetContentTypeDefinition $definitions,
        private CreateContentType $createType,
        private CreateEntry $createEntry,
    ) {
    }

    /**
     * @param list<Author> $authors
     * @param list<string> $referencedLogins
     * @return array{profiles:array<string,AuthorReference>,outcomes:list<AuthorOutcome>}
     */
    public function __invoke(string $source, array $authors, array $referencedLogins, bool $apply): array
    {
        if ($authors === [] && $referencedLogins === []) {
            return ['profiles' => [], 'outcomes' => []];
        }

        $schema = null;
        try {
            $schema = $this->definitions->byKey(self::TYPE);
        } catch (ContentTypeNotFound) {
            // The author schema is created on the first successful import.
        }
        $typeId = $schema?->id;
        $compatible = $schema === null || !in_array(true, array_column($schema->fields, 'required'), true);
        $occupied = [];
        if ($typeId !== null) {
            foreach ($this->entries->all($typeId) as $entry) {
                $occupied[$entry->slug->value] = true;
            }
        }
        $profiles = [];
        $outcomes = [];
        $names = [];
        $conflicts = [];
        $identityKeys = [];
        $identityOwners = [];
        $ambiguousIdentities = [];
        foreach ($authors as $author) {
            $key = self::key($author['login']);
            if (isset($names[$key]) && $names[$key] !== $author['name']) {
                $conflicts[$key] = true;
            }
            $names[$key] ??= $author['name'];
            $identity = isset($author['externalId']) ? self::identityKey($author['externalId']) : null;
            if (array_key_exists($key, $identityKeys) && $identityKeys[$key] !== $identity) {
                $identityKeys[$key] = null;
            } else {
                $identityKeys[$key] = $identity;
            }
            if ($identity !== null) {
                if (isset($identityOwners[$identity]) && $identityOwners[$identity] !== $key) {
                    $ambiguousIdentities[$identity] = true;
                }
                $identityOwners[$identity] ??= $key;
            }
        }
        foreach ($referencedLogins as $login) {
            $names[self::key($login)] ??= null;
        }

        foreach ($names as $key => $name) {
            if (isset($conflicts[$key])) {
                $outcomes[] = ['state' => 'conflict'];
                continue;
            }
            $identity = $identityKeys[$key] ?? null;
            if ($identity !== null && isset($ambiguousIdentities[$identity])) {
                $identity = null;
            }
            $legacyEntryId = $this->ledger->authorEntryId($source, $key);
            $identityEntryId = $identity === null ? null : $this->ledger->authorEntryId($source, $identity);
            $entryId = $identityEntryId ?? $legacyEntryId;
            if ($entryId !== null) {
                $entry = $this->entries->get(EntryId::fromString($entryId));
                if ($entry === null || $entry->contentTypeId !== $typeId) {
                    $outcomes[] = ['state' => 'unavailable'];
                    continue;
                }
                if ($apply && $legacyEntryId === null) {
                    $this->ledger->recordAuthor($source, $key, $entryId);
                }
                if ($apply && $identity !== null && $identityEntryId === null) {
                    $this->ledger->recordAuthor($source, $identity, $entryId);
                }
                $profiles[$key] = ['id' => $entryId, 'typeId' => $entry->contentTypeId, 'name' => $entry->title];
                $outcomes[] = ['state' => 'already_imported', 'profileId' => $entryId];
                continue;
            }
            if ($name === null) {
                $outcomes[] = ['state' => 'missing_declaration'];
                continue;
            }
            $slug = 'wp-author-'.substr(hash('sha256', $source.':'.($identity ?? $key)), 0, 40);
            if (!$compatible || isset($occupied[$slug])) {
                $outcomes[] = ['state' => 'conflict'];
                continue;
            }
            try {
                $name = (new PageTitle($name))->value;
            } catch (\InvalidArgumentException) {
                $outcomes[] = ['state' => 'invalid'];
                continue;
            }
            if ($apply) {
                $typeId ??= ($this->createType)('WordPress authors', self::TYPE, [])->id;
                $entryId = ($this->createEntry)($typeId, $name, $slug, [])->id;
                $this->ledger->recordAuthor($source, $key, $entryId);
                if ($identity !== null) {
                    $this->ledger->recordAuthor($source, $identity, $entryId);
                }
                $outcomes[] = ['state' => 'imported', 'profileId' => $entryId];
            } else {
                $typeId ??= PageId::new()->value;
                $entryId = PageId::new()->value;
                $outcomes[] = ['state' => 'planned'];
            }
            $occupied[$slug] = true;
            $profiles[$key] = ['id' => $entryId, 'typeId' => $typeId, 'name' => $name];
        }

        return ['profiles' => $profiles, 'outcomes' => $outcomes];
    }

    public static function key(string $login): string
    {
        return hash('sha256', mb_strtolower($login, 'UTF-8'));
    }

    private static function identityKey(string $externalId): string
    {
        return hash('sha256', "wordpress-author-id\0".$externalId);
    }
}
