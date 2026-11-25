<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Infrastructure\Persistence;

use Cms\Content\Application\ManageReusableSections;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Infrastructure\Persistence\DoctrineReusableSectionRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
final class DoctrineReusableSectionRepositoryTest extends TestCase
{
    public function test_legacy_neutral_sections_and_localized_variants_round_trip(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement(
            'CREATE TABLE content_reusable_sections (
                id CHAR(36) PRIMARY KEY,
                name VARCHAR(120) NOT NULL UNIQUE,
                blocks TEXT NOT NULL,
                version INT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                locale VARCHAR(6) DEFAULT NULL,
                translation_group_id CHAR(36) DEFAULT NULL
            )',
        );
        $connection->executeStatement(
            'CREATE UNIQUE INDEX uniq_reusable_section_group_locale ON content_reusable_sections (translation_group_id, locale)',
        );

        $legacyId = '33333333-3333-4333-8333-333333333333';
        $blocks = [['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'hero', 'data' => ['heading' => 'Reusable']]];
        $connection->insert('content_reusable_sections', [
            'id' => $legacyId, 'name' => 'Legacy section', 'blocks' => json_encode($blocks, JSON_THROW_ON_ERROR), 'version' => 3,
            'created_at' => '2026-11-23 10:00:00.000000', 'updated_at' => '2026-11-23 10:00:00.000000',
        ]);

        $sections = new ManageReusableSections(new DoctrineReusableSectionRepository($connection));
        $legacy = $sections->get($legacyId);
        self::assertNull($legacy['locale']);
        self::assertSame($legacyId, $legacy['translationGroupId']);
        self::assertSame(3, $legacy['version']);
        $translated = $sections->create('Section in Russian', $blocks, 'ru-RU', $legacyId);
        $sections->update($translated['id'], 'Updated section in Russian', $blocks, 1);
        $updated = $sections->get($translated['id']);
        self::assertSame('ru-RU', $updated['locale']);
        self::assertSame($legacyId, $updated['translationGroupId']);
        self::assertSame(2, $updated['version']);
        self::assertSame(3, $sections->get($legacyId)['version']);

        $this->expectException(ReusableSectionConflict::class);
        $sections->create('Another section in Russian', $blocks, 'ru-RU', $legacyId);
    }
}
