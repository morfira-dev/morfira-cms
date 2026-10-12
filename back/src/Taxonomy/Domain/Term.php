<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

final readonly class Term
{
    public function __construct(public string $id, public string $taxonomyId, public string $name, public string $slug, public ?string $parentId, public \DateTimeImmutable $createdAt)
    {
        if (trim($name) === '' || mb_strlen(trim($name)) > 120) {
            throw new \InvalidArgumentException('Term name must contain between 1 and 120 characters.');
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 120) {
            throw new \InvalidArgumentException('Term slug must use lowercase letters, numbers and single hyphens.');
        }
    }
}
