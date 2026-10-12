<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Content\Domain\PageBlock;
use Cms\Content\Domain\ReusableSection;
use Cms\Content\Domain\ReusableSectionConflict;
use Cms\Content\Domain\ReusableSectionRepository;

final readonly class ManageReusableSections
{
    public function __construct(private ReusableSectionRepository $sections)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(static fn (ReusableSection $section): array => $section->toArray(), $this->sections->all());
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array{id: string, name: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, version: int, createdAt: string, updatedAt: string}
     */
    public function create(string $name, array $blocks): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $section = new ReusableSection(self::uuid(), trim($name), self::blocks($blocks), 1, $now, $now);
        $this->sections->add($section);

        return $section->toArray();
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return array{id: string, name: string, blocks: list<array{id: string, type: string, data: array<string, string>}>, version: int, createdAt: string, updatedAt: string}
     */
    public function update(string $id, string $name, array $blocks, int $expectedVersion): array
    {
        $current = $this->sections->get($id);
        if ($current === null) {
            throw new \OutOfBoundsException('Reusable section not found.');
        }
        if ($current->version !== $expectedVersion) {
            throw new ReusableSectionConflict();
        }
        $updated = $current->revise($name, self::blocks($blocks), new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->sections->save($updated, $expectedVersion);

        return $updated->toArray();
    }

    public function delete(string $id): void
    {
        if (!$this->sections->remove($id)) {
            throw new \OutOfBoundsException('Reusable section not found.');
        }
    }

    /**
     * @param list<array<string, mixed>> $payload
     * @return list<PageBlock>
     */
    private static function blocks(array $payload): array
    {
        return array_map(PageBlock::fromArray(...), $payload);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}
