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
        if ($theme !== 'morfira') {
            throw new \InvalidArgumentException('Unsupported page theme.');
        }
        if (!in_array($template, ['standard', 'wide'], true)) {
            throw new \InvalidArgumentException('Unsupported page template.');
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
