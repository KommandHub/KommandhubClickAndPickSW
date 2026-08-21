<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour\PickupLocationOpeningHourDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour\PickupLocationSpecialHourDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Normalized pickup-location scheduling: a weekly opening-hour interval table and
 * a special-date override table, plus an IANA timezone on the pickup location.
 * Idempotent and safe to re-run.
 */
class Migration1760300000NormalizedPickupSchedule extends MigrationStep
{
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
}
