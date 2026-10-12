<?php

declare(strict_types=1);

namespace Cms\Tests\Extensions\Application;

use Cms\Extensions\Application\ExtensionManifest;
use Cms\Extensions\Application\PagePublishedContext;
use Cms\Extensions\Application\PagePublishedSubscriber;
use Cms\Extensions\Infrastructure\CoreExtension;
use Cms\Extensions\Infrastructure\RegisteredExtensionCatalog;
use Cms\Extensions\Infrastructure\SynchronousPagePublishedHookBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ExtensionRuntimeTest extends TestCase
{
    public function test_it_lists_a_compatible_container_registered_extension(): void
    {
        $catalog = new RegisteredExtensionCatalog([new CoreExtension()]);
        $manifests = $catalog->all();

        self::assertCount(1, $manifests);
        self::assertSame('cms.morfira.core-observer', $manifests[0]->id);
        self::assertSame(['page.published.observe'], $manifests[0]->capabilities);
    }

    public function test_it_rejects_unknown_capabilities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ExtensionManifest('vendor.invalid.extension', 'Invalid', '1.0.0', 'Vendor', 1, ['php.eval']);
    }

    public function test_it_isolates_subscriber_failures_and_continues_dispatching(): void
    {
        $successfulCalls = 0;
        $failing = new class implements PagePublishedSubscriber {
            public function onPagePublished(PagePublishedContext $context): void
            {
                throw new \RuntimeException('Sensitive extension details.');
            }
        };
        $successful = new class (static function () use (&$successfulCalls): void {
            ++$successfulCalls;
        }) implements PagePublishedSubscriber {
            public function __construct(private readonly \Closure $callback)
            {
            }

            public function onPagePublished(PagePublishedContext $context): void
            {
                ($this->callback)();
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
            public array $records = [];

            /** @param array<string, mixed> $context */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };

        (new SynchronousPagePublishedHookBus([$failing, $successful], $logger))->dispatch(
            new PagePublishedContext('page-id', 'snapshot-id', 3, 'landing'),
        );

        self::assertSame(1, $successfulCalls);
        self::assertCount(1, $logger->records);
        self::assertSame(\RuntimeException::class, $logger->records[0]['context']['exception']);
        self::assertStringNotContainsString('Sensitive extension details.', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }
}
