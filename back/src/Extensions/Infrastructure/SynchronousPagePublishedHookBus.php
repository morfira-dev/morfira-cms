<?php

declare(strict_types=1);

namespace Cms\Extensions\Infrastructure;

use Cms\Extensions\Application\PagePublishedContext;
use Cms\Extensions\Application\PagePublishedHookBus;
use Cms\Extensions\Application\PagePublishedSubscriber;
use Psr\Log\LoggerInterface;

final readonly class SynchronousPagePublishedHookBus implements PagePublishedHookBus
{
    /** @param iterable<PagePublishedSubscriber> $subscribers */
    public function __construct(
        private iterable $subscribers,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(PagePublishedContext $context): void
    {
        foreach ($this->subscribers as $subscriber) {
            try {
                $subscriber->onPagePublished($context);
            } catch (\Throwable $exception) {
                $this->logger->error('A page-published extension hook failed.', [
                    'subscriber' => $subscriber::class,
                    'pageId' => $context->pageId,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
