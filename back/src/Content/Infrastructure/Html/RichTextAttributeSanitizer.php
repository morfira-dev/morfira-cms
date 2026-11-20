<?php

declare(strict_types=1);

namespace Cms\Content\Infrastructure\Html;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

final class RichTextAttributeSanitizer implements AttributeSanitizerInterface
{
    private const string PUBLIC_IMAGE = '~\A/media/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/[1-9][0-9]{0,3}w\.webp\z~';
    private const string COLOR = '~\A(?:#[a-f0-9]{3,8}|[a-z]{1,24}|(?:rgb|rgba|hsl|hsla)\([0-9 .,%+\-]+\))\z~';
    private const string LENGTH = '~\A[0-9]{1,4}(?:\.[0-9]{1,3})?(?:px|%|em|rem)\z~';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['src', 'style', 'dir', 'start', 'reversed', 'scope', 'width', 'height', 'span', 'colspan', 'rowspan',
            'data-checklist-checked', 'data-mw-node', 'data-mention-id'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return match ($attribute) {
            'src' => $element === 'img' && $this->allowsImageSource($value) ? $value : null,
            'style' => $this->sanitizeStyle($element, $value),
            'dir' => in_array($value, ['ltr', 'rtl'], true) ? $value : null,
            'start' => preg_match('/\A[+-]?[0-9]{1,10}\z/', $value) === 1 && (int) $value >= -2147483648 && (int) $value <= 2147483647 ? (string) (int) $value : null,
            'reversed' => '',
            'scope' => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $value : null,
            'width', 'height', 'span', 'colspan', 'rowspan' => preg_match('/\A[0-9]{1,4}\z/', $value) === 1 && (int) $value >= 1 && (int) $value <= 1000 ? (string) (int) $value : null,
            'data-checklist-checked' => in_array($value, ['true', 'false'], true) ? $value : null,
            'data-mw-node' => $value === 'morfira:mention' ? $value : null,
            'data-mention-id' => $value !== '' && mb_strlen($value) <= 256 && preg_match('/[\x00-\x1f\x7f\x{2028}\x{2029}]/u', $value) === 0 ? $value : null,
            default => null,
        };
    }

    private function allowsImageSource(string $value): bool
    {
        if (preg_match(self::PUBLIC_IMAGE, $value) === 1) {
            return true;
        }

        $url = parse_url($value);
        if ($url === false || ($url['scheme'] ?? null) !== 'https' || !isset($url['host']) || isset($url['user']) || isset($url['pass'])) {
            return false;
        }
        $host = strtolower(rtrim($url['host'], '.'));
        if (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || !str_contains($host, '.')
            || preg_match('/\.(?:[0-9]+|0x[0-9a-f]+|localhost|local|internal)\z/', $host) === 1) {
            return false;
        }

        $path = rawurldecode($url['path'] ?? '');
        if (preg_match('~[%\\\\\x00-\x1f\x7f]~', $path) === 1) {
            return false;
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '.' && $segment !== '') {
                $segments[] = $segment;
            }
        }

        return preg_match('~(?:\A|/)api/v1/admin/media(?:/|\z)~i', implode('/', $segments)) !== 1;
    }

    private function sanitizeStyle(string $element, string $style): ?string
    {
        if (strlen($style) > 2048) {
            return null;
        }

        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($this->allowsStyleValue($element, $property, $value)) {
                $safe[$property] = $property.': '.$value;
            }
        }

        return $safe === [] ? null : implode('; ', $safe);
    }

    private function allowsStyleValue(string $element, string $property, string $value): bool
    {
        $lower = strtolower($value);
        $cell = $element === 'th' || $element === 'td';

        return match ($property) {
            'color', 'background-color' => preg_match(self::COLOR, $lower) === 1,
            'font-family' => preg_match('/\A(?:[a-z0-9_ -]+|"[a-z0-9_ -]+"|\'[a-z0-9_ -]+\')(?: *, *(?:[a-z0-9_ -]+|"[a-z0-9_ -]+"|\'[a-z0-9_ -]+\')){0,7}\z/i', $value) === 1 && strlen($value) <= 256,
            'font-size' => preg_match(self::LENGTH, $lower) === 1,
            'font-weight' => preg_match('/\A(?:bold|normal|[1-9]00)\z/', $lower) === 1,
            'font-style' => in_array($lower, ['italic', 'normal'], true),
            'text-decoration' => in_array($lower, ['underline', 'line-through', 'none'], true),
            'text-align' => in_array($lower, ['left', 'center', 'right', 'justify'], true),
            'list-style-type' => ($element === 'ul' && in_array($lower, ['disc', 'circle', 'square'], true))
                || ($element === 'ol' && in_array($lower, ['decimal', 'lower-alpha', 'upper-alpha', 'lower-roman', 'upper-roman'], true)),
            'width', 'height' => ($cell || in_array($element, ['img', 'table', 'col'], true)) && preg_match(self::LENGTH, $lower) === 1,
            'margin-left', 'margin-right' => in_array($element, ['table', 'img'], true) && in_array($lower, ['0px', 'auto'], true),
            'display' => $element === 'img' && $lower === 'block',
            'vertical-align' => $cell && in_array($lower, ['top', 'middle', 'bottom'], true),
            'padding', 'border-width' => $cell && preg_match('/\A(?:0|[1-9][0-9]?)px\z/', $lower) === 1 && (int) $lower <= ($property === 'padding' ? 48 : 12),
            'border-style' => $cell && in_array($lower, ['solid', 'dashed', 'dotted', 'double', 'none'], true),
            'border-color' => $cell && preg_match(self::COLOR, $lower) === 1,
            'border' => $cell && preg_match('/\A(0|[1-9][0-9]?)px +(solid|dashed|dotted|double|none) +(.+)\z/', $lower, $parts) === 1
                && (int) $parts[1] <= 12 && preg_match(self::COLOR, $parts[3]) === 1,
            default => false,
        };
    }
}
