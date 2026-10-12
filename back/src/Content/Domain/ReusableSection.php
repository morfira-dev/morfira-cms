<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class ReusableSection
{
    /** @param list<PageBlock> $blocks */
    public function __construct(
        public string $id,
        public string $name,
        public array $blocks,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Section identifier must be a UUID.');
        }
        if ($name !== trim($name) || mb_strlen($name) < 1 || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Section name must contain between 1 and 120 trimmed characters.');
        }
        if ($blocks === []) {
            throw new \InvalidArgumentException('A reusable section must contain at least one block.');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Section version must be positive.');
        }
    }

    /** @param list<PageBlock> $blocks */
    public function revise(string $name, array $blocks, \DateTimeImmutable $now): self
    {
        return new self($this->id, trim($name), $blocks, $this->version + 1, $this->createdAt, $now);
    }

    /** @return array{id: string, name: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, version: int, createdAt: string, updatedAt: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'blocks' => array_map(static fn (PageBlock $block): array => $block->toArray(), $this->blocks),
            'version' => $this->version,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
