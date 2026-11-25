<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Persistence;

use Cms\Content\Infrastructure\Persistence\DoctrinePageSectionUsageLookup;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class DoctrinePageSectionUsageLookupTest extends TestCase
{
    public function testItPaginatesOnlyMatchingDraftRowsAndKeepsTheirMatchingPlacements(): void
    {
        $sectionId = '1493b983-ea69-44b0-a173-ae6f9006ecb5';
        $first = ['sectionId' => $sectionId, 'version' => 1, 'blockIds' => ['cf49b538-07ee-4b53-a6a3-6b25cbfa22ae']];
        $latest = ['sectionId' => $sectionId, 'version' => 2, 'blockIds' => ['7bb4ff18-266f-44a7-a3d0-1b4d84ac0ac8']];
        $unrelated = [...$first, 'sectionId' => '6630b01a-9fb0-48c8-a487-3cf8f8876393'];
        $row = [
            'id' => '1e299522-3cdd-4e5d-b601-72f7eab4a822',
            'title' => 'Later page',
            'slug' => 'later-page',
            'locale' => 'ru-RU',
            'version' => '4',
            'section_links' => json_encode([$first, $unrelated, $latest], JSON_THROW_ON_ERROR),
        ];
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('iterateAssociative')
            ->with(
                'SELECT id, title, slug, locale, version, section_links FROM content_pages WHERE status = ? AND section_links IS NOT NULL ORDER BY title ASC, id ASC',
                ['draft'],
            )
            ->willReturn(new \ArrayIterator([
                [...$row, 'section_links' => json_encode([$unrelated], JSON_THROW_ON_ERROR)],
                [...$row, 'section_links' => json_encode([$first], JSON_THROW_ON_ERROR)],
                $row,
                [...$row, 'section_links' => json_encode([$latest], JSON_THROW_ON_ERROR)],
            ]));

        self::assertSame([
            'items' => [[
                'pageId' => $row['id'],
                'title' => 'Later page',
                'slug' => 'later-page',
                'locale' => 'ru-RU',
                'version' => 4,
                'placements' => [$first, $latest],
            ]],
            'total' => 3,
        ], (new DoctrinePageSectionUsageLookup($connection))->forSection($sectionId, 1, 1));
    }
}
