<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\WordPressRemoteMediaFailure;
use Cms\Content\Application\Import\WordPressStudioMediaBudget;
use PHPUnit\Framework\TestCase;

final class WordPressStudioMediaBudgetTest extends TestCase
{
    public function testEightNewTransfersShareOneDeadline(): void
    {
        $budget = new WordPressStudioMediaBudget();
        $start = 40_000_000_000;
        for ($index = 0; $index < 8; ++$index) {
            $now = $start + $index * 1_000_000_000;
            $budget->reserveTransfer($now);
            self::assertSame(20_000 - 1_000 * $index, $budget->remainingTimeoutMs($now));
        }

        $this->expectException(WordPressRemoteMediaFailure::class);
        $this->expectExceptionMessage('attachment transfer limit');
        $budget->reserveTransfer($start + 8_000_000_000);
    }

    public function testNoZeroTimeoutIsReturnedWhenLessThanOneMillisecondRemains(): void
    {
        $budget = new WordPressStudioMediaBudget();
        $budget->reserveTransfer(40_000_000_000);
        self::assertSame(1, $budget->remainingTimeoutMs(59_999_000_000));

        $this->expectException(WordPressRemoteMediaFailure::class);
        $this->expectExceptionMessage('attachment transfer time limit');
        $budget->remainingTimeoutMs(59_999_999_999);
    }
}
