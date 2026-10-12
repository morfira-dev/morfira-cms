<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

interface ReusableSectionRepository
{
    /** @return list<ReusableSection> */
    public function all(): array;

    public function get(string $id): ?ReusableSection;

    public function add(ReusableSection $section): void;

    public function save(ReusableSection $section, int $expectedVersion): void;

    public function remove(string $id): bool;
}
