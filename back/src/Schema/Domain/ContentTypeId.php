<?php

declare(strict_types=1);

namespace Cms\Schema\Domain;

final readonly class ContentTypeId
{
    private function __construct(public string $value)
    {
    }

    public static function new(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);

        return new self(sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)));
    }

    public static function fromString(string $value): self
    {
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
            throw new \InvalidArgumentException('Invalid content type identifier.');
        }

        return new self($value);
    }
}
