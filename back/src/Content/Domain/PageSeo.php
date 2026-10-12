<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageSeo
{
    public function __construct(
        public string $title,
        public string $description,
        public bool $noIndex,
        public ?string $openGraphImageAssetId,
    ) {
        if (mb_strlen($title) > 70) {
            throw new \InvalidArgumentException('SEO title must not exceed 70 characters.');
        }

        if (mb_strlen($description) > 170) {
            throw new \InvalidArgumentException('SEO description must not exceed 170 characters.');
        }

        if ($openGraphImageAssetId !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $openGraphImageAssetId) !== 1) {
            throw new \InvalidArgumentException('Open Graph image must be a media asset UUID.');
        }
    }

    public static function defaults(): self
    {
        return new self('', '', false, null);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $title = $payload['title'] ?? null;
        $description = $payload['description'] ?? null;
        $noIndex = $payload['noIndex'] ?? null;
        $openGraphImageAssetId = $payload['openGraphImageAssetId'] ?? null;

        if (!is_string($title) || !is_string($description) || !is_bool($noIndex) || ($openGraphImageAssetId !== null && !is_string($openGraphImageAssetId))) {
            throw new \InvalidArgumentException('Invalid page SEO settings.');
        }

        return new self(
            trim($title),
            trim($description),
            $noIndex,
            $openGraphImageAssetId === '' ? null : $openGraphImageAssetId,
        );
    }

    /** @return array{title: string, description: string, noIndex: bool, openGraphImageAssetId: ?string} */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'noIndex' => $this->noIndex,
            'openGraphImageAssetId' => $this->openGraphImageAssetId,
        ];
    }
}
