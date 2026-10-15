<?php

declare(strict_types=1);

namespace Cms\Publishing\Infrastructure\Persistence;

use Cms\Publishing\Domain\PublishedEntry;
use Cms\Publishing\Domain\PublishedEntryRepository;
use Cms\Publishing\Domain\PublishedEntrySlugAlreadyExists;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrinePublishedEntryRepository implements PublishedEntryRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function publish(PublishedEntry $e): PublishedEntry
    {
        $existing = $this->findByVersion($e->entryId, $e->sourceVersion);
        if (null !== $existing) {
            $this->activate($existing);

            return $existing;
        }
        try {
            $this->connection->transactional(function (Connection $c) use ($e): void {
                $c->insert('published_entry_snapshots', ['id' => $e->snapshotId, 'entry_id' => $e->entryId, 'source_version' => $e->sourceVersion, 'content_type' => json_encode($e->contentType, JSON_THROW_ON_ERROR), 'title' => $e->title, 'slug' => $e->slug, 'values_json' => json_encode($e->values, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT), 'taxonomies' => json_encode($e->taxonomies, JSON_THROW_ON_ERROR), 'published_at' => $e->publishedAt->format('Y-m-d H:i:s.u')]);
                foreach ($e->taxonomies as $term) {
                    $c->insert('published_entry_terms', ['snapshot_id' => $e->snapshotId, 'taxonomy_slug' => $term['taxonomySlug'], 'term_slug' => $term['termSlug']]);
                }
                $this->activateUsing($c, $e);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByVersion($e->entryId, $e->sourceVersion);
            if (null === $existing) {
                throw new PublishedEntrySlugAlreadyExists($e->slug);
            }
            $this->activate($existing);

            return $existing;
        }

        return $e;
    }

    public function unpublish(string $entryId): void
    {
        $this->connection->delete('published_entry_routes', ['entry_id' => $entryId]);
    }

    public function find(string $apiName, string $slug): ?PublishedEntry
    {
        $r = $this->connection->fetchAssociative('SELECT s.* FROM published_entry_routes r JOIN published_entry_snapshots s ON s.id=r.snapshot_id WHERE r.api_name=? AND r.slug=?', [$apiName, $slug]);

        return false === $r ? null : $this->hydrate($r);
    }

    public function list(string $apiName, int $limit, int $offset, ?string $taxonomy = null, ?string $term = null): array
    {
        $join = $taxonomy === null ? '' : ' JOIN published_entry_terms t ON t.snapshot_id=s.id';
        $filter = $taxonomy === null ? '' : ' AND t.taxonomy_slug=? AND t.term_slug=?';
        $params = $taxonomy === null ? [$apiName,$limit,$offset] : [$apiName,$taxonomy,$term,$limit,$offset];
        $types = $taxonomy === null ? [\Doctrine\DBAL\ParameterType::STRING,\Doctrine\DBAL\ParameterType::INTEGER,\Doctrine\DBAL\ParameterType::INTEGER] : [\Doctrine\DBAL\ParameterType::STRING,\Doctrine\DBAL\ParameterType::STRING,\Doctrine\DBAL\ParameterType::STRING,\Doctrine\DBAL\ParameterType::INTEGER,\Doctrine\DBAL\ParameterType::INTEGER];
        return array_map($this->hydrate(...), $this->connection->fetchAllAssociative('SELECT s.* FROM published_entry_routes r JOIN published_entry_snapshots s ON s.id=r.snapshot_id'.$join.' WHERE r.api_name=?'.$filter.' ORDER BY s.published_at DESC LIMIT ? OFFSET ?', $params, $types));
    }

    public function count(string $apiName, ?string $taxonomy = null, ?string $term = null): int
    {
        $join = $taxonomy === null ? '' : ' JOIN published_entry_terms t ON t.snapshot_id=r.snapshot_id';
        $filter = $taxonomy === null ? '' : ' AND t.taxonomy_slug=? AND t.term_slug=?';
        $params = $taxonomy === null ? [$apiName] : [$apiName,$taxonomy,$term];
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM published_entry_routes r'.$join.' WHERE r.api_name=?'.$filter, $params);
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException('Published entry count must be an integer.');
        }

        return (int) $value;
    }

    public function findByVersion(string $entryId, int $sourceVersion): ?PublishedEntry
    {
        $r = $this->connection->fetchAssociative(
            'SELECT * FROM published_entry_snapshots WHERE entry_id = ? AND source_version = ?',
            [$entryId, $sourceVersion],
        );

        return false === $r ? null : $this->hydrate($r);
    }

    private function activate(PublishedEntry $e): void
    {
        try {
            $this->connection->transactional(fn (Connection $c) => $this->activateUsing($c, $e));
        } catch (UniqueConstraintViolationException) {
            throw new PublishedEntrySlugAlreadyExists($e->slug);
        }
    }

    private function activateUsing(Connection $c, PublishedEntry $e): void
    {
        $c->executeQuery('SELECT entry_id FROM published_entry_routes WHERE entry_id=? FOR UPDATE', [$e->entryId]);
        $c->delete('published_entry_routes', ['entry_id' => $e->entryId]);
        $c->insert('published_entry_routes', ['api_name' => $e->contentType['apiName'], 'slug' => $e->slug, 'entry_id' => $e->entryId, 'snapshot_id' => $e->snapshotId]);
    }

    /** @param array<string,mixed> $r */
    private function hydrate(array $r): PublishedEntry
    {
        return new PublishedEntry(self::s($r, 'id'), self::s($r, 'entry_id'), self::i($r, 'source_version'), self::contentType($r), self::s($r, 'title'), self::s($r, 'slug'), self::values($r), self::taxonomies($r), new \DateTimeImmutable(self::s($r, 'published_at'), new \DateTimeZone('UTC')));
    }

    /** @param array<string,mixed> $r
     * @return list<array{taxonomyId:string,taxonomySlug:string,termId:string,termSlug:string,name:string}>
     */
    private static function taxonomies(array $r): array
    {
        $value=json_decode(self::s($r,'taxonomies'),true,flags:JSON_THROW_ON_ERROR);if(!is_array($value)||!array_is_list($value))throw new \UnexpectedValueException('Invalid published taxonomies.');
        $result=[];foreach($value as $item){if(!is_array($item))throw new \UnexpectedValueException('Invalid published taxonomy term.');$taxonomyId=$item['taxonomyId']??null;$taxonomySlug=$item['taxonomySlug']??null;$termId=$item['termId']??null;$termSlug=$item['termSlug']??null;$name=$item['name']??null;if(!is_string($taxonomyId)||!is_string($taxonomySlug)||!is_string($termId)||!is_string($termSlug)||!is_string($name))throw new \UnexpectedValueException('Invalid published taxonomy term.');$result[]=['taxonomyId'=>$taxonomyId,'taxonomySlug'=>$taxonomySlug,'termId'=>$termId,'termSlug'=>$termSlug,'name'=>$name];}return $result;
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array{id:string,name:string,apiName:string,version:int,fields:list<array{id:string,key:string,label:string,type:string,required:bool,targetContentTypeId:?string,multiple:bool}>}
     */
    private static function contentType(array $r): array
    {
        $value = json_decode(self::s($r, 'content_type'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new \UnexpectedValueException('Published content type must be an object.');
        }
        $id = $value['id'] ?? null;
        $name = $value['name'] ?? null;
        $apiName = $value['apiName'] ?? null;
        $version = $value['version'] ?? null;
        $fields = $value['fields'] ?? null;
        if (!is_string($id) || !is_string($name) || !is_string($apiName) || !is_int($version) || !is_array($fields) || !array_is_list($fields)) {
            throw new \UnexpectedValueException('Invalid published content type.');
        }
        $normalized = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                throw new \UnexpectedValueException('Invalid published field.');
            }
            $fieldId = $field['id'] ?? null;
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? null;
            $type = $field['type'] ?? null;
            $required = $field['required'] ?? null;
            $targetContentTypeId = $field['targetContentTypeId'] ?? null;
            $multiple = $field['multiple'] ?? false;
            if (!is_string($fieldId) || !is_string($key) || !is_string($label) || !is_string($type) || !is_bool($required) || (null !== $targetContentTypeId && !is_string($targetContentTypeId)) || !is_bool($multiple)) {
                throw new \UnexpectedValueException('Invalid published field.');
            }
            $normalized[] = ['id' => $fieldId, 'key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, 'targetContentTypeId' => $targetContentTypeId, 'multiple' => $multiple];
        }

        return ['id' => $id, 'name' => $name, 'apiName' => $apiName, 'version' => $version, 'fields' => $normalized];
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return array<string, string|int|float|bool|list<string>|null>
     */
    private static function values(array $r): array
    {
        $value = json_decode(self::s($r, 'values_json'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new \UnexpectedValueException('Published values must be an object.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \UnexpectedValueException('Invalid published value.');
            }
            if (is_string($item) || is_int($item) || is_float($item) || is_bool($item) || null === $item) {
                $result[$key] = $item;
                continue;
            }
            if (self::isStringList($item)) {
                /** @var list<string> $item */
                $result[$key] = $item;
                continue;
            }
            throw new \UnexpectedValueException('Invalid published value.');
        }

        return $result;
    }

    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $r */
    private static function s(array $r, string $k): string
    {
        $v = $r[$k] ?? null;
        if (!is_string($v)) {
            throw new \UnexpectedValueException('Invalid string column.');
        }

        return $v;
    }

    /** @param array<string,mixed> $r */
    private static function i(array $r, string $k): int
    {
        $v = $r[$k] ?? null;
        if (!is_int($v) && !(is_string($v) && ctype_digit($v))) {
            throw new \UnexpectedValueException('Invalid integer column.');
        }

        return (int) $v;
    }
}
