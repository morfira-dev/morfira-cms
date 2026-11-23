<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Import;

/**
 * @phpstan-import-type Export from WordPressExportReader
 * @phpstan-import-type Term from WordPressExportReader
 * @phpstan-import-type TermReference from WordPressExportReader
 * @phpstan-import-type ExportItem from WordPressExportReader
 * @phpstan-import-type Attachment from WordPressExportReader
 * @phpstan-import-type Author from WordPressExportReader
 */
final class WordPressRestMapper
{
    private const array IMPORTABLE_STATUSES = ['publish', 'private', 'draft', 'pending', 'future'];

    /**
     * @param array<string,list<array<string,mixed>>> $collections
     * @return Export
     */
    public function __invoke(string $sourceUrl, array $collections, bool $includePrivate = false): array
    {
        $posts = $this->index($collections['posts'] ?? []);
        $pages = $this->index($collections['pages'] ?? []);
        $media = $this->index($collections['media'] ?? []);
        $authenticatedMedia = $includePrivate ? $this->index($collections['authenticatedMedia'] ?? []) : [];
        if (array_intersect_key($posts, $pages) !== [] || array_intersect_key($posts + $pages, $media + $authenticatedMedia) !== []) {
            throw new \InvalidArgumentException('WordPress REST collections contain an overlapping content identifier.');
        }
        $categories = $this->index($collections['categories'] ?? []);
        $tags = $this->index($collections['tags'] ?? []);
        $users = $this->index($collections['users'] ?? []);

        $items = [];
        $publicItems = [];
        foreach (['post' => $posts, 'page' => $pages] as $type => $records) {
            foreach ($records as $id => $record) {
                $item = $this->item($id, $type, $record, $categories, $tags, $includePrivate);
                $items[] = $item;
                if ($item['status'] === 'publish' && !$item['passwordProtected'] && !($item['restrictedSource'] ?? false)) {
                    $publicItems[$id] = true;
                }
            }
        }

        $attachments = [];
        $skippedTypes = [];
        foreach ($authenticatedMedia + $media as $id => $record) {
            $publicRecord = $media[$id] ?? null;
            $parent = $record['post'] ?? null;
            $publicParent = $parent === null || $parent === 0 || $parent === '0' || isset($publicItems[self::id($parent) ?? '']);
            $private = $includePrivate && isset($authenticatedMedia[$id]) && (
                $publicRecord === null || !$publicParent || ($record['status'] ?? null) === 'private'
                || !$this->attachment($id, $publicRecord, $publicItems)['eligible']
            );
            // Public metadata always comes from the anonymous collection.
            if (!$private && $publicRecord !== null) {
                $record = $publicRecord;
            }
            if (($record['media_type'] ?? null) !== 'image' || !in_array($record['mime_type'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $skippedTypes['unsupported_media'] = ($skippedTypes['unsupported_media'] ?? 0) + 1;
                continue;
            }
            $attachments[] = $this->attachment($id, $record, $publicItems, $private);
        }

        $authors = [];
        foreach ($users as $id => $record) {
            $authors[] = $this->author($id, $record);
        }

        return [
            'items' => $items,
            'skippedTypes' => $skippedTypes,
            'attachments' => $attachments,
            'terms' => [...$this->terms('category', $categories), ...$this->terms('post_tag', $tags)],
            'authors' => $authors,
            'sourceUrl' => $sourceUrl,
        ];
    }

    /**
     * @param list<array<string,mixed>> $records
     * @return array<string,array<string,mixed>>
     */
    private function index(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $id = self::id($record['id'] ?? null);
            if ($id === null || isset($indexed[$id])) {
                throw new \InvalidArgumentException('A WordPress REST collection contains an invalid or duplicate identifier.');
            }
            // A prefix keeps PHP from coercing the decimal identifiers to integer keys.
            $indexed[$id] = $record;
        }

        return $indexed;
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,array<string,mixed>> $categories
     * @param array<string,array<string,mixed>> $tags
     * @return ExportItem
     */
    private function item(string $id, string $type, array $record, array $categories, array $tags, bool $includePrivate): array
    {
        $content = $record['content'] ?? null;
        $protection = is_array($content) ? ($content['protected'] ?? null) : null;
        $protected = $protection !== false;
        $status = self::string($record['status'] ?? null);
        $public = $status === 'publish' && !$protected;
        $raw = is_array($content) ? ($content['raw'] ?? null) : null;
        // Treat an absent protection flag as an incomplete edit-context response.
        $restricted = $includePrivate && !$public && is_bool($protection) && is_string($raw)
            && in_array($status, self::IMPORTABLE_STATUSES, true);
        $eligible = $public || $restricted;
        $author = self::id($record['author'] ?? null);
        $title = $record['title'] ?? null;
        $rawTitle = is_array($title) ? ($title['raw'] ?? null) : null;

        return [
            'id' => substr($id, 1),
            'type' => $type,
            'status' => $restricted || $status === 'publish' ? $status : 'private',
            'title' => $eligible ? self::plain($restricted && is_string($rawTitle) ? $rawTitle : self::rendered($title)) : '',
            'slug' => $eligible ? trim(self::string($record['slug'] ?? null)) : '',
            'html' => $restricted ? $raw : ($public ? self::rendered($content) : ''),
            'link' => $public ? trim(self::string($record['link'] ?? null)) : '',
            'passwordProtected' => $protected,
            'terms' => $eligible && $type === 'post' ? [
                ...$this->references('category', $record['categories'] ?? null, $categories),
                ...$this->references('post_tag', $record['tags'] ?? null, $tags),
            ] : [],
            'thumbnailId' => $eligible ? self::decimal($record['featured_media'] ?? null) : null,
            'authorLogin' => $eligible && $author !== null ? 'rest-author-'.substr($author, 1) : null,
        ] + ($restricted ? ['restrictedSource' => true] : []);
    }

    /**
     * @param array<string,mixed> $record
     * @param array<string,true> $publicItems
     * @return Attachment
     */
    private function attachment(string $id, array $record, array $publicItems, bool $private = false): array
    {
        $details = $record['media_details'] ?? null;
        $parent = $record['post'] ?? null;
        $parentId = self::id($parent);

        return [
            'id' => substr($id, 1),
            'path' => is_array($details) ? trim(self::string($details['file'] ?? null)) : '',
            'url' => trim(self::string($record['source_url'] ?? null)),
            'alt' => trim(self::string($record['alt_text'] ?? null)),
            'eligible' => $private
                ? in_array($record['status'] ?? null, ['inherit', 'publish', 'private'], true)
                : in_array($record['status'] ?? null, ['inherit', 'publish'], true)
                    && ($parent === null || $parent === 0 || $parent === '0' || ($parentId !== null && isset($publicItems[$parentId]))),
        ] + ($private ? ['isPrivate' => true] : []);
    }

    /**
     * @param array<string,array<string,mixed>> $records
     * @return list<Term>
     */
    private function terms(string $taxonomy, array $records): array
    {
        $terms = [];
        foreach ($records as $record) {
            $parent = self::id($record['parent'] ?? null);
            $parentSlug = $parent === null ? '' : $this->termSlug($parent, $records);
            $terms[] = [
                'taxonomy' => $taxonomy,
                'slug' => trim(self::string($record['slug'] ?? null)),
                'name' => self::plain(self::string($record['name'] ?? null)),
                'parent' => $taxonomy === 'category' ? $parentSlug : '',
            ];
        }

        return $terms;
    }

    /**
     * @param array<string,array<string,mixed>> $records
     * @return list<TermReference>
     */
    private function references(string $taxonomy, mixed $ids, array $records): array
    {
        if (!is_array($ids)) {
            return [];
        }
        $references = [];
        foreach ($ids as $value) {
            $id = self::id($value);
            if ($id !== null) {
                $references[$id] = ['taxonomy' => $taxonomy, 'slug' => $this->termSlug($id, $records)];
            }
        }

        return array_values($references);
    }

    /** @param array<string,array<string,mixed>> $records */
    private function termSlug(string $id, array $records): string
    {
        $slug = trim(self::string($records[$id]['slug'] ?? null));

        // A missing reference remains unresolved instead of flattening the hierarchy.
        return $slug === '' ? '#missing-'.substr($id, 1) : $slug;
    }

    /**
     * @param array<string,mixed> $record
     * @return Author
     */
    private function author(string $id, array $record): array
    {
        $externalId = substr($id, 1);
        $login = 'rest-author-'.$externalId;
        foreach (['name', 'slug'] as $field) {
            $candidate = self::plain(self::string($record[$field] ?? null));
            if ($candidate !== '' && !str_contains($candidate, '@') && preg_match('/[\x00-\x1f\x7f]/', $candidate) !== 1) {
                return ['login' => $login, 'name' => mb_substr($candidate, 0, 200), 'externalId' => $externalId];
            }
        }

        return ['login' => $login, 'name' => $login, 'externalId' => $externalId];
    }

    private static function id(mixed $value): ?string
    {
        $decimal = self::decimal($value);

        return $decimal === null ? null : '#'.$decimal;
    }

    private static function decimal(mixed $value): ?string
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $decimal = (string) $value;

        return preg_match('/\A[1-9][0-9]{0,19}\z/', $decimal) === 1 ? $decimal : null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function rendered(mixed $value): string
    {
        return is_array($value) ? self::string($value['rendered'] ?? null) : '';
    }

    private static function plain(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
