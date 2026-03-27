<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSalesChannelMapping\PickupLocationSalesChannelMappingDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1760119000PickupLocationSalesChannelMapping extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760119000;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $mappingTable = PickupLocationSalesChannelMappingDefinition::ENTITY_NAME;
        $pickupLocationTable = PickupLocationDefinition::ENTITY_NAME;

        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `{$mappingTable}` (
                `pickup_location_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`pickup_location_id`, `sales_channel_id`),
                CONSTRAINT `fk.{$mappingTable}.pickup_location_id`
                    FOREIGN KEY (`pickup_location_id`)
                    REFERENCES `{$pickupLocationTable}` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.{$mappingTable}.sales_channel_id`
                    FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }
}

