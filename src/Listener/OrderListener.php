<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Installer\CustomFieldsInstaller;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Handles pickup-related order wiring:
 *
 * - Persist the selected pickup location onto the order (custom field).
 * - Attach the pickup location entity to loaded orders (extension).
 * - Dispatch the {@see PickupOrderPlacedEvent} flow trigger for pickup orders.
 *
 * Notifications (customer and pickup-location mails) are handled entirely by
 * Flow Builder flows reacting to the triggers — this listener sends no mail.
 */
readonly class OrderListener
{
    public const PICKUP_LOCATION_EXTENSION = 'pickupLocation';

    public function __construct(
        private EntityRepository $orderRepository,
        private EntityRepository $kommandhubPickupLocationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * On order placement: persist the pickup location and, for a valid pickup
     * order, dispatch the flow trigger. The pickup-location notification is then
     * sent by the flow bound to that trigger, not from here.
     */
    #[AsEventListener(event: CheckoutOrderPlacedEvent::class)]
    public function onCheckoutOrderPlacedEvent(CheckoutOrderPlacedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $context = $salesChannelContext->getContext();

        $pickupLocationId = $this->extractPickupLocationId($salesChannelContext);

        if ($pickupLocationId === null) {
            return;
        }

        $order = $event->getOrder();

        $this->updateOrderWithPickupLocation($order, $pickupLocationId, $context);

        // Resolve the location once. A missing entity (deleted/invalid id) means
        // this is not a valid pickup order — skip the flow trigger. Normal
        // delivery orders never get here (no pickup extension).
        $pickupLocation = $this->getPickupLocation($pickupLocationId, $context);

        if ($pickupLocation === null) {
            return;
        }

        $this->dispatchPickupOrderPlacedEvent($order, $pickupLocation, $salesChannelContext);
    }

    /**
     * Attach pickup location entity to orders when loaded.
     *
     * Enables:
     * $order.extensions.pickupLocation
     */
    #[AsEventListener(event: OrderEvents::ORDER_LOADED_EVENT)]
    public function onOrderLoaded(EntityLoadedEvent $event): void
    {
        $orderLocationMap = $this->extractOrderLocationMap($event);

        if ($orderLocationMap === []) {
            return;
        }

        $pickupLocations = $this->fetchPickupLocationsById(
            array_values($orderLocationMap),
            $event->getContext()
        );

        if ($pickupLocations === []) {
            return;
        }

        $this->attachPickupLocations($event, $orderLocationMap, $pickupLocations);
    }

    /**
     * Fetch pickup location entity.
     */
    private function getPickupLocation(string $id, Context $context): ?PickupLocationEntity
    {
        $entity = $this->kommandhubPickupLocationRepository
            ->search(new Criteria([$id]), $context)
            ->first();

        return $entity instanceof PickupLocationEntity ? $entity : null;
    }

    /**
     * Extract pickup location ID from context extension.
     */
    private function extractPickupLocationId(SalesChannelContext $context): ?string
    {
        $extension = $context->getExtension(self::PICKUP_LOCATION_EXTENSION);

        $pickupLocationId = $extension?->getVars()['id'] ?? null;

        return \is_string($pickupLocationId) && $pickupLocationId !== ''
            ? $pickupLocationId
            : null;
    }

    /**
     * Persist pickup location ID to order custom fields.
     */
    private function updateOrderWithPickupLocation(
        OrderEntity $order,
        string $pickupLocationId,
        Context $context
    ): void {
        $customFields = $order->getCustomFields() ?? [];

        $this->orderRepository->update([[
            'id' => $order->getId(),
            'customFields' => array_merge($customFields, [
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => $pickupLocationId,
            ]),
        ]], $context);
    }

    /**
     * Dispatch domain event for pickup orders.
     */
    private function dispatchPickupOrderPlacedEvent(
        OrderEntity $order,
        PickupLocationEntity $pickupLocation,
        SalesChannelContext $context
    ): void {
        $this->eventDispatcher->dispatch(
            new PickupOrderPlacedEvent($context, $order, $pickupLocation),
            PickupOrderPlacedEvent::EVENT_NAME
        );
    }

    /**
     * Extract order → pickup location mapping.
     *
     * @return array<string, string>
     */
    private function extractOrderLocationMap(EntityLoadedEvent $event): array
    {
        $map = [];

        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $id = $order->getCustomFieldsValue(
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD
            );

            if (is_string($id) && $id !== '') {
                $map[$order->getId()] = $id;
            }
        }

        return $map;
    }

    /**
     * Fetch pickup locations indexed by ID.
     *
     * @param array<int, string> $ids
     *
     * @return array<string, PickupLocationEntity>
     */
    private function fetchPickupLocationsById(array $ids, Context $context): array
    {
        $entities = $this->kommandhubPickupLocationRepository
            ->search(new Criteria(array_unique($ids)), $context)
            ->getEntities();

        $mapped = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof PickupLocationEntity) {
                continue;
            }

            $mapped[$entity->getId()] = $entity;
        }

        return $mapped;
    }

    /**
     * Attach pickup location entities to orders as extensions.
     *
     * @param array<string, string> $orderLocationMap
     * @param array<string, PickupLocationEntity> $locations
     */
    private function attachPickupLocations(
        EntityLoadedEvent $event,
        array $orderLocationMap,
        array $locations
    ): void {
        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $locationId = $orderLocationMap[$order->getId()] ?? null;

            if ($locationId && isset($locations[$locationId])) {
                $order->addExtension(self::PICKUP_LOCATION_EXTENSION, $locations[$locationId]);
            }
        }
    }
}
