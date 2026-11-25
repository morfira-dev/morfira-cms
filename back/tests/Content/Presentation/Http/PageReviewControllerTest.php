<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Http;

use Cms\Content\Application\CreatePage\CreatePage;
use Cms\Content\Application\GetPage\GetPage;
use Cms\Content\Application\ManagePageReviews;
use Cms\Content\Application\UpdatePage\UpdatePage;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageReview;
use Cms\Content\Domain\PageReviewStatus;
use Cms\Content\Domain\PageSeo;
use Cms\Content\Infrastructure\Persistence\DoctrinePageReviewRepository;
use Cms\Content\Infrastructure\Twig\RichTextExtension;
use Cms\Content\Presentation\Http\PagePreviewController;
use Cms\Content\Presentation\Http\PageReviewController;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Cms\Tests\Content\InMemoryPageRepository;
use Cms\Tests\Content\PassThroughRichTextSanitizer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

#[RequiresPhpExtension('pdo_sqlite')]
final class PageReviewControllerTest extends TestCase
{
    private const EDITOR_ID = '33333333-3333-4333-8333-333333333333';
    private const OWNER_ID = '44444444-4444-4444-8444-444444444444';

    private Connection $connection;
    private DoctrinePageReviewRepository $repository;
    private InMemoryPageRepository $pages;
    private PageId $pageId;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        $this->connection->executeStatement('CREATE TABLE content_page_revisions (page_id TEXT NOT NULL, revision_number INT NOT NULL, PRIMARY KEY (page_id, revision_number))');
        $this->connection->executeStatement(<<<'SQL'
CREATE TABLE content_page_reviews (
    id TEXT PRIMARY KEY,
    page_id TEXT NOT NULL,
    revision_number INT NOT NULL,
    page_title TEXT NOT NULL,
    page_slug TEXT NOT NULL,
    locale TEXT NOT NULL,
    status TEXT NOT NULL,
    submitted_by TEXT NOT NULL,
    submitted_at TEXT NOT NULL,
    decided_by TEXT,
    decided_at TEXT,
    comment TEXT,
    UNIQUE (page_id, revision_number),
    FOREIGN KEY (page_id, revision_number) REFERENCES content_page_revisions (page_id, revision_number) ON DELETE CASCADE
)
SQL);
        $this->repository = new DoctrinePageReviewRepository($this->connection);
        $this->pages = new InMemoryPageRepository();
        $created = (new CreatePage($this->pages))('Original title', 'original', 'pt-BR');
        $this->pageId = PageId::fromString($created->id);
        (new UpdatePage($this->pages, new PassThroughRichTextSanitizer()))(
            $created->id,
            'New title',
            'new-title',
            [['id' => PageId::new()->value, 'type' => 'hero', 'data' => ['heading' => 'Reviewed heading']]],
            PageSeo::defaults(),
            1,
        );
        $this->connection->insert('content_page_revisions', ['page_id' => $created->id, 'revision_number' => 1]);
        $this->connection->insert('content_page_revisions', ['page_id' => $created->id, 'revision_number' => 2]);
    }

    public function test_review_is_bound_to_the_submitted_revision_and_idempotent(): void
    {
        $editor = $this->controller(owner: false);
        $submitted = $editor->submit($this->pageId->value, self::request(['revision' => 1]));
        self::assertSame(201, $submitted->getStatusCode());
        $review = self::body($submitted);
        self::assertSame('Original title', $review['pageTitle']);
        self::assertSame('original', $review['pageSlug']);
        self::assertSame('pt-BR', $review['locale']);
        self::assertSame(1, $review['revision']);
        self::assertSame('pending', $review['status']);
        self::assertSame(self::EDITOR_ID, $review['submittedBy']);

        $retry = $this->controller(owner: true)->submit($this->pageId->value, self::request(['revision' => 1]));
        self::assertSame(200, $retry->getStatusCode());
        self::assertSame($review, self::body($retry));
        self::assertSame(404, $editor->submit($this->pageId->value, self::request(['revision' => 3]))->getStatusCode());
        self::assertSame(422, $editor->submit($this->pageId->value, self::request(['revision' => 1, 'actor' => self::OWNER_ID]))->getStatusCode());

        $listed = $editor->list(Request::create('/?status=pending&pageId=' . $this->pageId->value . '&limit=1'));
        self::assertSame([$review], self::body($listed)['items']);
        self::assertSame([], self::body($editor->list(Request::create('/?status=approved')))['items']);
        self::assertSame(422, $editor->list(Request::create('/?limit=101'))->getStatusCode());

        $newRevisionReview = $editor->submit($this->pageId->value, self::request(['revision' => 2]));
        self::assertSame(201, $newRevisionReview->getStatusCode());
        self::assertSame('New title', self::body($newRevisionReview)['pageTitle']);
    }

    public function test_private_review_preview_renders_the_exact_revision_after_draft_changes(): void
    {
        $review = self::body($this->controller(owner: false)->submit($this->pageId->value, self::request(['revision' => 2])));
        self::assertIsString($id = $review['id']);
        (new UpdatePage($this->pages, new PassThroughRichTextSanitizer()))(
            $this->pageId->value, 'Later title', 'later-title', [], PageSeo::defaults(), 2, locale: 'en',
        );
        $preview = $this->previewController();

        $response = $preview->review($id);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $body = $response->getContent();
        self::assertIsString($body);
        self::assertStringContainsString('<html lang="pt-BR">', $body);
        self::assertStringContainsString('Review preview · revision 2', $body);
        self::assertStringContainsString('<h1>Reviewed heading</h1>', $body);
        self::assertStringNotContainsString('Later title', $body);

        $draft = $preview($this->pageId->value)->getContent();
        self::assertIsString($draft);
        self::assertStringContainsString('Draft preview · revision 3', $draft);
        self::assertStringContainsString('Later title', $draft);
        self::assertStringNotContainsString('Reviewed heading', $draft);
    }

    public function test_invalid_unknown_and_deleted_review_previews_are_private_not_found(): void
    {
        $review = self::body($this->controller(owner: false)->submit($this->pageId->value, self::request(['revision' => 1])));
        self::assertIsString($id = $review['id']);
        $this->connection->delete('content_page_revisions', ['page_id' => $this->pageId->value, 'revision_number' => 1]);
        $preview = $this->previewController();

        foreach (['malformed', PageId::new()->value, $id] as $unavailable) {
            $response = $preview->review($unavailable);
            self::assertSame(404, $response->getStatusCode());
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
            self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        }
    }

    public function test_owner_can_decide_once_and_feedback_is_required_for_changes(): void
    {
        $editor = $this->controller(owner: false);
        $review = self::body($editor->submit($this->pageId->value, self::request(['revision' => 1])));
        $id = $review['id'];
        self::assertIsString($id);

        self::assertSame(403, $editor->decide($id, self::request(['status' => 'approved'], 'PATCH'))->getStatusCode());
        $owner = $this->controller(owner: true);
        self::assertSame(422, $owner->decide($id, self::request(['status' => 'changes_requested', 'comment' => " \u{00a0} "], 'PATCH'))->getStatusCode());
        self::assertSame(422, $owner->decide($id, self::request(['status' => 'approved', 'comment' => str_repeat('é', 1001)], 'PATCH'))->getStatusCode());
        self::assertSame(422, $owner->decide($id, self::request(['status' => 'approved', 'comment' => str_repeat(' ', 1001)], 'PATCH'))->getStatusCode());
        self::assertSame(404, $owner->decide(PageId::new()->value, self::request(['status' => 'approved'], 'PATCH'))->getStatusCode());

        $decided = $owner->decide($id, self::request(['status' => 'changes_requested', 'comment' => '  Please update the heading.  '], 'PATCH'));
        self::assertSame(200, $decided->getStatusCode());
        self::assertSame('changes_requested', self::body($decided)['status']);
        self::assertSame(self::OWNER_ID, self::body($decided)['decidedBy']);
        self::assertSame('Please update the heading.', self::body($decided)['comment']);
        self::assertSame(409, $owner->decide($id, self::request(['status' => 'approved'], 'PATCH'))->getStatusCode());
    }

    public function test_repository_unique_key_and_decision_write_handle_concurrent_attempts(): void
    {
        $revision = $this->pages->revision($this->pageId, 1);
        self::assertNotNull($revision);
        $now = new \DateTimeImmutable('2026-11-25T12:00:00Z');
        $first = PageReview::submit($this->pageId, $revision, self::EDITOR_ID, $now);
        $second = PageReview::submit($this->pageId, $revision, self::OWNER_ID, $now);
        self::assertSame($first->id, $this->repository->add($first)->id);
        self::assertSame($first->id, $this->repository->add($second)->id);
        self::assertTrue($this->repository->saveDecision($first->decide(PageReviewStatus::Approved, null, self::OWNER_ID, $now)));
        self::assertFalse($this->repository->saveDecision($first->decide(PageReviewStatus::ChangesRequested, 'Needs changes.', self::OWNER_ID, $now)));
        self::assertSame(PageReviewStatus::Approved, $this->repository->get($first->id)?->status);

        $revisionTwo = $this->pages->revision($this->pageId, 2);
        self::assertNotNull($revisionTwo);
        $newest = $this->repository->add(PageReview::submit($this->pageId, $revisionTwo, self::EDITOR_ID, $now->modify('+1 second')));
        self::assertSame([$newest->id], array_map(
            static fn (PageReview $review): string => $review->id,
            $this->repository->list(null, $this->pageId, 1),
        ));

        $this->connection->delete('content_page_revisions', ['page_id' => $this->pageId->value, 'revision_number' => 1]);
        self::assertNull($this->repository->get($first->id));
        self::assertNotNull($this->repository->get($newest->id));
    }

    private function controller(bool $owner): PageReviewController
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new SecurityUser(
            $owner ? self::OWNER_ID : self::EDITOR_ID,
            $owner ? 'owner@example.test' : 'editor@example.test',
            '',
            $owner ? ['ROLE_OWNER', 'ROLE_EDITOR'] : ['ROLE_EDITOR'],
        ));
        $security->method('isGranted')->willReturn($owner);

        return new PageReviewController(new ManagePageReviews($this->repository, $this->pages), $security);
    }

    private function previewController(): PagePreviewController
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 4) . '/templates'), ['strict_variables' => true]);
        $twig->addExtension(new RichTextExtension(new PassThroughRichTextSanitizer()));
        $twig->addFunction(new TwigFunction('media_asset', static fn (string $id): null => null));

        return new PagePreviewController(
            new GetPage($this->pages),
            new ManagePageReviews($this->repository, $this->pages),
            $twig,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function request(array $payload, string $method = 'POST'): Request
    {
        return Request::create('/', $method, content: json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private static function body(JsonResponse $response): array
    {
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
