<?php

declare(strict_types=1);

namespace Cms\Theme\Application;

final readonly class ThemeView
{
    /**
     * @param list<array{id: string, name: string}> $templates
     * @param list<string>                          $regions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public array $templates,
        public array $regions,
    ) {
    }

    /** @return array{id: string, name: string, version: string, templates: list<array{id: string, name: string}>, regions: list<string>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'version' => $this->version, 'templates' => $this->templates, 'regions' => $this->regions];
    }
}
