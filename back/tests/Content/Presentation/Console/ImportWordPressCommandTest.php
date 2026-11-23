<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Presentation\Console;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportWordPressCommandTest extends KernelTestCase
{
    /** @param array<string,string|bool> $arguments */
    #[DataProvider('incompatibleHistoryArguments')]
    public function testRejectsIncompatibleHistoryOptionsBeforeReadingTheSource(array $arguments, string $message): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('cms:import:wordpress'));

        $status = $tester->execute($arguments + ['--source' => 'legacy', '--record-history' => true], ['capture_stderr_separately' => true]);

        self::assertSame(Command::INVALID, $status);
        self::assertSame($message."\n", $tester->getErrorOutput(true));
        self::assertSame('', $tester->getDisplay());
    }

    /** @return iterable<string,array{array<string,string|bool>,string}> */
    public static function incompatibleHistoryArguments(): iterable
    {
        yield 'dry-run' => [
            ['file' => '/unavailable-export.xml'],
            '--record-history requires --apply.',
        ];
        yield 'private REST' => [
            ['--api-url' => 'https://source.example.test/wp-json/wp/v2', '--include-private' => true, '--apply' => true],
            '--record-history cannot be combined with --include-private.',
        ];
    }
}
