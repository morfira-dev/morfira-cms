<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\GetPage\GetPage;
use Cms\Content\Application\ListPageRevisions;
use Cms\Content\Application\ListPages\ListPages;
use Cms\Content\Application\RestorePageRevision;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\PageLayout;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionRepository;
use Cms\Content\Presentation\Http\PageController;
use Cms\Content\Presentation\Http\PageRevisionController;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class LinkedSectionsControllerTest extends TestCase
{
    private const BLOCK = [
        'id' => 'cf49b538-07ee-4b53-a6a3-6b25cbfa22ae',
        'type' => 'hero',
        'data' => ['heading' => 'Shared heading'],
    ];

    private const LINK = [
        'sectionId' => '1493b983-ea69-44b0-a173-ae6f9006ecb5',
        'version' => 1,
        'blockIds' => [self::BLOCK['id']],
    ];

    public function testLinkedPlacementsAreRevisionedAndCompatibleWithOlderClients(): void
    {
        $pages = new InMemoryPageRepository();
        $id = (new CreatePage($pages))('Landing', 'landing')->id;
        $controller = self::controller($pages);
        $revisions = new PageRevisionController(new ListPageRevisions($pages), new RestorePageRevision($pages));

        self::assertArrayNotHasKey('sectionLinks', self::payload($controller->get($id)));
        $linked = $controller->update($id, self::request([...self::revisionPayload(1), 'sectionLinks' => [self::LINK]]));
        self::assertSame(200, $linked->getStatusCode());
        self::assertSame([self::LINK], self::payload($linked)['sectionLinks']);
        self::assertSame([self::LINK], self::payload($controller->get($id))['sectionLinks']);

        $oldClientSave = $controller->update($id, self::request(self::revisionPayload(2)));
        self::assertSame(200, $oldClientSave->getStatusCode());
        self::assertSame([self::LINK], self::payload($oldClientSave)['sectionLinks']);
        $staleWrite = $controller->update($id, self::request([...self::revisionPayload(2), 'sectionLinks' => []]));
        self::assertSame(409, $staleWrite->getStatusCode());

        $detach = $controller->update($id, self::request([...self::revisionPayload(3), 'sectionLinks' => []]));
        self::assertSame(200, $detach->getStatusCode());
        self::assertArrayNotHasKey('sectionLinks', self::payload($detach));

        $restored = $revisions->restore($id, 2, self::request(['version' => 4], 'POST'));
        self::assertSame(200, $restored->getStatusCode());
        self::assertSame([self::LINK], self::payload($restored)['sectionLinks']);
        self::assertSame(5, self::payload($restored)['version']);
        self::assertStringContainsString(self::LINK['sectionId'], (string) $revisions->list($id)->getContent());

        $oldClientRemove = $controller->update($id, self::request([...self::revisionPayload(5), 'blocks' => []]));
        self::assertSame(200, $oldClientRemove->getStatusCode());
        self::assertArrayNotHasKey('sectionLinks', self::payload($oldClientRemove));
    }

    public function testInvalidMembershipCannotChangeTheDraft(): void
    {
        $pages = new InMemoryPageRepository();
        $id = (new CreatePage($pages))('Landing', 'landing')->id;
        $controller = self::controller($pages);

        foreach ([
            null,
            new \stdClass(),
            [self::LINK, self::LINK],
            [[...self::LINK, 'blockIds' => ['1e299522-3cdd-4e5d-b601-72f7eab4a822']]],
            [[...self::LINK, 'blockIds' => (object) self::LINK['blockIds']]],
            [[...self::LINK, 'version' => 0]],
        ] as $invalidLinks) {
            $response = $controller->update($id, self::request([...self::revisionPayload(1), 'sectionLinks' => $invalidLinks]));
            self::assertSame(422, $response->getStatusCode());
        }

        $duplicateBlock = $controller->update($id, self::request([
            ...self::revisionPayload(1),
            'blocks' => [self::BLOCK, self::BLOCK],
            'sectionLinks' => [self::LINK],
        ]));
        self::assertSame(422, $duplicateBlock->getStatusCode());
        self::assertSame(1, self::payload($controller->get($id))['version']);
    }

    public function testKnownLocalizedSectionMustMatchTheAuthoredPageLocale(): void
    {
        $pages = new InMemoryPageRepository();
        $id = (new CreatePage($pages))('Landing', 'landing')->id;
        $clock = new \DateTimeImmutable('2026-11-25T00:00:00Z');
        $section = new ReusableSection(self::LINK['sectionId'], 'Localized', [PageBlock::fromArray(self::BLOCK)], 1, $clock, $clock, 'ru-RU');
        $sections = $this->createStub(ReusableSectionRepository::class);
        $sections->method('get')->willReturn($section);
        $controller = self::controller($pages, $sections);
        $payload = [...self::revisionPayload(1), 'sectionLinks' => [self::LINK]];

        self::assertSame(422, $controller->update($id, self::request($payload))->getStatusCode());
        self::assertSame(1, self::payload($controller->get($id))['version']);
        self::assertSame(200, $controller->update($id, self::request([...$payload, 'locale' => 'ru-RU']))->getStatusCode());
        self::assertSame(422, $controller->update($id, self::request([...self::revisionPayload(2), 'locale' => 'en']))->getStatusCode());
        self::assertSame(200, $controller->update($id, self::request([...self::revisionPayload(2), 'locale' => 'en', 'sectionLinks' => []]))->getStatusCode());
    }

    private static function controller(InMemoryPageRepository $pages, ?ReusableSectionRepository $sections = null): PageController
    {
        return new PageController(
            new CreatePage($pages),
            new GetPage($pages),
            new ListPages($pages),
            new UpdatePage($pages, new PassThroughRichTextSanitizer(), sections: $sections),
        );
    }

    /** @return array<string, mixed> */
    private static function revisionPayload(int $version): array
    {
        return [
            'title' => 'Landing',
            'slug' => 'landing',
            'blocks' => [self::BLOCK],
            'seo' => PageSeo::defaults()->toArray(),
            'layout' => PageLayout::defaults()->toArray(),
            'version' => $version,
        ];
    }

    /** @param array<string, mixed> $body */
    private static function request(array $body, string $method = 'PUT'): Request
    {
        return Request::create('/', $method, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private static function payload(JsonResponse $response): array
    {
        $result = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        /** @var array<string, mixed> $result */
        return $result;
    }
}
