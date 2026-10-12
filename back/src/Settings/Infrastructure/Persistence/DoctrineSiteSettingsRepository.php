<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence;

use Cms\Settings\Domain\SiteSettings;
use Cms\Settings\Domain\SiteSettingsRepository;
use Cms\Settings\Domain\SiteSettingsVersionConflict;
use Doctrine\DBAL\Connection;

final readonly class DoctrineSiteSettingsRepository implements SiteSettingsRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function get(): SiteSettings
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM site_settings WHERE id = 1');
        if ($row === false) {
            throw new \LogicException('Site settings have not been initialized.');
        }

        return new SiteSettings(
            self::string($row, 'site_name'),
            self::string($row, 'default_locale'),
            self::string($row, 'timezone'),
            self::integer($row, 'version'),
            new \DateTimeImmutable(self::string($row, 'updated_at'), new \DateTimeZone('UTC')),
        );
    }

    public function save(SiteSettings $settings, int $expectedVersion): void
    {
        $affected = $this->connection->update('site_settings', [
            'site_name' => $settings->siteName,
            'default_locale' => $settings->defaultLocale,
            'timezone' => $settings->timezone,
            'version' => $settings->version,
            'updated_at' => $settings->updatedAt->format('Y-m-d H:i:s.u'),
        ], ['id' => 1, 'version' => $expectedVersion]);

        if ($affected !== 1) {
            throw new SiteSettingsVersionConflict();
        }
    }

    /** @param array<string, mixed> $row */
    private static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be a string.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $row */
    private static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \UnexpectedValueException(sprintf('Column "%s" must be an integer.', $key));
        }
        return (int) $value;
    }
}
