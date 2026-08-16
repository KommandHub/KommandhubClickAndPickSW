<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Entity\Order\Aggregated\OrderDelivery\OrderDeliveryStates;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderReadyEvent;
use Kommandhub\ClickAndPickSW\Installer\CustomFieldsInstaller;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Fires the {@see PickupOrderReadyEvent} Flow Builder trigger when an order's
 * delivery enters the "ready" state, but only for orders that carry a valid
 * pickup location.
 *
 * The state-change event is dispatched twice per transition (leave + enter side)
 * under the same event name, so it is filtered to the enter side of the "ready"
 * state; every other transition and side is ignored.
 */
readonly class PickupOrderReadyListener
{
    public function __construct(
        private EntityRepository $orderDeliveryRepository,
        private EntityRepository $kommandhubPickupLocationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[AsEventListener(event: 'state_machine.order_delivery.state_changed')]
    public function onOrderDeliveryStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        if ($event->getNextState()->getTechnicalName() !== OrderDeliveryStates::STATE_READY_FOR_PICKUP) {
            return;
        }

        $context = $event->getContext();

        $order = $this->loadOrder($event->getTransition()->getEntityId(), $context);

        if ($order === null) {
            return;
        }

        $pickupLocationId = $order->getCustomFieldsValue(CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD);

        if (!\is_string($pickupLocationId) || $pickupLocationId === '') {
            // Not a pickup order — a normal delivery reaching a "ready" state.
            return;
        }

        $pickupLocation = $this->resolvePickupLocation($pickupLocationId, $context);

        if ($pickupLocation === null) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new PickupOrderReadyEvent($order, $pickupLocation, $context),
            PickupOrderReadyEvent::EVENT_NAME
        );
    }

    private function loadOrder(string $orderDeliveryId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderDeliveryId]);
        $criteria->addAssociation('order.orderCustomer');

        $delivery = $this->orderDeliveryRepository->search($criteria, $context)->getEntities()->first();

        return $delivery instanceof OrderDeliveryEntity ? $delivery->getOrder() : null;
    }

    private function resolvePickupLocation(string $pickupLocationId, Context $context): ?PickupLocationEntity
    {
        $entity = $this->kommandhubPickupLocationRepository
            ->search(new Criteria([$pickupLocationId]), $context)
            ->first();

        return $entity instanceof PickupLocationEntity ? $entity : null;
    }
}
