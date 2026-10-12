<?php

declare(strict_types=1);

namespace Cms\Navigation\Domain;

final readonly class NavigationItem
{
    /** @param list<NavigationItem> $children */
    private function __construct(
        public string $id,
        public string $label,
        public string $href,
        public bool $openInNewTab,
        public array $children,
    ) {
    }

    /** @param list<array<string, mixed>> $payloads
     *  @return list<NavigationItem>
     */
    public static function listFromArray(array $payloads): array
    {
        $count = 0;

        return self::parseList($payloads, 1, $count);
    }

    /** @return array{id: string, label: string, href: string, openInNewTab: bool, children: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'href' => $this->href,
            'openInNewTab' => $this->openInNewTab,
            'children' => array_map(static fn (self $child): array => $child->toArray(), $this->children),
        ];
    }

    /** @param list<array<string, mixed>> $payloads
     *  @return list<NavigationItem>
     */
    private static function parseList(array $payloads, int $depth, int &$count): array
    {
        if ($payloads !== [] && $depth > 3) {
            throw new \InvalidArgumentException('Navigation items must not exceed 3 levels.');
        }

        $items = [];
        foreach ($payloads as $payload) {
            ++$count;
            if ($count > 100) {
                throw new \InvalidArgumentException('A navigation menu must not contain more than 100 items.');
            }

            $id = $payload['id'] ?? null;
            $label = $payload['label'] ?? null;
            $href = $payload['href'] ?? null;
            $openInNewTab = $payload['openInNewTab'] ?? null;
            $children = $payload['children'] ?? null;

            if (!is_string($id) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
                throw new \InvalidArgumentException('Every navigation item must have a UUID identifier.');
            }

            if (!is_string($label) || trim($label) === '' || mb_strlen(trim($label)) > 120) {
                throw new \InvalidArgumentException('Navigation item label must contain between 1 and 120 characters.');
            }

            if (!is_string($href) || mb_strlen(trim($href)) > 2048 || !self::isValidHref(trim($href))) {
                throw new \InvalidArgumentException('Navigation item href must be a relative path or an absolute HTTP(S) URL.');
            }

            if (!is_bool($openInNewTab) || !is_array($children) || !array_is_list($children)) {
                throw new \InvalidArgumentException('Navigation item openInNewTab and children fields are invalid.');
            }

            $childPayloads = [];
            foreach ($children as $child) {
                if (!is_array($child)) {
                    throw new \InvalidArgumentException('Every navigation child must be an object.');
                }
                /** @var array<string, mixed> $child */
                $childPayloads[] = $child;
            }

            $items[] = new self($id, trim($label), trim($href), $openInNewTab, self::parseList($childPayloads, $depth + 1, $count));
        }

        return $items;
    }

    private static function isValidHref(string $href): bool
    {
        if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
            return true;
        }

        $parts = parse_url($href);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        return is_string($scheme)
            && in_array(strtolower($scheme), ['http', 'https'], true)
            && is_string($host)
            && $host !== '';
    }
}
