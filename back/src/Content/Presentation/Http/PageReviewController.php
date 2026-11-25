<?php

declare(strict_types=1);

namespace Cms\Content\Presentation\Http;

use Cms\Content\Application\ManagePageReviews;
use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageReviewConflict;
use Cms\Content\Domain\PageReviewStatus;
use Cms\Content\Domain\PageRevisionNotFound;
use Cms\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PageReviewController
{
    public function __construct(private ManagePageReviews $reviews, private Security $security)
    {
    }

    #[Route('/api/v1/admin/page-reviews', name: 'content.page_reviews.list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $limit = filter_var($query['limit'] ?? 100, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $status = $query['status'] ?? null;
        $pageId = $query['pageId'] ?? null;
        if (!is_int($limit) || ($status !== null && !is_string($status)) || ($pageId !== null && !is_string($pageId))) {
            return new JsonResponse(['error' => 'Invalid page review filters.'], 422);
        }

        try {
            $filter = $status === null ? null : PageReviewStatus::tryFrom($status) ?? throw new \InvalidArgumentException('Invalid page review status.');
            $id = $pageId === null ? null : PageId::fromString($pageId);

            return new JsonResponse(['items' => $this->reviews->list($filter, $id, $limit)]);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/pages/{id}/reviews', name: 'content.page_reviews.submit', methods: ['POST'])]
    public function submit(string $id, Request $request): JsonResponse
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        try {
            $payload = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            if (!$payload instanceof \stdClass || count(get_object_vars($payload)) !== 1
                || !is_int($revision = $payload->revision ?? null) || $revision < 1) {
                throw new \InvalidArgumentException('A positive page revision is required.');
            }

            $result = $this->reviews->submit(PageId::fromString($id), $revision, $actor->id);

            return new JsonResponse($result['review'], $result['created'] ? 201 : 200);
        } catch (PageRevisionNotFound $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/api/v1/admin/page-reviews/{id}', name: 'content.page_reviews.decide', methods: ['PATCH'])]
    public function decide(string $id, Request $request): JsonResponse
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof SecurityUser) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }
        if (!$this->security->isGranted('ROLE_OWNER')) {
            return new JsonResponse(['error' => 'Only owners may decide page reviews.'], 403);
        }

        try {
            $payload = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            if (!$payload instanceof \stdClass || array_diff(array_keys(get_object_vars($payload)), ['status', 'comment']) !== []
                || !is_string($status = $payload->status ?? null)
                || (($comment = $payload->comment ?? null) !== null && !is_string($comment))) {
                throw new \InvalidArgumentException('A review decision and optional comment are required.');
            }
            $decision = PageReviewStatus::tryFrom($status);
            if ($decision === null || $decision === PageReviewStatus::Pending) {
                throw new \InvalidArgumentException('Select a review decision.');
            }

            return new JsonResponse($this->reviews->decide($id, $decision, $comment, $actor->id));
        } catch (PageReviewConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\OutOfBoundsException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 404);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }
}
