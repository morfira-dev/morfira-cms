<?php

declare(strict_types=1);

namespace Cms\Tests\Content\Application\Import;

use Cms\Content\Application\Import\WordPressLegacyGallery;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class WordPressLegacyGalleryTest extends TestCase
{
    public function testExpandsExplicitListsInSourceOrderAndPreservesText(): void
    {
        $document = new DOMDocument();
        $element = $document->createElement('p');
        $document->appendChild($element);
        $node = $document->createTextNode('First & [gallery columns=3 ids="9, 18"] middle [gallery include=\'21,20\' /] after');
        $element->appendChild($node);

        WordPressLegacyGallery::expand($node);

        self::assertSame(
            '<p>First &amp; <img class="wp-image-9"><img class="wp-image-18"> middle <img class="wp-image-21"><img class="wp-image-20"> after</p>',
            $document->saveHTML($element),
        );
    }

    public function testLeavesUnsupportedShortcodesAndEscapedSyntaxAsText(): void
    {
        $document = new DOMDocument();
        $element = $document->createElement('p');
        $document->appendChild($element);
        $source = '[[gallery ids="1,2"]] [gallery id="1"] [gallery ids="3" include="4"] '
            .'[gallery ids="5" orderby="rand"] [gallery ids="0,6"] [gallery ids="7," ] '
            .'[gallery ids="'.implode(',', range(1, 21)).'"] [gallery ids="8" broken] [gallery ids="9"';
        $node = $document->createTextNode($source);
        $element->appendChild($node);

        WordPressLegacyGallery::expand($node);

        self::assertSame($node, $element->firstChild);
        self::assertSame($source, $element->textContent);
    }

    public function testUsesDataIdOnlyOnImagesInsideRecognizedGalleries(): void
    {
        $document = new DOMDocument();
        $document->loadHTML(
            '<div class="columns wp-block-gallery"><ul><li><img data-id="123"><img data-id="001"></li></ul></div>'
            .'<div class="wp-block-gallery-example"><img data-id="456"></div><img data-id="789">',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );
        $images = $document->getElementsByTagName('img');

        self::assertSame(['123', null, null, null], array_map(
            static fn (DOMElement $image): ?string => WordPressLegacyGallery::imageId($image),
            iterator_to_array($images),
        ));
    }
}
