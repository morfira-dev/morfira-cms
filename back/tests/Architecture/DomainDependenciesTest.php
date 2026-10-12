<?php

declare(strict_types=1);

namespace Cms\Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

final class DomainDependenciesTest extends TestCase
{
    #[Test]
    public function domain_code_does_not_depend_on_frameworks(): void
    {
        $sourceDirectory = dirname(__DIR__, 2) . '/src';
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceDirectory),
            ),
            '/^.+\/Domain\/.+\.php$/i',
        );

        foreach ($iterator as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);

            $contents = file_get_contents($file->getPathname());

            self::assertIsString($contents);
            self::assertDoesNotMatchRegularExpression(
                '/^use (Symfony|Doctrine)\\\\/m',
                $contents,
                sprintf('Domain file %s depends on infrastructure.', $file->getPathname()),
            );
        }

        $this->addToAssertionCount(1);
    }
}
