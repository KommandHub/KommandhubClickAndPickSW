<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listens to context switch and context-resolved events in the Shopware Storefront.
 * Handles validation and persistence of the pickup location context for the sales channel.
 */
readonly class SwitchContextEventListener
{
    /**
     * Key for the pickup location ID in the context payload.
     */
    final public const PICKUP_LOCATION_ID = 'pickupLocationId';

    /**
     * Key for the pickup location extension in the sales channel context.
     */
    final public const PICKUP_LOCATION_EXTENSION = 'pickupLocation';

    /**
     * @param DataValidator $validator Validator for data validation.
     * @param SalesChannelContextPersister $contextPersister Persists sales channel context data.
     * @param Connection $connection Database connection.
     */
    public function __construct(
        private DataValidator $validator,
        private SalesChannelContextPersister $contextPersister,
        private Connection $connection,
    ) {
    }

    /**
     * Handles the SwitchContextEvent to validate and update the pickup location in the context.
     *
     * @throws \JsonException
     * @throws Exception
     */
    #[AsEventListener(event: SwitchContextEvent::CONSISTENT_CHECK)]
    public function onSwitchContext(SwitchContextEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $pickupLocationId = $this->normalizePickupLocationId(
            $event->getRequestData()->get(self::PICKUP_LOCATION_ID)
        );

        // If no pickup location is provided, do nothing.
        if ($pickupLocationId === null) {
            $this->updateContextPayload($context, null);

            return;
        }

        // Validate and update the pickup location in the context payload.
        $this->validatePickupLocation($pickupLocationId, $context);
        $this->updateContextPayload($context, $pickupLocationId);
    }

    /**
     * Handles the SalesChannelContextResolvedEvent to add the pickup location extension to the context.
     *
     * @param SalesChannelContextResolvedEvent $event
     *
     * @throws \JsonException
     * @throws Exception
     */
    #[AsEventListener(event: SalesChannelContextResolvedEvent::class)]
    public function onSalesChannelContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $existingPayload = $this->fetchContextPayload($context);

        // If no payload exists, do nothing.
        if (!$existingPayload) {
            return;
        }

        $pickupLocationId = $this->normalizePickupLocationId(
            $existingPayload[self::PICKUP_LOCATION_ID] ?? null
        );

        // If no pickup location is set, do nothing.
        if ($pickupLocationId === null) {
            return;
        }

        // Add the pickup location as an extension to the context.
        $context->addExtension(self::PICKUP_LOCATION_EXTENSION, new ArrayStruct([
            'id' => $pickupLocationId,
            self::PICKUP_LOCATION_ID => $pickupLocationId,
        ]));
    }

    /**
     * Validates the provided pickup location ID for the current sales channel context.
     *
     * @param string $pickupLocationId
     * @param SalesChannelContext $context
     */
    private function validatePickupLocation(string $pickupLocationId, SalesChannelContext $context): void
    {
        $criteria = $this->createPickupLocationCriteria($pickupLocationId, $context->getSalesChannelId());
        $definition = $this->createValidationDefinition($criteria, $context);

        $this->validator->validate([self::PICKUP_LOCATION_ID => $pickupLocationId], $definition);
    }

    /**
     * Creates a criteria object to search for a valid pickup location.
     *
     * @param string $pickupLocationId
     * @param string $salesChannelId
     *
     * @return Criteria
     */
    private function createPickupLocationCriteria(string $pickupLocationId, string $salesChannelId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $pickupLocationId),
            new EqualsFilter('active', true),
            new EqualsFilter('salesChannels.id', $salesChannelId),
        ]));
        $criteria->setLimit(1);

        return $criteria;
    }

    /**
     * Creates a validation definition for the pickup location entity.
     *
     * @param Criteria $criteria
     * @param SalesChannelContext $context
     *
     * @return DataValidationDefinition
     */
    private function createValidationDefinition(Criteria $criteria, SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('kommandhub_click_and_pick.context_switch');
        $definition->add(
            self::PICKUP_LOCATION_ID,
            new EntityExists([
                'entity' => 'kommandhub_pickup_location',
                'context' => $context->getContext(),
                'criteria' => $criteria,
            ])
        );

        return $definition;
    }

    /**
     * Updates the context payload with the new pickup location ID.
     * Does nothing if the context is expired or payload is missing.
     *
     * @param SalesChannelContext $context
     * @param string|null $pickupLocationId
     *
     * @throws \JsonException
     * @throws Exception
     */
    private function updateContextPayload(SalesChannelContext $context, ?string $pickupLocationId): void
    {
        $existingPayload = $this->fetchContextPayload($context);

        if (!$existingPayload) {
            return;
        }

        // Don't update if context is marked as expired
        if (($existingPayload['expired'] ?? null) === true) {
            return;
        }

        $existingPayload[self::PICKUP_LOCATION_ID] = $pickupLocationId;

        $this->saveContextPayload($context, $existingPayload);
    }

    /**
     * Fetches the context payload from the database for the given context.
     *
     * @param SalesChannelContext $context
     *
     * @return array|null
     *
     * @throws \JsonException
     * @throws Exception
     */
    private function fetchContextPayload(SalesChannelContext $context): ?array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('payload')
            ->from('sales_channel_api_context')
            ->where('token = :token')
            ->andWhere('sales_channel_id = :salesChannelId')
            ->setParameter('token', $context->getToken())
            ->setParameter('salesChannelId', Uuid::fromHexToBytes($context->getSalesChannelId()));

        $payload = $query->executeQuery()->fetchOne();

        if (!\is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Saves the updated context payload to the database.
     *
     * @param SalesChannelContext $context
     * @param array $payload
     *
     * @throws \JsonException
     */
    private function saveContextPayload(SalesChannelContext $context, array $payload): void
    {
        $customer = $context->getCustomer();
        $customerId = $customer && empty($context->getPermissions()) ? $customer->getId() : null;

        $this->contextPersister->save(
            $context->getToken(),
            $payload,
            $context->getSalesChannelId(),
            $customerId
        );
    }

    private function normalizePickupLocationId(mixed $pickupLocationId): ?string
    {
        if (!\is_string($pickupLocationId)) {
            return null;
        }

        $pickupLocationId = trim($pickupLocationId);

        return $pickupLocationId !== '' ? $pickupLocationId : null;
    }
}
