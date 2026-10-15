<?php

declare(strict_types=1);

namespace Cms\Content\Application;

use Cms\Schema\Application\GetContentTypeDefinition;

final readonly class SanitizeEntryValues
{
    public function __construct(private GetContentTypeDefinition $definitions, private RichTextSanitizer $richText)
    {
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function __invoke(string $contentTypeId, array $values): array
    {
        $definition = $this->definitions->byId($contentTypeId);
        foreach ($definition->fields as $field) {
            $key = $field['key'];
            if ('richText' !== $field['type'] || !array_key_exists($key, $values) || !is_string($values[$key])) {
                continue;
            }

            $sanitized = $this->richText->sanitize($values[$key]);
            $values[$key] = $this->hasVisibleContent($sanitized) ? $sanitized : null;
        }

        return $values;
    }

    private function hasVisibleContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $text);

        return null !== $text && '' !== $text;
    }
}
