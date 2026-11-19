<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Content\Domain\PageId;

/**
 * @phpstan-import-type Segment from SegmentWordPressImages
 * @phpstan-import-type AuthorReference from ImportWordPressAuthors
 * @phpstan-type Field array{id:string,key:string,label:string,type:string,required:bool,targetContentTypeId?:?string,multiple?:bool}
 */
final class WordPressPostImages
{
    private const int MAX_INLINE_IMAGES = 20;

    /**
     * @param list<Segment> $segments
     * @param list<Field> $fields
     * @param ?AuthorReference $author
     * @return array{fields:list<Field>,values:array<string,string>}
     */
    public static function plan(array $segments, ?string $featured, array $fields, ?array $author = null): array
    {
        if ($fields === []) {
            $fields[] = self::field('body', 'Body', 'richText');
        }
        $indexed = array_column($fields, null, 'key');
        $values = [];
        if ($featured !== null) {
            self::requireField($indexed, 'wordpressFeaturedImage', 'Featured image', 'media');
            $values['wordpressFeaturedImage'] = $featured;
        }
        if ($author !== null) {
            self::requireField($indexed, 'wordpressAuthorName', 'Author', 'text');
            self::requireField($indexed, 'wordpressAuthor', 'Author profile', 'relation', $author['typeId']);
            $values['wordpressAuthorName'] = $author['name'];
            $values['wordpressAuthor'] = $author['id'];
        }
        $imageNumber = 0;
        foreach ($segments as $segment) {
            if ($segment['type'] === 'image') {
                if (++$imageNumber > self::MAX_INLINE_IMAGES) {
                    throw new \InvalidArgumentException('The post exceeds the limit of 20 inline images.');
                }
                $key = 'wordpressInlineImage'.$imageNumber;
                self::requireField($indexed, $key, 'Inline image '.$imageNumber, 'media');
                $values[$key] = $segment['assetId'];
                continue;
            }
            $key = $imageNumber === 0 ? 'body' : 'wordpressBodyPart'.($imageNumber + 1);
            self::requireField($indexed, $key, 'Body part '.($imageNumber + 1), 'richText');
            $values[$key] = ($values[$key] ?? '').$segment['html'];
        }
        if (count($indexed) > 50) {
            throw new \InvalidArgumentException('The WordPress posts schema has no room for the imported fields.');
        }
        if (count($indexed) === count($fields)) {
            return ['fields' => $fields, 'values' => $values];
        }

        return ['fields' => self::orderedFields($indexed), 'values' => $values];
    }

    /**
     * @param array<string,Field> $fields
     * @return list<Field>
     */
    private static function orderedFields(array $fields): array
    {
        $ordered = [];
        $importFields = ['wordpressFeaturedImage'];
        for ($i = 1; $i <= self::MAX_INLINE_IMAGES; ++$i) {
            $importFields[] = 'wordpressInlineImage'.$i;
            $importFields[] = 'wordpressBodyPart'.($i + 1);
        }
        foreach ($fields as $key => $field) {
            if (in_array($key, $importFields, true)) {
                continue;
            }
            if ($key === 'body' && isset($fields['wordpressFeaturedImage'])) {
                $ordered[] = $fields['wordpressFeaturedImage'];
            }
            $ordered[] = $field;
            if ($key === 'body') {
                foreach (array_slice($importFields, 1) as $importKey) {
                    if (isset($fields[$importKey])) {
                        $ordered[] = $fields[$importKey];
                    }
                }
            }
        }

        return $ordered;
    }

    /** @param array<string,Field> $fields */
    private static function requireField(array &$fields, string $key, string $label, string $type, ?string $target = null): void
    {
        if (!isset($fields[$key])) {
            $fields[$key] = self::field($key, $label, $type);
            if ($target !== null) {
                $fields[$key]['targetContentTypeId'] = $target;
            }
        }
        if ($fields[$key]['type'] !== $type || $fields[$key]['required'] || ($fields[$key]['targetContentTypeId'] ?? null) !== $target || ($fields[$key]['multiple'] ?? false)) {
            throw new \InvalidArgumentException('An existing WordPress field has an incompatible definition.');
        }
    }

    /** @return Field */
    private static function field(string $key, string $label, string $type): array
    {
        return ['id' => PageId::new()->value, 'key' => $key, 'label' => $label, 'type' => $type, 'required' => false];
    }
}
