<?php declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1759696668PickupLocation extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759696668;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $table = PickupLocationDefinition::ENTITY_NAME;

        $connection->executeStatement("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `street` VARCHAR(255) NOT NULL,
                `phone_number` VARCHAR(255) NULL,
                `email` VARCHAR(255) NULL,
                `additional_address_line1` VARCHAR(255) NULL,
                `additional_address_line2` VARCHAR(255) NULL,
                `city` VARCHAR(255) NOT NULL,
                `postal_code` VARCHAR(20) NOT NULL,
                `opening_hours` VARCHAR(255) NULL,
                `closing_hours` VARCHAR(255) NULL,
                `open_days` JSON NULL,
                `latitude` VARCHAR(50) NULL,
                `longitude` VARCHAR(50) NULL,
                `location_code` VARCHAR(100) NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }
}
