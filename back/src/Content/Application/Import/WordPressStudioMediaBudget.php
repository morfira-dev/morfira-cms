<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

final class WordPressStudioMediaBudget
{
    private const int MAX_TRANSFERS = 8;
    private const int MAX_DURATION_NS = 20_000_000_000;

    private int $transfers = 0;
    private ?int $deadlineNs = null;

    public function reserveTransfer(int $nowNs): void
    {
        if ($this->transfers >= self::MAX_TRANSFERS) {
            throw new WordPressRemoteMediaFailure('The WordPress Studio attachment transfer limit was reached.');
        }

        $this->deadlineNs ??= $nowNs + self::MAX_DURATION_NS;
        $this->remainingTimeoutMs($nowNs);
        ++$this->transfers;
    }

    public function remainingTimeoutMs(int $nowNs): int
    {
        if ($this->deadlineNs === null) {
            throw new \LogicException('Reserve a WordPress attachment transfer before reading its deadline.');
        }

        $remainingMs = intdiv($this->deadlineNs - $nowNs, 1_000_000);
        if ($remainingMs < 1) {
            throw new WordPressRemoteMediaFailure('The WordPress Studio attachment transfer time limit was reached.');
        }

        return $remainingMs;
    }
}
