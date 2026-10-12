<?php

declare(strict_types=1);

namespace Cms\Publishing\Presentation\Http;

use Cms\Content\Domain\EntryNotFound;
use Cms\Content\Domain\EntryVersionConflict;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageVersionConflict;
use Cms\Publishing\Application\CancelPublicationSchedule;
use Cms\Publishing\Application\GetPublicationSchedule;
use Cms\Publishing\Application\PlanEntryPublication;
use Cms\Publishing\Application\PlanPagePublication;
use Cms\Publishing\Domain\PublicationTarget;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PublicationScheduleController
{
    public function __construct(
        private PlanPagePublication $planPage,
        private PlanEntryPublication $planEntry,
        private GetPublicationSchedule $getSchedule,
        private CancelPublicationSchedule $cancelSchedule,
    ) {
    }

    #[Route('/api/v1/admin/pages/{id}/schedule', name: 'publishing.pages.schedule.get', methods: ['GET'])]
    public function getPage(string $id): JsonResponse
    {
        return $this->get(PublicationTarget::Page, $id);
    }

    #[Route('/api/v1/admin/pages/{id}/schedule', name: 'publishing.pages.schedule.put', methods: ['PUT'])]
    public function putPage(string $id, Request $request): JsonResponse
    {
        return $this->put($id, $request, $this->planPage);
    }

    #[Route('/api/v1/admin/pages/{id}/schedule', name: 'publishing.pages.schedule.delete', methods: ['DELETE'])]
    public function deletePage(string $id): JsonResponse
    {
        return $this->delete(PublicationTarget::Page, $id);
    }

    #[Route('/api/v1/admin/entries/{id}/schedule', name: 'publishing.entries.schedule.get', methods: ['GET'])]
    public function getEntry(string $id): JsonResponse
    {
        return $this->get(PublicationTarget::Entry, $id);
    }

    #[Route('/api/v1/admin/entries/{id}/schedule', name: 'publishing.entries.schedule.put', methods: ['PUT'])]
    public function putEntry(string $id, Request $request): JsonResponse
    {
        return $this->put($id, $request, $this->planEntry);
    }

    #[Route('/api/v1/admin/entries/{id}/schedule', name: 'publishing.entries.schedule.delete', methods: ['DELETE'])]
    public function deleteEntry(string $id): JsonResponse
    {
        return $this->delete(PublicationTarget::Entry, $id);
    }

    private function get(PublicationTarget $target, string $id): JsonResponse
    {
        $schedule = ($this->getSchedule)($target, $id);

        return $schedule === null
            ? new JsonResponse(['error' => 'Publication schedule not found.'], 404)
            : new JsonResponse($schedule->toArray());
    }

    /** @param callable(string, int, \DateTimeImmutable): \Cms\Publishing\Application\PublicationScheduleView $planner */
    private function put(string $id, Request $request, callable $planner): JsonResponse
    {
        $payload = $request->toArray();
        $unexpected = array_diff(array_keys($payload), ['sourceVersion', 'scheduledAt']);
        $sourceVersion = $payload['sourceVersion'] ?? null;
        $scheduledAt = $payload['scheduledAt'] ?? null;
        if ($unexpected !== [] || !is_int($sourceVersion) || $sourceVersion < 1 || !is_string($scheduledAt)) {
            return new JsonResponse(['error' => 'A positive sourceVersion and RFC 3339 scheduledAt are required.'], 422);
        }

        try {
            $date = self::parseDate($scheduledAt);
            return new JsonResponse($planner($id, $sourceVersion, $date)->toArray());
        } catch (PageNotFound|EntryNotFound) {
            return new JsonResponse(['error' => 'Draft not found.'], 404);
        } catch (PageVersionConflict|EntryVersionConflict $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 422);
        }
    }

    private function delete(PublicationTarget $target, string $id): JsonResponse
    {
        try {
            $schedule = ($this->cancelSchedule)($target, $id);
        } catch (\DomainException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], 409);
        }

        return $schedule === null
            ? new JsonResponse(['error' => 'Publication schedule not found.'], 404)
            : new JsonResponse($schedule->toArray());
    }

    private static function parseDate(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(DATE_RFC3339_EXTENDED, $value)
            ?: \DateTimeImmutable::createFromFormat(DATE_RFC3339, $value);
        if ($date === false) {
            throw new \InvalidArgumentException('scheduledAt must be an RFC 3339 date-time with a timezone.');
        }

        return $date->setTimezone(new \DateTimeZone('UTC'));
    }
}
