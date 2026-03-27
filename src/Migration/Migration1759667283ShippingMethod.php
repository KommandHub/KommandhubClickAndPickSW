<?php declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
class Migration1759667283ShippingMethod extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1759667283;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $this->createShippingMethod($connection);
    }

    /**
     * @throws Exception
     */
    private function createShippingMethod(Connection $connection): void
    {
        $deliveryTimeId = $this->createDeliveryTimes($connection);
        $systemLanguage = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $ruleId = $connection->fetchOne('SELECT id FROM rule WHERE name = ?', ['All customers']);
        if ($ruleId === false) {
            $ruleId = null;
        }

        // Insert shipping_method if not exists
        $exists = $connection->fetchOne('SELECT 1 FROM shipping_method WHERE id = ?', [Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID)]);
        if (!$exists) {
            $connection->insert('shipping_method', [
                'id' => Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
                'active' => 1,
                'delivery_time_id' => $deliveryTimeId,
                'technical_name' => 'kommandhub_self_pickup',
                'availability_rule_id' => $ruleId,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ]);
        }

        // Insert shipping_method_translation if not exists
        $exists = $connection->fetchOne('SELECT 1 FROM shipping_method_translation WHERE shipping_method_id = ? AND language_id = ?', [
            Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
            $systemLanguage
        ]);
        if (!$exists) {
            $connection->insert('shipping_method_translation', [
                'shipping_method_id' => Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
                'language_id' => $systemLanguage,
                'name' => 'Self pick-up',
                'description' => 'Pick up your order yourself from the selected location.',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ]);
        }

        // Insert shipping_method_price if not exists
        $exists = $connection->fetchOne('SELECT 1 FROM shipping_method_price WHERE shipping_method_id = ?', [
            Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID)
        ]);
        if (!$exists) {
            $connection->insert('shipping_method_price', [
                'id' => Uuid::randomBytes(),
                'shipping_method_id' => Uuid::fromHexToBytes(KommandhubClickAndPickSW::SHIPPING_METHOD_ID),
                'calculation' => 1,
                'quantity_start' => 0,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            ]);
        }
    }

    /**
     * @throws Exception
     */
    private function createDeliveryTimes(Connection $connection): string
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $fifteenTo30mins = Uuid::randomBytes();
        $thirtyTo45minutes = Uuid::randomBytes();
        $forty5To60minutes = Uuid::randomBytes();

        if (!$connection->fetchOne('SELECT 1 FROM delivery_time WHERE id = ?', [$fifteenTo30mins])) {
            $connection->insert('delivery_time', ['id' => $fifteenTo30mins, 'min' => 0.15, 'max' => 0.30, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_HOUR, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
            $connection->insert('delivery_time_translation', ['delivery_time_id' => $fifteenTo30mins, 'language_id' => $languageEn, 'name' => '15-30 minutes', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        }

        if (!$connection->fetchOne('SELECT 1 FROM delivery_time WHERE id = ?', [$thirtyTo45minutes])) {
            $connection->insert('delivery_time', ['id' => $thirtyTo45minutes, 'min' => 0.30, 'max' => 0.45, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_HOUR, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
            $connection->insert('delivery_time_translation', ['delivery_time_id' => $thirtyTo45minutes, 'language_id' => $languageEn, 'name' => '30-45 minutes', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        }

        if (!$connection->fetchOne('SELECT 1 FROM delivery_time WHERE id = ?', [$forty5To60minutes])) {
            $connection->insert('delivery_time', ['id' => $forty5To60minutes, 'min' => 0.45, 'max' => 0.60, 'unit' => DeliveryTimeEntity::DELIVERY_TIME_HOUR, 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
            $connection->insert('delivery_time_translation', ['delivery_time_id' => $forty5To60minutes, 'language_id' => $languageEn, 'name' => '45-60 minutes', 'created_at' => (new \DateTime())->format('Y-m-d H:i:s')]);
        }
        return $fifteenTo30mins;
    }
}
