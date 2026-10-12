<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageBlock
{
    /** @param array<string, string> $data */
    private function __construct(
        public string $id,
        public string $type,
        public array $data,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $id = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? null;

        if (!is_string($id) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Every block must have a UUID identifier.');
        }

        if (!is_string($type) || !in_array($type, ['hero', 'richText', 'image'], true)) {
            throw new \InvalidArgumentException('Unsupported block type.');
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Block data must be an object.');
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \InvalidArgumentException('Block fields must be strings.');
            }
            $normalized[$key] = trim($value);
        }

        self::validateData($type, $normalized);

        return new self($id, $type, $normalized);
    }

    /** @return array{id: string, type: string, data: array<string, string>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'data' => $this->data];
    }

    /** @param array<string, string> $data */
    private static function validateData(string $type, array $data): void
    {
        $allowed = match ($type) {
            'hero' => ['heading', 'text'],
            'richText' => ['content'],
            'image' => ['assetId', 'alt', 'caption'],
            default => throw new \InvalidArgumentException('Unsupported block type.'),
        };
        $unexpected = array_diff(array_keys($data), $allowed);

        if ($unexpected !== []) {
            throw new \InvalidArgumentException(sprintf('Unexpected block field "%s".', reset($unexpected)));
        }

        if ($type === 'hero') {
            self::required($data, 'heading', 120);
            self::optional($data, 'text', 500);
            return;
        }

        if ($type === 'richText') {
            self::required($data, 'content', 20_000);
            $visibleContent = html_entity_decode(strip_tags($data['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_replace('/[\s\x{00A0}]+/u', '', $visibleContent) === '') {
                throw new \InvalidArgumentException('Block field "content" must contain visible text.');
            }
            return;
        }

        self::required($data, 'assetId', 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $data['assetId']) !== 1) {
            throw new \InvalidArgumentException('Block field "assetId" must be a UUID identifier.');
        }
        self::required($data, 'alt', 300);
        self::optional($data, 'caption', 500);
    }

    /** @param array<string, string> $data */
    private static function required(array $data, string $key, int $limit): void
    {
        if (($data[$key] ?? '') === '' || mb_strlen($data[$key]) > $limit) {
            throw new \InvalidArgumentException(sprintf('Block field "%s" is required and must not exceed %d characters.', $key, $limit));
        }
    }

    /** @param array<string, string> $data */
    private static function optional(array $data, string $key, int $limit): void
    {
        if (isset($data[$key]) && mb_strlen($data[$key]) > $limit) {
            throw new \InvalidArgumentException(sprintf('Block field "%s" must not exceed %d characters.', $key, $limit));
        }
    }
}
