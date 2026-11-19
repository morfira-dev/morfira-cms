<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application;

use Cms\Content\Application\Import\WordPressPostImages;
use PHPUnit\Framework\TestCase;

final class WordPressPostImagesTest extends TestCase
{
    public function testExtendingExistingSchemaPreservesFieldsAndPositionsTheImageBetweenText(): void
    {
        $body = ['id' => 'body-id', 'key' => 'body', 'label' => 'Edited body', 'type' => 'richText', 'required' => false];
        $custom = ['id' => 'extra-id', 'key' => 'summary', 'label' => 'Summary', 'type' => 'text', 'required' => false];
        $plan = WordPressPostImages::plan([
            ['type' => 'richText', 'html' => '<p>Before</p>'],
            ['type' => 'image', 'assetId' => 'inline-asset', 'alt' => 'Inline'],
            ['type' => 'richText', 'html' => '<p>After</p>'],
        ], 'featured-asset', [$body, $custom]);

        self::assertSame(['wordpressFeaturedImage', 'body', 'wordpressInlineImage1', 'wordpressBodyPart2', 'summary'], array_column($plan['fields'], 'key'));
        self::assertSame($body, $plan['fields'][1]);
        self::assertSame($custom, $plan['fields'][4]);
        self::assertSame([
            'wordpressFeaturedImage' => 'featured-asset',
            'body' => '<p>Before</p>',
            'wordpressInlineImage1' => 'inline-asset',
            'wordpressBodyPart2' => '<p>After</p>',
        ], $plan['values']);
    }

    public function testIncompatibleExistingImageFieldIsReportedAsAnInvalidPlan(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WordPressPostImages::plan([], 'featured-asset', [
            ['id' => 'body-id', 'key' => 'body', 'label' => 'Body', 'type' => 'richText', 'required' => false],
            ['id' => 'conflict-id', 'key' => 'wordpressFeaturedImage', 'label' => 'Custom data', 'type' => 'text', 'required' => false],
        ]);
    }
}
