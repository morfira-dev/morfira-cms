<?php

declare(strict_types=1);

namespace Cms\Tests\Publishing\Application;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Content\Domain\PageLayout;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Infrastructure\Html\SymfonyRichTextSanitizer;
use Cms\Publishing\Application\GetPublishedPage\GetPublishedPage;
use Cms\Publishing\Application\GetPublishedRedirect\GetPublishedRedirect;
use Cms\Publishing\Application\ListPageRedirects\ListPageRedirects;
use Cms\Publishing\Application\PublishPage\PublishPage;
use Cms\Publishing\Application\AddPageRedirect;
use Cms\Publishing\Application\RemovePageRedirect;
use Cms\Publishing\Domain\PublishedSlugAlreadyExists;
use Cms\Publishing\Domain\PublishedPageNotActive;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Cms\Tests\Publishing\InMemoryPublishedPageRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PublishPageTest extends TestCase
{
    #[Test]
    public function it_publishes_an_immutable_snapshot_of_the_requested_revision(): void
    {
        $drafts = new InMemoryPageRepository();
        $delivery = new InMemoryPublishedPageRepository();
        $created = (new CreatePage($drafts))('Landing', 'landing');
        $draft = (new UpdatePage($drafts, new PassThroughRichTextSanitizer()))(
            $created->id,
            'Landing',
            'landing',
            [['id' => 'cf49b538-07ee-4b53-a6a3-6b25cbfa22ae', 'type' => 'hero', 'data' => ['heading' => 'Hello']]],
            new PageSeo('Landing SEO', 'An immutable description.', false, null),
            1,
            new PageLayout('morfira', 'wide', 'main', 'footer'),
        );

        $published = (new PublishPage($drafts, $delivery))($draft->id, 2);
        (new UpdatePage($drafts, new PassThroughRichTextSanitizer()))($draft->id, 'Changed draft', 'landing', [], PageSeo::defaults(), 2);
        $publicPage = (new GetPublishedPage($delivery))('landing');

        self::assertSame(2, $published->sourceRevision);
        self::assertNotNull($publicPage);
        self::assertSame('Landing', $publicPage->title);
        self::assertCount(1, $publicPage->blocks);
        self::assertSame('Landing SEO', $publicPage->seo['title']);
        self::assertSame(['theme' => 'morfira', 'template' => 'wide', 'headerNavigationCode' => 'main', 'footerNavigationCode' => 'footer'], $publicPage->layout);
    }

    #[Test]
    public function it_rejects_publishing_a_stale_editor_version(): void
    {
        $drafts = new InMemoryPageRepository();
        $created = (new CreatePage($drafts))('Landing', 'landing');
        (new UpdatePage($drafts, new PassThroughRichTextSanitizer()))($created->id, 'Changed', 'landing', [], PageSeo::defaults(), 1);

        $this->expectException(PageVersionConflict::class);

        (new PublishPage($drafts, new InMemoryPublishedPageRepository()))($created->id, 1);
    }

    #[Test]
    public function publishing_the_same_revision_is_idempotent(): void
    {
        $drafts = new InMemoryPageRepository();
        $delivery = new InMemoryPublishedPageRepository();
        $created = (new CreatePage($drafts))('Landing', 'landing');
        $publish = new PublishPage($drafts, $delivery);

        $first = $publish($created->id, 1);
        $second = $publish($created->id, 1);

        self::assertSame($first->snapshotId, $second->snapshotId);
    }

    #[Test]
    public function it_preserves_sanitized_rich_text_in_the_published_snapshot(): void
    {
        $drafts = new InMemoryPageRepository();
        $delivery = new InMemoryPublishedPageRepository();
        $created = (new CreatePage($drafts))('Landing', 'landing');
        $updated = (new UpdatePage($drafts, new SymfonyRichTextSanitizer()))(
            $created->id,
            'Landing',
            'landing',
            [[
                'id' => '6bb4ff18-266f-44a7-a3d0-1b4d84ac0ac8',
                'type' => 'richText',
                'data' => ['content' => '<h2>Welcome</h2><p><strong>Safe</strong> copy.</p><script>alert(1)</script>'],
            ]],
            PageSeo::defaults(),
            $created->version,
        );

        $published = (new PublishPage($drafts, $delivery))($updated->id, $updated->version);

        self::assertSame('<h2>Welcome</h2><p><strong>Safe</strong> copy.</p>', $published->blocks[0]['data']['content']);
    }

    #[Test]
    public function changing_a_published_slug_creates_a_direct_permanent_route_to_the_current_page(): void
    {
        $drafts = new InMemoryPageRepository();
        $delivery = new InMemoryPublishedPageRepository();
        $created = (new CreatePage($drafts))('Landing', 'landing');
        $publish = new PublishPage($drafts, $delivery);
        $publish($created->id, 1);
        $renamed = (new UpdatePage($drafts, new PassThroughRichTextSanitizer()))(
            $created->id,
            'Landing',
            'better-landing',
            [],
            PageSeo::defaults(),
            1,
        );

        $publish($renamed->id, 2);

        $redirectTarget = (new GetPublishedRedirect($delivery))('landing');
        self::assertNotNull($redirectTarget);
        self::assertSame('better-landing', $redirectTarget->slug);
        self::assertNull((new GetPublishedPage($delivery))('landing'));
        self::assertSame(
            [['sourceSlug' => 'landing', 'targetSlug' => 'better-landing']],
            (new ListPageRedirects($delivery))($created->id),
        );
    }

    #[Test]
    public function another_page_cannot_claim_a_slug_reserved_by_redirect_history(): void
    {
        $drafts = new InMemoryPageRepository();
        $delivery = new InMemoryPublishedPageRepository();
        $first = (new CreatePage($drafts))('First', 'first');
        $publish = new PublishPage($drafts, $delivery);
        $publish($first->id, 1);
        $renamed = (new UpdatePage($drafts, new PassThroughRichTextSanitizer()))($first->id, 'First', 'renamed', [], PageSeo::defaults(), 1);
        $publish($renamed->id, 2);
        $second = (new CreatePage($drafts))('Second', 'first');

        $this->expectException(PublishedSlugAlreadyExists::class);
        $publish($second->id, 1);
    }

    #[Test]
    public function editors_can_add_and_remove_manual_redirects_for_an_active_page():void
    {
        $drafts=new InMemoryPageRepository();$delivery=new InMemoryPublishedPageRepository();$page=(new CreatePage($drafts))('Landing','landing');(new PublishPage($drafts,$delivery))($page->id,1);
        $redirect=(new AddPageRedirect($drafts,$delivery))($page->id,'campaign');
        self::assertSame(['sourceSlug'=>'campaign','targetSlug'=>'landing'],$redirect);self::assertNotNull((new GetPublishedRedirect($delivery))('campaign'));
        (new RemovePageRedirect($drafts,$delivery))($page->id,'campaign');
        self::assertNull((new GetPublishedRedirect($delivery))('campaign'));
    }

    #[Test]
    public function manual_redirects_require_an_active_page():void
    {
        $drafts=new InMemoryPageRepository();$delivery=new InMemoryPublishedPageRepository();$page=(new CreatePage($drafts))('Landing','landing');
        $this->expectException(PublishedPageNotActive::class);
        (new AddPageRedirect($drafts,$delivery))($page->id,'campaign');
    }

    #[Test]
    public function another_page_cannot_remove_a_redirect_it_does_not_own():void
    {
        $drafts=new InMemoryPageRepository();$delivery=new InMemoryPublishedPageRepository();$publish=new PublishPage($drafts,$delivery);
        $first=(new CreatePage($drafts))('First','first');$second=(new CreatePage($drafts))('Second','second');$publish($first->id,1);$publish($second->id,1);
        (new AddPageRedirect($drafts,$delivery))($first->id,'campaign');
        (new RemovePageRedirect($drafts,$delivery))($second->id,'campaign');
        self::assertNotNull((new GetPublishedRedirect($delivery))('campaign'));
    }
}
