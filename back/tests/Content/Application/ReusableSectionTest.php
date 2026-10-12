<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\ManageReusableSections;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;
use PHPUnit\Framework\TestCase;

final class ReusableSectionTest extends TestCase
{
    public function test_section_lifecycle_is_versioned(): void
    {
        $repository = new MemorySectionRepository();
        $service = new ManageReusableSections($repository);
        $blocks = [['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'hero', 'data' => ['heading' => 'Reusable']]];

        $created = $service->create('Call to action', $blocks);
        $sectionId = $created['id'];
        $updated = $service->update($sectionId, 'Primary call to action', $blocks, 1);

        self::assertSame(2, $updated['version']);
        self::assertSame('Primary call to action', $service->all()[0]['name']);
        $service->delete($sectionId);
        self::assertSame([], $service->all());
    }
}

final class MemorySectionRepository implements ReusableSectionRepository
{
    /** @var array<string, ReusableSection> */
    private array $items = [];

    public function all(): array { return array_values($this->items); }
    public function get(string $id): ?ReusableSection { return $this->items[$id] ?? null; }
    public function add(ReusableSection $section): void { $this->items[$section->id] = $section; }
    public function save(ReusableSection $section, int $expectedVersion): void
    {
        if (($this->items[$section->id]->version ?? null) !== $expectedVersion) throw new ReusableSectionConflict();
        $this->items[$section->id] = $section;
    }
    public function remove(string $id): bool
    {
        if (!isset($this->items[$id])) return false;
        unset($this->items[$id]);
        return true;
    }
}
