<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\ManageReusableSections;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;
use Cms\Content\Presentation\Http\ReusableSectionController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReusableSectionTest extends TestCase
{
    private const BLOCKS = [['id' => '11111111-1111-4111-8111-111111111111', 'type' => 'hero', 'data' => ['heading' => 'Reusable']]];

    public function test_section_lifecycle_is_versioned(): void
    {
        $repository = new MemorySectionRepository();
        $service = new ManageReusableSections($repository);
        $created = $service->create('Call to action', self::BLOCKS);
        $sectionId = $created['id'];
        self::assertNull($created['locale']);
        self::assertSame($sectionId, $created['translationGroupId']);
        $updated = $service->update($sectionId, 'Primary call to action', self::BLOCKS, 1);

        self::assertSame(2, $updated['version']);
        self::assertSame('Primary call to action', $service->all()[0]['name']);
        self::assertSame($sectionId, $updated['translationGroupId']);
        $service->delete($sectionId);
        self::assertSame([], $service->all());
    }

    public function test_translations_share_a_group_and_keep_independent_versions(): void
    {
        $service = new ManageReusableSections(new MemorySectionRepository());
        $english = $service->create('Call to action', self::BLOCKS, 'en');
        $russian = $service->create('Russian call to action', self::BLOCKS, 'ru-RU', $english['id']);
        $updated = $service->update($russian['id'], 'Primary Russian call to action', self::BLOCKS, 1);

        self::assertNotSame($english['id'], $russian['id']);
        self::assertSame($english['id'], $updated['translationGroupId']);
        self::assertSame('ru-RU', $updated['locale']);
        self::assertSame(2, $updated['version']);
        self::assertSame(1, $service->get($english['id'])['version']);

        $service->delete($english['id']);
        $newEnglish = $service->create('New English call to action', self::BLOCKS, 'en', $russian['id']);
        self::assertSame($english['id'], $newEnglish['translationGroupId']);
    }

    public function test_http_requires_a_normalized_locale_and_a_unique_variant(): void
    {
        $service = new ManageReusableSections(new MemorySectionRepository());
        $controller = new ReusableSectionController($service);
        $base = ['name' => 'Call to action', 'blocks' => self::BLOCKS];
        $created = $controller->create(self::request($base));
        self::assertSame(201, $created->getStatusCode());
        $root = self::body($created);
        self::assertIsString($root['id']);
        self::assertNull($root['locale']);
        self::assertSame($root['id'], $root['translationGroupId']);

        $translated = [...$base, 'name' => 'Portuguese call to action', 'translationSourceId' => $root['id']];
        self::assertSame(422, $controller->create(self::request($translated))->getStatusCode());
        self::assertSame(422, $controller->create(self::request([...$translated, 'locale' => 'pt-br']))->getStatusCode());
        self::assertSame(422, $controller->create(self::request([...$translated, 'locale' => 'pt-BR', 'translationSourceId' => 'bad-id']))->getStatusCode());
        self::assertSame(404, $controller->create(self::request([
            ...$translated,
            'locale' => 'pt-BR',
            'translationSourceId' => '22222222-2222-4222-8222-222222222222',
        ]))->getStatusCode());

        $createdVariant = $controller->create(self::request([...$translated, 'locale' => 'pt-BR']));
        self::assertSame(201, $createdVariant->getStatusCode());
        $variant = self::body($createdVariant);
        self::assertSame('pt-BR', $variant['locale']);
        self::assertSame($root['id'], $variant['translationGroupId']);
        self::assertSame(409, $controller->create(self::request([...$translated, 'name' => 'Another Portuguese name', 'locale' => 'pt-BR']))->getStatusCode());

        self::assertIsString($variant['id']);
        self::assertSame(422, $controller->update($variant['id'], self::request([
            ...$base,
            'name' => 'Brazilian Portuguese call to action',
            'version' => 1,
            'locale' => null,
        ], 'PUT'))->getStatusCode());
        $updated = self::body($controller->update($variant['id'], self::request([
            ...$base,
            'name' => 'Brazilian Portuguese call to action',
            'version' => 1,
        ], 'PUT')));
        self::assertSame('pt-BR', $updated['locale']);
        self::assertSame($root['id'], $updated['translationGroupId']);
    }

    /** @param array<string, mixed> $payload */
    private static function request(array $payload, string $method = 'POST'): Request
    {
        return Request::create('/', $method, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private static function body(JsonResponse $response): array
    {
        $responseBody = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($responseBody);

        /** @var array<string, mixed> $responseBody */
        return $responseBody;
    }
}

final class MemorySectionRepository implements ReusableSectionRepository
{
    /** @var array<string, ReusableSection> */
    private array $items = [];

    public function all(): array { return array_values($this->items); }
    public function get(string $id): ?ReusableSection { return $this->items[$id] ?? null; }
    public function add(ReusableSection $section): void
    {
        foreach ($this->items as $existing) {
            if ($existing->name === $section->name || ($existing->translationGroupId === $section->translationGroupId && $existing->locale === $section->locale)) {
                throw new ReusableSectionConflict();
            }
        }
        $this->items[$section->id] = $section;
    }
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
