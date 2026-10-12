<?php

declare(strict_types=1);

namespace Cms\Media\Infrastructure\Persistence;

use Cms\Media\Domain\MediaAsset;
use Cms\Media\Domain\MediaAssetId;
use Cms\Media\Domain\MediaAssetRepository;
use Cms\Media\Domain\MediaVariant;
use Doctrine\DBAL\Connection;

final readonly class DoctrineMediaAssetRepository implements MediaAssetRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function add(MediaAsset $asset): void
    {
        $this->connection->insert('media_assets', [
            'id' => $asset->id->value,
            'original_name' => $asset->originalName,
            'mime_type' => $asset->mimeType,
            'width' => $asset->width,
            'height' => $asset->height,
            'byte_size' => $asset->bytes,
            'default_alt' => $asset->defaultAlt,
            'collection' => $asset->collection,
            'tags' => json_encode($asset->tags, JSON_THROW_ON_ERROR),
            'variants' => json_encode(
                array_map(static fn (MediaVariant $variant): array => $variant->toArray(), $asset->variants),
                JSON_THROW_ON_ERROR,
            ),
            'created_at' => $asset->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function get(MediaAssetId $id): ?MediaAsset
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM media_assets WHERE id = ?', [$id->value]);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(MediaAsset $asset): void
    {
        $this->connection->update('media_assets', [
            'default_alt' => $asset->defaultAlt,
            'collection' => $asset->collection,
            'tags' => json_encode($asset->tags, JSON_THROW_ON_ERROR),
        ], ['id' => $asset->id->value]);
    }

    public function remove(MediaAssetId $id): void
    {
        $this->connection->delete('media_assets', ['id' => $id->value]);
    }

    public function all(): array
    {
        return array_map(
            $this->hydrate(...),
            $this->connection->fetchAllAssociative('SELECT * FROM media_assets ORDER BY created_at DESC'),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): MediaAsset
    {
        $variantRows = json_decode(self::stringValue($row, 'variants'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($variantRows) || !array_is_list($variantRows) || $variantRows === []) {
            throw new \UnexpectedValueException('Media variants must be a non-empty JSON list.');
        }

        $variants = array_map(static function (mixed $variant): MediaVariant {
            if (!is_array($variant)) {
                throw new \UnexpectedValueException('Every stored media variant must be an object.');
            }

            /** @var array<string, mixed> $variant */
            return new MediaVariant(
                self::intValue($variant, 'width'),
                self::intValue($variant, 'height'),
                self::intValue($variant, 'bytes'),
                self::stringValue($variant, 'url'),
            );
        }, $variantRows);
        $tags = json_decode(self::stringValue($row, 'tags'), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($tags) || !array_is_list($tags) || array_filter($tags, static fn (mixed $tag): bool => !is_string($tag)) !== []) {
            throw new \UnexpectedValueException('Media tags must be a JSON list of strings.');
        }
        /** @var list<string> $tags */

        $collection = $row['collection'] ?? null;
        if ($collection !== null && !is_string($collection)) {
            throw new \UnexpectedValueException('Media collection must be null or a string.');
        }

        return MediaAsset::reconstitute(
            MediaAssetId::fromString(self::stringValue($row, 'id')),
            self::stringValue($row, 'original_name'),
            self::stringValue($row, 'mime_type'),
            self::intValue($row, 'width'),
            self::intValue($row, 'height'),
            self::intValue($row, 'byte_size'),
            self::stringValue($row, 'default_alt'),
            $collection,
            $tags,
            $variants,
            new \DateTimeImmutable(self::stringValue($row, 'created_at'), new \DateTimeZone('UTC')),
        );
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        }

        return (int) $value;
    }
}
