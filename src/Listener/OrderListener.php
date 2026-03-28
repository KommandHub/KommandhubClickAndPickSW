<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Kommandhub\ClickAndPickSW\Service\CustomFieldsInstaller;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Listens for the CheckoutOrderPlacedEvent and updates the order entity
 * with the selected pickup location, if available.
 *
 * @package Kommandhub\ClickAndPickSW\Storefront\Listener
 */
readonly class OrderListener
{
    /**
     * The extension name used to retrieve the pickup location from the context.
     */
    private const PICKUP_LOCATION_EXTENSION = 'pickupLocation';

    /**
     * @param EntityRepository $orderRepository Repository for updating order entities.
     */
    public function __construct(
        private EntityRepository $orderRepository,
        private EntityRepository $kommandhubPickupLocationRepository,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Handles the CheckoutOrderPlacedEvent.
     * Extracts the pickup location ID from the context and updates the order if present.
     *
     * @param CheckoutOrderPlacedEvent $event
     */
    #[AsEventListener(event: CheckoutOrderPlacedEvent::class)]
    public function onCheckoutOrderPlacedEvent(CheckoutOrderPlacedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $pickupLocationId = $this->extractPickupLocationId($context);

        if ($pickupLocationId === null) {
            // No pickup location selected, nothing to update.
            return;
        }

        $order = $event->getOrder();
        $salesChannelContext = $event->getSalesChannelContext();

        $this->updateOrderWithPickupLocation($order, $pickupLocationId, $context->getContext());
        $this->dispatchPickupOrderPlacedEvent($order, $salesChannelContext);
    }

    /**
     * Handle order-loaded event.
     */
    #[AsEventListener(event: OrderEvents::ORDER_LOADED_EVENT)]
    public function onOrderLoaded(EntityLoadedEvent $event): void
    {
        // Step 1: Build map of orderId => pickupLocationId
        $orderLocationMap = $this->extractOrderLocationMap($event);

        if ($orderLocationMap === []) {
            return;
        }

        // Step 2: Fetch pickup locations
        $pickupLocationsById = $this->fetchPickupLocationsById(
            array_values($orderLocationMap),
            $event
        );

        if ($pickupLocationsById === []) {
            return;
        }

        // Step 3: Attach pickup locations to orders
        $this->attachPickupLocations($event, $orderLocationMap, $pickupLocationsById);
    }

    /**
     * Extract mapping of order IDs to pickup location IDs.
     *
     * @return array<string, string> [orderId => locationId]
     */
    private function extractOrderLocationMap(EntityLoadedEvent $event): array
    {
        $map = [];

        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $locationId = $order->getCustomFieldsValue(
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD
            );

            if (!is_string($locationId) || $locationId === '') {
                continue;
            }

            $map[$order->getId()] = $locationId;
        }

        return $map;
    }

    /**
     * Fetch pickup locations indexed by ID.
     *
     * @param string[] $locationIds
     * @return array<string, mixed> [locationId => pickupLocationEntity]
     */
    private function fetchPickupLocationsById(array $locationIds, EntityLoadedEvent $event): array
    {
        $criteria = new Criteria(array_unique($locationIds));

        $result = $this->kommandhubPickupLocationRepository
            ->search($criteria, $event->getContext())
            ->getEntities();

        if ($result->count() === 0) {
            return [];
        }

        $mapped = [];

        foreach ($result as $pickupLocation) {
            $mapped[$pickupLocation->getId()] = $pickupLocation;
        }

        return $mapped;
    }

    /**
     * Attach pickup locations to orders via extensions.
     *
     * @param array<string, string> $orderLocationMap
     * @param array<string, mixed> $pickupLocationsById
     */
    private function attachPickupLocations(
        EntityLoadedEvent $event,
        array $orderLocationMap,
        array $pickupLocationsById
    ): void {
        foreach ($event->getEntities() as $order) {
            if (!$order instanceof OrderEntity) {
                continue;
            }

            $locationId = $orderLocationMap[$order->getId()] ?? null;

            if ($locationId === null) {
                continue;
            }

            $pickupLocation = $pickupLocationsById[$locationId] ?? null;

            if ($pickupLocation === null) {
                continue;
            }

            $order->addExtension(self::PICKUP_LOCATION_EXTENSION, $pickupLocation);
        }
    }

    /**
     * Extracts the pickup location ID from the sales channel context extension.
     *
     * @param mixed $context The sales channel context.
     * @return string|null The pickup location ID, or null if not set.
     */
    private function extractPickupLocationId(mixed $context): ?string
    {
        $pickupLocationExtension = $context->getExtension(self::PICKUP_LOCATION_EXTENSION);

        if ($pickupLocationExtension === null) {
            // Extension isn't found, return null.
            return null;
        }

        $pickupLocationVars = $pickupLocationExtension->getVars();

        return $pickupLocationVars['id'] ?? null;
    }

    /**
     * Updates the order entity with the selected pickup location ID in its custom fields.
     *
     * @param OrderEntity $order The order entity to update.
     * @param string $pickupLocationId The pickup location ID to store.
     * @param mixed $context The Shopware context for the update operation.
     */
    private function updateOrderWithPickupLocation(OrderEntity $order, string $pickupLocationId, mixed $context): void
    {
        $customFields = $order->getCustomFields() ?? [];

        $updateData = [
            'id' => $order->getId(),
            'customFields' => array_merge($customFields, [
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => $pickupLocationId
            ]),
        ];

        // Update the order entity with the new custom field.
        $this->orderRepository->update([$updateData], $context);
    }

    /**
     * Dispatches a PickupOrderPlacedEvent if the given order includes a delivery with a pickup shipping method.
     *
     * @param OrderEntity $order The order entity to check for pickup deliveries.
     * @param SalesChannelContext $salesChannelContext The sales channel context associated with the order.
     *
     * @return void
     */
    private function dispatchPickupOrderPlacedEvent(OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        $deliveries = $order->getDeliveries();

        if ($deliveries === null || $deliveries->count() === 0) {
            return;
        }

        foreach ($deliveries as $delivery) {
            $shippingMethod = $delivery->getShippingMethod();
            if ($shippingMethod === null) {
                continue;
            }

            if ($shippingMethod->getId() !== KommandhubClickAndPickSW::SHIPPING_METHOD_ID) {
                continue;
            }

            // It's a pickup shipping method, dispatch the PickupOrderPlacedEvent
            $pickupEvent = new PickupOrderPlacedEvent(
                $salesChannelContext,
                $order
            );

            $this->eventDispatcher->dispatch($pickupEvent, PickupOrderPlacedEvent::EVENT_NAME);

            // We only need to dispatch once for the first pickup shipping method found
            break;
        }
    }
}