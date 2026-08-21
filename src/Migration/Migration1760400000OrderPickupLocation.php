<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * The dedicated order pickup-location table — the single source of truth for
 * pickup data on an order (location, chosen time, customer instructions),
 * replacing the previous order custom field. FK to the versioned `order`
 * cascades on order delete; FK to the pickup location is SET NULL so orders keep
 * their history when a location is removed. Idempotent.
 */
class Migration1760400000OrderPickupLocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760400000;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $table = OrderPickupLocationDefinition::ENTITY_NAME;
        $locationTable = PickupLocationDefinition::ENTITY_NAME;

        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `pickup_location_id` BINARY(16) NULL,
                `pickup_time` DATETIME(3) NULL,
                `comment` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.{$table}.order` (`order_id`, `order_version_id`),
                KEY `idx.{$table}.pickup_location` (`pickup_location_id`),
                CONSTRAINT `fk.{$table}.order`
                    FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.{$table}.pickup_location`
                    FOREIGN KEY (`pickup_location_id`)
                    REFERENCES `{$locationTable}` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }
}
