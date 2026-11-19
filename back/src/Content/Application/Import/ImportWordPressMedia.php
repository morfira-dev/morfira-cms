<?php

declare(strict_types=1);

namespace Cms\Content\Application\Import;

use Cms\Media\Application\ImportLocalImage;
use Cms\Media\Application\MediaAssetView;
use Cms\Media\Application\GetMediaAsset\GetMediaAsset;

/**
 * @phpstan-type Attachment array{id:string,path:string,url:string,alt:string,eligible:bool}
 * @phpstan-type MediaOutcome array{id:string,state:string,assetId?:string,reason?:string}
 */
final readonly class ImportWordPressMedia
{
    public function __construct(private WordPressImportLedger $ledger, private ImportLocalImage $images, private GetMediaAsset $assets)
    {
    }

    /**
     * @param list<Attachment> $attachments
     * @param \ArrayObject<int,MediaAssetView> $created
     * @return list<MediaOutcome>
     */
    public function __invoke(string $source, array $attachments, ?string $directory, bool $apply, \ArrayObject $created): array
    {
        $report = [];
        foreach ($attachments as $attachment) {
            $report[] = $this->importAttachment($source, $attachment, $directory, $apply, $created);
        }

        return $report;
    }

    /** @param iterable<MediaAssetView> $created */
    public function compensate(iterable $created): void
    {
        foreach ($created as $asset) {
            $this->images->rollback($asset);
        }
    }

    /** @return ?array{assetId:string,alt:string} */
    public function reusableImage(string $source, string $id): ?array
    {
        $assetId = $this->ledger->mediaAssetId($source, $id);
        $asset = $assetId === null ? null : ($this->assets)($assetId);

        return $asset === null ? null : ['assetId' => $asset->id, 'alt' => $asset->defaultAlt];
    }

    /**
     * @param Attachment $attachment
     * @param \ArrayObject<int,MediaAssetView> $created
     * @return MediaOutcome
     */
    private function importAttachment(string $source, array $attachment, ?string $directory, bool $apply, \ArrayObject $created): array
    {
        $id = $attachment['id'];
        if (!$attachment['eligible']) {
            return ['id' => $id, 'state' => 'unsupported_status'];
        }
        if ($this->ledger->contains($source, $id)) {
            $image = $this->reusableImage($source, $id);
            if ($image === null) {
                return ['id' => $id, 'state' => 'already_imported', 'reason' => 'No reusable media asset mapping is available.'];
            }
            return ['id' => $id, 'state' => 'already_imported', 'assetId' => $image['assetId']];
        }
        if ($directory === null) {
            return ['id' => $id, 'state' => 'media_directory_required'];
        }

        $relativePath = $this->relativePath($attachment);
        if ($relativePath === '') {
            return ['id' => $id, 'state' => 'invalid', 'reason' => 'Attachment has no usable uploads path.'];
        }

        try {
            if (!$apply) {
                ($this->images)($directory, $relativePath, $attachment['alt'], false);
                return ['id' => $id, 'state' => 'planned'];
            }

            $asset = $this->ledger->transactional(function () use ($source, $id, $directory, $relativePath, $attachment, $created): MediaAssetView {
                $this->ledger->claim($source, $id);
                $asset = ($this->images)($directory, $relativePath, $attachment['alt'], true);
                if ($asset === null) {
                    throw new \LogicException('Applying an image import must produce an asset.');
                }
                $created->append($asset);
                $this->ledger->recordMediaAsset($source, $id, $asset->id);

                return $asset;
            });
        } catch (\InvalidArgumentException $error) {
            return ['id' => $id, 'state' => 'invalid', 'reason' => $error->getMessage()];
        }

        return ['id' => $id, 'state' => 'imported', 'assetId' => $asset->id];
    }

    /** @param Attachment $attachment */
    private function relativePath(array $attachment): string
    {
        if ($attachment['path'] !== '') {
            return $attachment['path'];
        }

        $path = parse_url($attachment['url'], PHP_URL_PATH);
        $prefix = '/wp-content/uploads/';
        if (!is_string($path) || ($start = strpos($path, $prefix)) === false) {
            return '';
        }

        return rawurldecode(substr($path, $start + strlen($prefix)));
    }
}
