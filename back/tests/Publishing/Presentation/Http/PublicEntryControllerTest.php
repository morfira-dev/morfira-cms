<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Presentation\Http;

use Cms\Content\Application\CreateEntry;
use Cms\Publishing\Application\GetPublishedEntry;
use Cms\Publishing\Application\ListPublishedEntries;
use Cms\Publishing\Application\PublishEntry;
use Cms\Publishing\Presentation\Http\PublicEntryController;
use Cms\Schema\Application\CreateContentType;
use Cms\Schema\Application\GetContentTypeDefinition;
use Cms\Schema\Application\ValidateEntryValues;
use Cms\Tests\Content\InMemoryEntryRepository;
use Cms\Tests\Publishing\InMemoryPublishedEntryRepository;
use Cms\Tests\Schema\InMemoryContentTypeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class PublicEntryControllerTest extends TestCase
{
    #[Test]
    public function it_renders_only_a_published_snapshot_with_escaped_values_and_resolved_media(): void
    {
        $types = new InMemoryContentTypeRepository();
        $definitions = new GetContentTypeDefinition($types);
        $validator = new ValidateEntryValues($definitions);
        $type = (new CreateContentType($types))('Article', 'articles', [
            ['id' => '11111111-1111-4111-8111-111111111111', 'key' => 'body', 'label' => 'Body', 'type' => 'textarea', 'required' => true],
            ['id' => '22222222-2222-4222-8222-222222222222', 'key' => 'hero', 'label' => 'Hero', 'type' => 'media', 'required' => true],
        ]);
        $entries = new InMemoryEntryRepository();
        $draft = (new CreateEntry($entries, $validator))(
            $type->id,
            '<script>Title</script>',
            'safe-entry',
            ['body' => '<script>alert(1)</script>', 'hero' => '33333333-3333-4333-8333-333333333333'],
        );
        $published = new InMemoryPublishedEntryRepository();
        (new PublishEntry($entries, $published, $definitions, $validator))($draft->id, 1);

        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 4).'/templates'), ['autoescape' => 'html']);
        $twig->addFunction(new TwigFunction('media_asset', static fn (string $id): array => [
            'width' => 800,
            'height' => 450,
            'defaultAlt' => 'Safe image',
            'variants' => [['url' => '/media/'.$id.'/800w.webp', 'width' => 800, 'height' => 450]],
        ]));
        $controller = new PublicEntryController(new GetPublishedEntry($published), new ListPublishedEntries($published), $twig);

        $response = $controller->get('articles', 'safe-entry', Request::create('/content/articles/safe-entry'));
        $html = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('/media/33333333-3333-4333-8333-333333333333/800w.webp', $html);
        self::assertNotNull($response->getEtag());
    }

    #[Test]
    public function draft_entries_are_not_rendered(): void
    {
        $types = new InMemoryContentTypeRepository();
        $definitions = new GetContentTypeDefinition($types);
        (new CreateContentType($types))('Article', 'articles', []);
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 4).'/templates'), ['autoescape' => 'html']);
        $published = new InMemoryPublishedEntryRepository();
        $controller = new PublicEntryController(new GetPublishedEntry($published), new ListPublishedEntries($published), $twig);

        self::assertSame(404, $controller->get('articles', 'draft', Request::create('/content/articles/draft'))->getStatusCode());
    }

    #[Test]
    public function taxonomy_archives_render_immutable_filtered_entries(): void
    {
        $published = new InMemoryPublishedEntryRepository();
        $published->publish(new \Cms\Publishing\Domain\PublishedEntry('11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222',1,['id'=>'33333333-3333-4333-8333-333333333333','name'=>'Article','apiName'=>'articles','version'=>1,'fields'=>[]],'First','first',[],[['taxonomyId'=>'44444444-4444-4444-8444-444444444444','taxonomySlug'=>'topics','termId'=>'55555555-5555-4555-8555-555555555555','termSlug'=>'php','name'=>'PHP']],new \DateTimeImmutable('2026-10-12T12:00:00+00:00')));
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 4).'/templates'), ['autoescape' => 'html']);
        $controller = new PublicEntryController(new GetPublishedEntry($published), new ListPublishedEntries($published), $twig);

        $response = $controller->archive('articles','topics','php',Request::create('/content/articles/taxonomy/topics/php'));

        self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('First',(string)$response->getContent());self::assertNotNull($response->getEtag());
        self::assertSame(404,$controller->archive('articles','topics','missing',Request::create('/content/articles/taxonomy/topics/missing'))->getStatusCode());
        self::assertSame(404,$controller->archive('articles','topics','php',Request::create('/content/articles/taxonomy/topics/php?page=2'))->getStatusCode());
        self::assertSame(422,$controller->archive('articles','topics','php',Request::create('/content/articles/taxonomy/topics/php?page=zero'))->getStatusCode());
    }
}
