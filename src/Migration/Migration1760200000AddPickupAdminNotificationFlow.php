<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Flow\Action\SendPickupNotificationToAdminAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Flow that notifies the pickup location when a pickup order is placed. Replaces
 * the direct notification that used to live in OrderListener, so the admin mail
 * is now sent exclusively through the Flow Builder action.
 */
class Migration1760200000AddPickupAdminNotificationFlow extends MigrationStep
{
    public const FLOW_ID = 'fd5d43fc45a2da3c3a1da4500f911851';

    public function getCreationTimestamp(): int
    {
        return 1760200000;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $flowId = Uuid::fromHexToBytes(self::FLOW_ID);

        if ($this->flowExists($connection, $flowId)) {
            return;
        }

        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $connection->transactional(function (Connection $conn) use ($flowId, $now): void {
            $conn->insert('flow', [
                'id' => $flowId,
                'name' => 'Send pickup notification to admin',
                'event_name' => PickupOrderPlacedEvent::EVENT_NAME,
                'active' => true,
                'payload' => null,
                'invalid' => 0,
                'custom_fields' => null,
                'created_at' => $now,
            ]);

            $conn->insert('flow_sequence', [
                'id' => Uuid::randomBytes(),
                'flow_id' => $flowId,
                'rule_id' => null,
                'parent_id' => null,
                'action_name' => SendPickupNotificationToAdminAction::ACTION_NAME,
                'position' => 1,
                'true_case' => 0,
                'created_at' => $now,
                'config' => json_encode([
                    'mailTemplateId' => Migration1760113852PickupReadyMailTemplate::ADMIN_ORDER_PLACED_TEMPLATE_ID,
                ], \JSON_THROW_ON_ERROR),
            ]);
        });

        $this->registerIndexer($connection, 'flow.indexer');
    }

    /**
     * @throws Exception
     */
    private function flowExists(Connection $connection, string $flowId): bool
    {
        return $connection->fetchOne('SELECT id FROM flow WHERE id = :id', ['id' => $flowId]) !== false;
    }
}
