<?php

declare(strict_types=1);

namespace Cms\Publishing\Infrastructure\Persistence;

use Cms\Publishing\Domain\PublishedPage;
use Cms\Publishing\Domain\PublishedPageRepository;
use Cms\Publishing\Domain\PublishedRedirect;
use Cms\Publishing\Domain\PublishedSlugAlreadyExists;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final readonly class DoctrinePublishedPageRepository implements PublishedPageRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function publish(PublishedPage $page): PublishedPage
    {
        $existing = $this->findByRevision($page->pageId, $page->sourceRevision);

        if ($existing !== null) {
            $this->activate($existing);
            return $existing;
        }

        try {
            $this->connection->transactional(function (Connection $connection) use ($page): void {
                $connection->insert('published_page_snapshots', [
                    'id' => $page->snapshotId,
                    'page_id' => $page->pageId,
                    'source_revision' => $page->sourceRevision,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'blocks' => json_encode($page->blocks, JSON_THROW_ON_ERROR),
                    'seo' => json_encode($page->seo, JSON_THROW_ON_ERROR),
                    'published_at' => $page->publishedAt->format('Y-m-d H:i:s.u'),
                ]);
                $this->activateUsing($connection, $page);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = $this->findByRevision($page->pageId, $page->sourceRevision);

            if ($existing === null) {
                throw new PublishedSlugAlreadyExists($page->slug);
            }

            $this->activate($existing);
            return $existing;
        }

        return $page;
    }

    public function unpublish(string $pageId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($pageId): void {
            $connection->delete('published_page_routes', ['page_id' => $pageId]);
            $connection->delete('published_page_redirects', ['page_id' => $pageId]);
        });
    }

    public function findBySlug(string $slug): ?PublishedPage
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT snapshot.*
FROM published_page_routes route
JOIN published_page_snapshots snapshot ON snapshot.id = route.snapshot_id
WHERE route.slug = ?
SQL, [$slug]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findRedirectTargetBySlug(string $slug): ?PublishedPage
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
SELECT snapshot.*
FROM published_page_redirects redirect
JOIN published_page_routes route ON route.page_id = redirect.page_id
JOIN published_page_snapshots snapshot ON snapshot.id = route.snapshot_id
WHERE redirect.source_slug = ?
SQL, [$slug]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function redirectsForPage(string $pageId): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT redirect.source_slug, route.slug AS target_slug
FROM published_page_redirects redirect
JOIN published_page_routes route ON route.page_id = redirect.page_id
WHERE redirect.page_id = ?
ORDER BY redirect.created_at DESC, redirect.source_slug ASC
SQL, [$pageId]);

        return array_map(
            static fn (array $row): PublishedRedirect => new PublishedRedirect(
                self::stringValue($row, 'source_slug'),
                self::stringValue($row, 'target_slug'),
            ),
            $rows,
        );
    }

    public function addRedirect(string $pageId, string $sourceSlug): PublishedRedirect
    {
        try {
            return $this->connection->transactional(function (Connection $connection) use ($pageId, $sourceSlug): PublishedRedirect {
                $targetSlug = $connection->fetchOne('SELECT slug FROM published_page_routes WHERE page_id = ? FOR UPDATE', [$pageId]);
                if (!is_string($targetSlug)) throw new \Cms\Publishing\Domain\PublishedPageNotActive();
                $redirect = new PublishedRedirect($sourceSlug, $targetSlug);
                $activeOwner = $connection->fetchOne('SELECT page_id FROM published_page_routes WHERE slug = ? FOR UPDATE', [$sourceSlug]);
                if (is_string($activeOwner)) throw new PublishedSlugAlreadyExists($sourceSlug);
                $redirectOwner = $connection->fetchOne('SELECT page_id FROM published_page_redirects WHERE source_slug = ? FOR UPDATE', [$sourceSlug]);
                if (is_string($redirectOwner) && $redirectOwner !== $pageId) throw new PublishedSlugAlreadyExists($sourceSlug);
                if (!is_string($redirectOwner)) {
                    $connection->insert('published_page_redirects', ['source_slug'=>$sourceSlug,'page_id'=>$pageId,'created_at'=>(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')]);
                }

                return $redirect;
            });
        } catch (UniqueConstraintViolationException) {
            throw new PublishedSlugAlreadyExists($sourceSlug);
        }
    }

    public function removeRedirect(string $pageId, string $sourceSlug): void
    {
        $this->connection->delete('published_page_redirects', ['source_slug'=>$sourceSlug,'page_id'=>$pageId]);
    }

    public function allActive(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
SELECT snapshot.*
FROM published_page_routes route
JOIN published_page_snapshots snapshot ON snapshot.id = route.snapshot_id
ORDER BY snapshot.published_at DESC
SQL);

        return array_map($this->hydrate(...), $rows);
    }

    private function findByRevision(string $pageId, int $revision): ?PublishedPage
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM published_page_snapshots WHERE page_id = ? AND source_revision = ?',
            [$pageId, $revision],
        );

        return $row === false ? null : $this->hydrate($row);
    }

    private function activate(PublishedPage $page): void
    {
        try {
            $this->connection->transactional(function (Connection $connection) use ($page): void {
                $this->activateUsing($connection, $page);
            });
        } catch (UniqueConstraintViolationException) {
            throw new PublishedSlugAlreadyExists($page->slug);
        }
    }

    private function activateUsing(Connection $connection, PublishedPage $page): void
    {
        $previousSlug = $connection->fetchOne(
            'SELECT slug FROM published_page_routes WHERE page_id = ? FOR UPDATE',
            [$page->pageId],
        );
        $redirectOwner = $connection->fetchOne(
            'SELECT page_id FROM published_page_redirects WHERE source_slug = ? FOR UPDATE',
            [$page->slug],
        );

        if (is_string($redirectOwner) && $redirectOwner !== $page->pageId) {
            throw new PublishedSlugAlreadyExists($page->slug);
        }

        if (is_string($previousSlug) && $previousSlug !== $page->slug) {
            $previousOwner = $connection->fetchOne(
                'SELECT page_id FROM published_page_redirects WHERE source_slug = ? FOR UPDATE',
                [$previousSlug],
            );
            if (is_string($previousOwner) && $previousOwner !== $page->pageId) {
                throw new PublishedSlugAlreadyExists($previousSlug);
            }

            $connection->delete('published_page_redirects', ['source_slug' => $previousSlug]);
            $connection->insert('published_page_redirects', [
                'source_slug' => $previousSlug,
                'page_id' => $page->pageId,
                'created_at' => $page->publishedAt->format('Y-m-d H:i:s.u'),
            ]);
        }

        $connection->delete('published_page_redirects', [
            'source_slug' => $page->slug,
            'page_id' => $page->pageId,
        ]);
        $connection->delete('published_page_routes', ['page_id' => $page->pageId]);
        $connection->insert('published_page_routes', [
            'slug' => $page->slug,
            'page_id' => $page->pageId,
            'snapshot_id' => $page->snapshotId,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PublishedPage
    {
        $blocks = json_decode(self::stringValue($row, 'blocks'), true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($blocks) || !array_is_list($blocks)) {
            throw new \UnexpectedValueException('Published blocks must be a JSON list.');
        }

        $seo = json_decode(self::stringValue($row, 'seo'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($seo) || array_is_list($seo)) {
            throw new \UnexpectedValueException('Published SEO settings must be a JSON object.');
        }
        /** @var array<string, mixed> $seo */

        $normalizedBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new \UnexpectedValueException('Every published block must be an object.');
            }

            $id = $block['id'] ?? null;
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? null;
            if (!is_string($id) || !is_string($type) || !is_array($data)) {
                throw new \UnexpectedValueException('Invalid published block.');
            }

            $normalizedData = [];
            foreach ($data as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    throw new \UnexpectedValueException('Invalid published block field.');
                }
                $normalizedData[$key] = $value;
            }
            $normalizedBlocks[] = ['id' => $id, 'type' => $type, 'data' => $normalizedData];
        }

        return new PublishedPage(
            self::stringValue($row, 'id'),
            self::stringValue($row, 'page_id'),
            self::intValue($row, 'source_revision'),
            self::stringValue($row, 'title'),
            self::stringValue($row, 'slug'),
            $normalizedBlocks,
            self::seoValue($seo),
            new \DateTimeImmutable(self::stringValue($row, 'published_at'), new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $seo
     *  @return array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string}
     */
    private static function seoValue(array $seo): array
    {
        $title = $seo['title'] ?? null;
        $description = $seo['description'] ?? null;
        $noIndex = $seo['noIndex'] ?? null;
        $openGraphImageAssetId = $seo['openGraphImageAssetId'] ?? null;

        if (!is_string($title) || !is_string($description) || !is_bool($noIndex) || ($openGraphImageAssetId !== null && !is_string($openGraphImageAssetId))) {
            throw new \UnexpectedValueException('Invalid published SEO settings.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'noIndex' => $noIndex,
            'openGraphImageAssetId' => $openGraphImageAssetId,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        }
        return (int) $value;
    }
}
