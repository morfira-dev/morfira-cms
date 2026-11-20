<?php

declare(strict_types=1);

namespace Cms\Media\Application;

final class FindMediaReference
{
    public static function inPayload(mixed $value, string $assetId): bool
    {
        if (is_string($value)) {
            return $value === $assetId || (str_contains($value, '/media/'.$assetId.'/')
                && preg_match('~<img\b[^>]*\ssrc="(?:https://[^/"\s]+)?/media/'.preg_quote($assetId, '~').'/[0-9]+w\.webp"~i', $value) === 1);
        }

        return is_array($value) && array_any($value, static fn (mixed $nested): bool => self::inPayload($nested, $assetId));
    }
}
