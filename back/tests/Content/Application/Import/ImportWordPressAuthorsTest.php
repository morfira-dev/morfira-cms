<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\CreateEntry;
use Cms\Content\Application\Import\ImportWordPressAuthors;
use Cms\Content\Application\Import\WordPressImportLedger;
use Cms\Content\Application\SanitizeEntryValues;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ImportWordPressAuthorsTest extends TestCase
{
    #[DataProvider('formatOrders')]
    public function testNumericIdentityReusesAuthorAcrossFormats(string $firstLogin, string $secondLogin): void
    {
        $claims = [];
        [$import, $entries] = $this->fixture($claims);
        $first = [['login' => $firstLogin, 'name' => 'Original editor', 'externalId' => '23']];
        $second = [['login' => $secondLogin, 'name' => 'Renamed editor', 'externalId' => '23']];

        $created = $import('legacy', $first, [$firstLogin], true)['profiles'][ImportWordPressAuthors::key($firstLogin)];
        $before = $claims;
        $preview = $import('legacy', $second, [$secondLogin], false);
        self::assertSame($before, $claims);
        self::assertSame($created, $preview['profiles'][ImportWordPressAuthors::key($secondLogin)]);
        self::assertSame([['state' => 'already_imported', 'profileId' => $created['id']]], $preview['outcomes']);

        $replay = $import('legacy', $second, [$secondLogin], true);
        self::assertSame($preview, $replay);
        self::assertCount(1, $entries->all());
        self::assertSame($created['id'], $claims['legacy'][ImportWordPressAuthors::key($secondLogin)]);
        self::assertSame('Original editor', $replay['profiles'][ImportWordPressAuthors::key($secondLogin)]['name']);

        $anotherId = [['login' => 'rest-author-24', 'name' => 'Original editor', 'externalId' => '24']];
        self::assertSame('imported', $import('legacy', $anotherId, ['rest-author-24'], true)['outcomes'][0]['state']);
        self::assertSame('imported', $import('different-source', $second, [$secondLogin], true)['outcomes'][0]['state']);
        self::assertCount(3, $entries->all());
    }

    /** @return iterable<string,array{string,string}> */
    public static function formatOrders(): iterable
    {
        yield 'WXR then REST' => ['original-login', 'rest-author-23'];
        yield 'REST then WXR' => ['rest-author-23', 'original-login'];
    }

    public function testExactLegacyLoginCanBeBridgedByAnExportReplay(): void
    {
        $claims = [];
        [$import, $entries] = $this->fixture($claims);
        $legacy = [['login' => 'acme-editor', 'name' => 'Editor profile']];
        $rest = [['login' => 'rest-author-23', 'name' => 'Editor profile', 'externalId' => '23']];
        $original = $import('legacy', $legacy, [], true)['profiles'][ImportWordPressAuthors::key('acme-editor')];
        self::assertSame([['state' => 'planned']], $import('legacy', $rest, [], false)['outcomes']);

        $enriched = [$legacy[0] + ['externalId' => '23']];
        $import('legacy', $enriched, [], true);
        $result = $import('legacy', $rest, [], true);

        self::assertSame($original, $result['profiles'][ImportWordPressAuthors::key('rest-author-23')]);
        self::assertSame($original['id'], $claims['legacy'][ImportWordPressAuthors::key('acme-editor')]);
        self::assertCount(1, $entries->all());
    }

    public function testConflictingNumericDeclarationsDoNotMergeLegacyLogins(): void
    {
        $claims = [];
        [$import, $entries] = $this->fixture($claims);
        $authors = [
            ['login' => 'login-one', 'name' => 'Same name', 'externalId' => '23'],
            ['login' => 'login-two', 'name' => 'Same name', 'externalId' => '23'],
        ];

        $result = $import('legacy', $authors, [], true);

        self::assertSame(['imported', 'imported'], array_column($result['outcomes'], 'state'));
        self::assertCount(2, $claims['legacy']);
        self::assertCount(2, $entries->all());
    }

    public function testEstablishedNumericMappingDoesNotOverwriteExistingLegacyDuplicates(): void
    {
        $claims = [];
        [$import, $entries] = $this->fixture($claims);
        $wxr = ['login' => 'original-login', 'name' => 'WXR profile'];
        $rest = ['login' => 'rest-author-23', 'name' => 'REST profile'];
        $wxrKey = ImportWordPressAuthors::key($wxr['login']);
        $restKey = ImportWordPressAuthors::key($rest['login']);
        $first = $import('legacy', [$wxr], [], true)['profiles'][$wxrKey];
        $second = $import('legacy', [$rest], [], true)['profiles'][$restKey];

        $import('legacy', [$wxr + ['externalId' => '23']], [], true);
        $replay = $import('legacy', [$rest + ['externalId' => '23']], [], true);

        self::assertSame($first, $replay['profiles'][$restKey]);
        self::assertSame($first['id'], $claims['legacy'][$wxrKey]);
        self::assertSame($second['id'], $claims['legacy'][$restKey]);
        self::assertCount(2, $entries->all());
    }

    /**
     * @param array<string,array<string,string>> $claims
     * @return array{ImportWordPressAuthors,InMemoryEntryRepository}
     */
    private function fixture(array &$claims): array
    {
        $ledger = $this->createMock(WordPressImportLedger::class);
        $ledger->expects(self::atLeastOnce())->method('authorEntryId')->willReturnCallback(static function (string $source, string $key) use (&$claims): ?string {
            return $claims[$source][$key] ?? null;
        });
        $ledger->expects(self::atLeastOnce())->method('recordAuthor')->willReturnCallback(static function (string $source, string $key, string $id) use (&$claims): void {
            self::assertArrayNotHasKey($key, $claims[$source] ?? []);
            $claims[$source][$key] = $id;
        });
        $types = new InMemoryContentTypeRepository();
        $definitions = new GetContentTypeDefinition($types);
        $entries = new InMemoryEntryRepository();
        $createEntry = new CreateEntry($entries, new ValidateEntryValues($definitions), new SanitizeEntryValues($definitions, new PassThroughRichTextSanitizer()));

        return [new ImportWordPressAuthors($ledger, $entries, $definitions, new CreateContentType($types), $createEntry), $entries];
    }
}
