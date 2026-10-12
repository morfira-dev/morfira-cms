<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

final readonly class PageLayout
{
    public function __construct(
        public string $theme,
        public string $template,
        public ?string $headerNavigationCode,
        public ?string $footerNavigationCode,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $theme) !== 1 || preg_match('/^[a-z][a-z0-9-]{0,63}$/', $template) !== 1) {
            throw new \InvalidArgumentException('Theme and template identifiers must be lowercase slugs.');
        }
        foreach ([$headerNavigationCode, $footerNavigationCode] as $code) {
            if ($code !== null && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $code) !== 1) {
                throw new \InvalidArgumentException('Navigation codes must be lowercase slugs.');
            }
        }
    }

    public static function defaults(): self
    {
        return new self('morfira', 'standard', null, null);
    }

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self
    {
        $theme = $value['theme'] ?? null;
        $template = $value['template'] ?? null;
        $header = $value['headerNavigationCode'] ?? null;
        $footer = $value['footerNavigationCode'] ?? null;
        if (!is_string($theme) || !is_string($template) || ($header !== null && !is_string($header)) || ($footer !== null && !is_string($footer))) {
            throw new \InvalidArgumentException('Invalid page layout.');
        }

        return new self($theme, $template, $header, $footer);
    }

    /** @return array{theme: string, template: string, headerNavigationCode: ?string, footerNavigationCode: ?string} */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
            'template' => $this->template,
            'headerNavigationCode' => $this->headerNavigationCode,
            'footerNavigationCode' => $this->footerNavigationCode,
        ];
    }
}
