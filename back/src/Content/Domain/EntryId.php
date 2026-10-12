<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class EntryId
{
    private function __construct(public string $value)
    {
    }

    public static function new(): self
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 15) | 64);
        $b[8] = chr((ord($b[8]) & 63) | 128);
        $h = bin2hex($b);

        return new self(sprintf('%s-%s-%s-%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 12, 4), substr($h, 16, 4), substr($h, 20)));
    }

    public static function fromString(string $v): self
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $v)) {
            throw new \InvalidArgumentException('Invalid entry identifier.');
        }

        return new self($v);
    }
}
