<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour\PickupLocationOpeningHourDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour\PickupLocationSpecialHourDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Kommandhub\ClickAndPickSW\PickupLocation\Availability\Weekday;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Normalized pickup-location scheduling: a weekly opening-hour interval table and
 * a special-date override table, plus an IANA timezone on the pickup location.
 * Backfills the existing openDays / openingHours / closingHours JSON into
 * interval rows. Idempotent and safe to re-run. The legacy columns are kept
 * (deprecated) — removed only once no code reads them.
 */
class Migration1760300000NormalizedPickupSchedule extends MigrationStep
{
    private const DEFAULT_TIMEZONE = 'UTC';

    public function getCreationTimestamp(): int
    {
        return 1760300000;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $this->addTimezoneColumn($connection);
        $this->createOpeningHourTable($connection);
        $this->createSpecialHourTable($connection);
        $this->backfill($connection);
    }

    /**
     * @throws Exception
     */
    private function addTimezoneColumn(Connection $connection): void
    {
        if ($this->columnExists($connection, PickupLocationDefinition::ENTITY_NAME, 'timezone')) {
            return;
        }

        $connection->executeStatement(sprintf(
            'ALTER TABLE `%s` ADD COLUMN `timezone` VARCHAR(64) NULL AFTER `time_format`',
            PickupLocationDefinition::ENTITY_NAME
        ));
    }

    /**
     * @throws Exception
     */
    private function createOpeningHourTable(Connection $connection): void
    {
        $table = PickupLocationOpeningHourDefinition::ENTITY_NAME;
        $parent = PickupLocationDefinition::ENTITY_NAME;

        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` BINARY(16) NOT NULL,
                `pickup_location_id` BINARY(16) NOT NULL,
                `day_of_week` INT NOT NULL,
                `open_time` VARCHAR(5) NOT NULL,
                `close_time` VARCHAR(5) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.{$table}.location_weekday` (`pickup_location_id`, `day_of_week`),
                CONSTRAINT `fk.{$table}.pickup_location_id`
                    FOREIGN KEY (`pickup_location_id`) REFERENCES `{$parent}` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    /**
     * @throws Exception
     */
    private function createSpecialHourTable(Connection $connection): void
    {
        $table = PickupLocationSpecialHourDefinition::ENTITY_NAME;
        $parent = PickupLocationDefinition::ENTITY_NAME;

        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` BINARY(16) NOT NULL,
                `pickup_location_id` BINARY(16) NOT NULL,
                `date` DATE NOT NULL,
                `closed` TINYINT(1) NOT NULL DEFAULT 0,
                `open_time` VARCHAR(5) NULL,
                `close_time` VARCHAR(5) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                KEY `idx.{$table}.location_date` (`pickup_location_id`, `date`),
                CONSTRAINT `fk.{$table}.pickup_location_id`
                    FOREIGN KEY (`pickup_location_id`) REFERENCES `{$parent}` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    /**
     * @throws Exception
     */
    private function backfill(Connection $connection): void
    {
        $parent = PickupLocationDefinition::ENTITY_NAME;
        $intervalTable = PickupLocationOpeningHourDefinition::ENTITY_NAME;
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Default the timezone so availability is deterministic on existing rows.
        $connection->executeStatement(
            "UPDATE `{$parent}` SET `timezone` = :tz WHERE `timezone` IS NULL OR `timezone` = ''",
            ['tz' => self::DEFAULT_TIMEZONE]
        );

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $connection->fetchAllAssociative(
            "SELECT LOWER(HEX(`id`)) AS id, `open_days`, `opening_hours`, `closing_hours` FROM `{$parent}`"
        );

        foreach ($rows as $row) {
            $locationId = $this->rowString($row, 'id');

            if ($locationId === null) {
                continue;
            }

            $locationIdBytes = Uuid::fromHexToBytes($locationId);

            // Idempotency: skip locations that already have interval rows.
            $existing = $connection->fetchOne(
                "SELECT 1 FROM `{$intervalTable}` WHERE `pickup_location_id` = :id LIMIT 1",
                ['id' => $locationIdBytes]
            );

            if ($existing !== false) {
                continue;
            }

            foreach ($this->buildIntervals($row) as $interval) {
                $connection->insert($intervalTable, [
                    'id' => Uuid::randomBytes(),
                    'pickup_location_id' => $locationIdBytes,
                    'day_of_week' => $interval['dayOfWeek'],
                    'open_time' => $interval['openTime'],
                    'close_time' => $interval['closeTime'],
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{dayOfWeek: int, openTime: string, closeTime: string}>
     */
    private function buildIntervals(array $row): array
    {
        $openDays = $this->decodeOpenDays($row['open_days'] ?? null);

        if ($openDays === []) {
            return [];
        }

        // Preserve prior behavior: locations with no explicit hours were open all
        // day on their open days.
        $openTime = $this->normalizeTime($this->rowString($row, 'opening_hours') ?? '') ?? '00:00';
        $closeTime = $this->normalizeTime($this->rowString($row, 'closing_hours') ?? '') ?? '23:59';

        $intervals = [];

        foreach ($openDays as $dayName) {
            $iso = Weekday::isoFromName((string) $dayName);

            if ($iso === null) {
                continue;
            }

            $intervals[] = [
                'dayOfWeek' => $iso,
                'openTime' => $openTime,
                'closeTime' => $closeTime,
            ];
        }

        return $intervals;
    }

    /**
     * @return list<string>
     */
    private function decodeOpenDays(mixed $openDays): array
    {
        if (!\is_string($openDays) || $openDays === '') {
            return [];
        }

        try {
            $decoded = json_decode($openDays, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn ($value): bool => \is_string($value)));
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) ? $value : null;
    }
}
