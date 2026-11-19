<?php

declare(strict_types=1);

namespace Cms\Tests\Media\Application;

use Cms\Media\Application\ImageProcessor;
use Cms\Media\Application\ImportLocalImage;
use Cms\Media\Application\UnsupportedMediaType;
use Cms\Media\Application\UploadImage\UploadImage;
use Cms\Tests\Media\InMemoryMediaAssetRepository;
use PHPUnit\Framework\TestCase;

final class LocalImageImportBoundaryTest extends TestCase
{
    public function testRejectsNonImageBytesWithoutInvokingTheProcessor(): void
    {
        $file = tmpfile();
        self::assertIsResource($file);
        try {
            fwrite($file, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
            $path = stream_get_meta_data($file)['uri'] ?? null;
            self::assertIsString($path);
            $processor = $this->createMock(ImageProcessor::class);
            $processor->expects(self::never())->method('process');
            $import = new ImportLocalImage(new UploadImage(new InMemoryMediaAssetRepository(), $processor), $processor);

            $this->expectException(UnsupportedMediaType::class);
            $import(dirname($path), basename($path), '', false);
        } finally {
            fclose($file);
        }
    }
}
