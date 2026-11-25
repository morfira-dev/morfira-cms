<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

/** @phpstan-type SectionData array{id: string, name: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, version: int, createdAt: string, updatedAt: string, locale: ?string, translationGroupId: string} */
final readonly class ReusableSection
{
    public string $translationGroupId;

    /** @param list<PageBlock> $blocks */
    public function __construct(
        public string $id,
        public string $name,
        public array $blocks,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?string $locale = null,
        ?string $translationGroupId = null,
    ) {
        self::assertValidId($id);
        if ($name !== trim($name) || mb_strlen($name) < 1 || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Section name must contain between 1 and 120 trimmed characters.');
        }
        if ($blocks === []) {
            throw new \InvalidArgumentException('A reusable section must contain at least one block.');
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('Section version must be positive.');
        }
        if ($locale !== null && preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/D', $locale) !== 1) {
            throw new \InvalidArgumentException('Locale must be a normalized language tag such as en or pt-BR.');
        }
        $this->translationGroupId = $translationGroupId ?? $id;
        self::assertValidId($this->translationGroupId);
        if ($locale === null && $this->translationGroupId !== $id) {
            throw new \InvalidArgumentException('A section translation requires an explicit locale.');
        }
    }

    public static function assertValidId(string $id): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('Section identifier must be a UUID.');
        }
    }

    /** @param list<PageBlock> $blocks */
    public function revise(string $name, array $blocks, \DateTimeImmutable $now): self
    {
        return new self($this->id, trim($name), $blocks, $this->version + 1, $this->createdAt, $now, $this->locale, $this->translationGroupId);
    }

    /** @return SectionData */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'blocks' => array_map(static fn (PageBlock $block): array => $block->toArray(), $this->blocks),
            'version' => $this->version,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'locale' => $this->locale,
            'translationGroupId' => $this->translationGroupId,
        ];
    }
}
