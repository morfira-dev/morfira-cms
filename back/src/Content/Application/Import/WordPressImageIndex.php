<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Domain\PageId;

/**
 * @phpstan-import-type Attachment from ImportWordPressMedia
 * @phpstan-import-type MediaOutcome from ImportWordPressMedia
 * @phpstan-type ImageReference array{assetId:string,alt:string}
 */
final class WordPressImageIndex
{
    /** @var array<string,ImageReference|null> */
    private array $byId = [];

    /** @var array<string,ImageReference|false> */
    private array $byUrl = [];

    /**
     * @param list<Attachment> $attachments
     * @param list<MediaOutcome> $outcomes
     * @param \Closure(string): ?ImageReference $reuse
     */
    public function __construct(array $attachments, array $outcomes, private readonly \Closure $reuse)
    {
        $assets = [];
        foreach ($outcomes as $outcome) {
            $id = $outcome['assetId'] ?? ($outcome['state'] === 'planned' ? PageId::new()->value : null);
            if ($id !== null) {
                $assets[$outcome['id']] = $id;
            }
        }
        foreach ($attachments as $attachment) {
            $image = isset($assets[$attachment['id']]) ? ['assetId' => $assets[$attachment['id']], 'alt' => $attachment['alt']] : null;
            $this->byId[$attachment['id']] = $image;
            $key = self::urlKey($attachment['url']);
            if ($key !== null) {
                $this->register($key, $image);
                $relative = parse_url($key, PHP_URL_PATH);
                if (is_string($relative) && $relative !== $key) {
                    $this->register($relative, $image);
                }
            }
        }
    }

    /** @return ?ImageReference */
    public function byId(?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        if (!array_key_exists($id, $this->byId)) {
            $this->byId[$id] = ($this->reuse)($id);
        }

        return $this->byId[$id];
    }

    /** @return ?ImageReference */
    public function resolve(string $url, ?string $wordpressId): ?array
    {
        if ($wordpressId !== null) {
            return $this->byId($wordpressId);
        }
        $key = self::urlKey($url);
        if ($key === null) {
            return null;
        }
        if (isset($this->byUrl[$key])) {
            $exact = $this->byUrl[$key];
            return $exact === false ? null : $exact;
        }
        $original = preg_replace('/-[0-9]+x[0-9]+(?=\.[a-zA-Z0-9]+$)/', '', $key) ?? $key;
        $image = $this->byUrl[$original] ?? false;

        return $image === false ? null : $image;
    }

    /** @param ?ImageReference $image */
    private function register(string $key, ?array $image): void
    {
        $this->byUrl[$key] = isset($this->byUrl[$key]) ? false : ($image ?? false);
    }

    private static function urlKey(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || !in_array(strtolower($parts['scheme'] ?? 'https'), ['http', 'https'], true)) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '');
        if (!str_starts_with($path, '/') || preg_match('/[\x00-\x20\x7f\\\\]/', $path) === 1
            || array_intersect(explode('/', $path), ['.', '..']) !== []) {
            return null;
        }

        return isset($parts['host']) ? '//'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '').$path : $path;
    }
}
