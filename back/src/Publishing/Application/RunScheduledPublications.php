<?php

declare(strict_types=1);

namespace Cms\Publishing\Application;

use Cms\Publishing\Application\PublishPage\PublishPage;
use Cms\Publishing\Domain\PublicationScheduleRepository;
use Cms\Publishing\Domain\PublicationTarget;

final readonly class RunScheduledPublications
{
    public function __construct(private PublicationScheduleRepository $schedules, private PublishPage $publishPage, private PublishEntry $publishEntry)
    {
    }

    /** @return array{processed: int, completed: int, failed: int} */
    public function __invoke(int $limit): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new \InvalidArgumentException('The processing limit must be between 1 and 1000.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $result = ['processed' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($this->schedules->due($now, $limit) as $schedule) {
            ++$result['processed'];
            try {
                if ($schedule->target === PublicationTarget::Page) {
                    ($this->publishPage)($schedule->targetId, $schedule->sourceVersion);
                } else {
                    ($this->publishEntry)($schedule->targetId, $schedule->sourceVersion);
                }
                $this->schedules->save($schedule->complete($now));
                ++$result['completed'];
            } catch (\Throwable $exception) {
                $this->schedules->save($schedule->fail($exception->getMessage(), $now));
                ++$result['failed'];
            }
        }

        return $result;
    }
}
