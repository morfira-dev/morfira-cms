<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Application;

use Cms\Media\Application\FindMediaReference;
use PHPUnit\Framework\TestCase;

final class FindMediaReferenceTest extends TestCase
{
    public function testRecognizesNativeAndInlineReferences(): void
    {
        $asset = '5bb4ff18-266f-44a7-a3d0-1b4d84ac0ac8';
        $path = '/media/'.$asset.'/480w.webp';

        self::assertTrue(FindMediaReference::inPayload(['data' => ['assetId' => $asset]], $asset));
        self::assertTrue(FindMediaReference::inPayload(['body' => '<figure><img alt="Graphic" src="'.$path.'" /></figure>'], $asset));
        self::assertTrue(FindMediaReference::inPayload(['body' => '<img src="https://cms.example'.$path.'" />'], $asset));
        self::assertFalse(FindMediaReference::inPayload(['body' => '<a href="'.$path.'">Link</a>'], $asset));
    }
}
