<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Content\Domain\PageId;
use Cms\Content\Domain\PageNotFound;
use Cms\Content\Domain\PageRepository;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationScheduleStatus;
use Cms\Publishing\Domain\PublicationTarget;
use Cms\Publishing\Domain\PublishedPageRepository;

final readonly class UnpublishPage
{
    public function __construct(
        private PageRepository $pages,
        private PublishedPageRepository $published,
        private PublicationScheduleRepository $schedules,
    ) {
    }

    public function __invoke(string $pageId): void
    {
        $id = PageId::fromString($pageId);
        if ($this->pages->get($id) === null) {
            throw new PageNotFound();
        }

        $this->published->unpublish($id->value);
        $schedule = $this->schedules->find(PublicationTarget::Page, $id->value);
        if ($schedule?->status === PublicationScheduleStatus::Pending) {
            $this->schedules->save($schedule->cancel(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))));
        }
    }
}
