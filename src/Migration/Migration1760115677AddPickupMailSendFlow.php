<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1760115677AddPickupMailSendFlow extends MigrationStep
{
    public const SEND_PICKUP_READY_FLOW_ID = '6274f06b80621e996fc24532f010fa4b';
    public const SEND_ADMIN_PICKUP_ORDER_PLACED_FLOW_ID = 'c8f2e7a5e43d081cb1987ae8ba5fe5ff';
    public const SEND_PICKUP_READY_MAIL = 'Send pickup ready mail to customer';
    public const SEND_ADMIN_PICKUP_ORDER_PLACED_MAIL = 'Send admin pickup order placed mail';

    public function getCreationTimestamp(): int
    {
        return 1760115677;
    }

    /**
     * @throws Exception
     */
    public function update(Connection $connection): void
    {
        $mailTemplates = $this->getMailTemplateConfigs();

        foreach ($mailTemplates as $mailTemplate) {
            $this->insertFlowData($connection, $mailTemplate);
        }

        $this->registerIndexer($connection, 'flow.indexer');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getMailTemplateConfigs(): array
    {
        return [
            [
                'flowId' => self::SEND_PICKUP_READY_FLOW_ID,
                'mailTemplateId' => Migration1760113852PickupReadyMailTemplate::PICKUP_READY_TEMPLATE_ID,
                'flowName' => self::SEND_PICKUP_READY_MAIL,
                'event' => 'state_enter.order_delivery.state.ready_for_pickup',
                'type' => 'default',
            ],
            [
                'flowId' => self::SEND_ADMIN_PICKUP_ORDER_PLACED_FLOW_ID,
                'mailTemplateId' => Migration1760113852PickupReadyMailTemplate::ADMIN_ORDER_PLACED_TEMPLATE_ID,
                'flowName' => self::SEND_ADMIN_PICKUP_ORDER_PLACED_MAIL,
                'event' => PickupOrderPlacedEvent::EVENT_NAME,
                'type' => 'admin',
            ],
        ];
    }

    /**
     * @throws Exception
     */
    private function insertFlowData(Connection $connection, array $mailTemplate): void
    {
        $flowId = Uuid::fromHexToBytes($mailTemplate['flowId']);

        if ($this->flowExists($connection, $flowId)) {
            return;
        }

        $connection->transactional(function (Connection $conn) use ($flowId, $mailTemplate) {
            $this->insertFlow($conn, $flowId, $mailTemplate);

            if ($this->shouldInsertSequence($mailTemplate)) {
                $this->insertFlowSequence($conn, $flowId, $mailTemplate);
            }
        });
    }

    /**
     * @throws Exception
     */
    private function flowExists(Connection $connection, string $flowId): bool
    {
        $existingFlow = $connection->fetchOne(
            'SELECT id FROM flow WHERE id = :id',
            ['id' => $flowId]
        );

        return $existingFlow !== false;
    }

    /**
     * @throws Exception
     */
    private function insertFlow(Connection $connection, string $flowId, array $mailTemplate): void
    {
        $connection->insert('flow', [
            'id' => $flowId,
            'name' => $mailTemplate['flowName'],
            'event_name' => $mailTemplate['event'],
            'active' => true,
            'payload' => null,
            'invalid' => 0,
            'custom_fields' => null,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @throws Exception
     */
    private function insertFlowSequence(Connection $connection, string $flowId, array $mailTemplate): void
    {
        $mailTemplateId = $this->getValidMailTemplateId($mailTemplate['mailTemplateId']);

        $connection->insert('flow_sequence', [
            'id' => Uuid::randomBytes(),
            'flow_id' => $flowId,
            'rule_id' => null,
            'parent_id' => null,
            'action_name' => SendMailAction::ACTION_NAME,
            'position' => 1,
            'true_case' => 0,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'config' => json_encode([
                'replyTo' => null,
                'recipient' => ['data' => [], 'type' => 'default'],
                'mailTemplateId' => $mailTemplateId,
                'documentTypeIds' => [],
            ]),
        ]);
    }

    private function shouldInsertSequence(array $mailTemplate): bool
    {
        return $this->getValidMailTemplateId($mailTemplate['mailTemplateId']) !== null;
    }

    private function getValidMailTemplateId(mixed $mailTemplateId): ?string
    {
        return \is_string($mailTemplateId) ? $mailTemplateId : null;
    }
}
