<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Listener;

use Kommandhub\ClickAndPickSW\Entity\Order\Aggregated\OrderDelivery\OrderDeliveryStates;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderReadyEvent;
use Kommandhub\ClickAndPickSW\Installer\CustomFieldsInstaller;
use Kommandhub\ClickAndPickSW\Listener\PickupOrderReadyListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(PickupOrderReadyListener::class)]
#[UsesClass(PickupOrderReadyEvent::class)]
class PickupOrderReadyListenerTest extends TestCase
{
    private const ORDER_ID = '0123456789abcdef0123456789abcdef';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';
    private const DELIVERY_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private EntityRepository&MockObject $orderDeliveryRepository;

    private EntityRepository&MockObject $pickupLocationRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private PickupOrderReadyListener $listener;

    protected function setUp(): void
    {
        $this->orderDeliveryRepository = $this->createMock(EntityRepository::class);
        $this->pickupLocationRepository = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->listener = new PickupOrderReadyListener(
            $this->orderDeliveryRepository,
            $this->pickupLocationRepository,
            $this->eventDispatcher,
        );
    }

    public function testDispatchesForPickupOrderEnteringReady(): void
    {
        $order = $this->order([CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID]);
        $pickupLocation = new PickupLocationEntity();
        $pickupLocation->setId(self::LOCATION_ID);
        $event = $this->stateChangeEvent(
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            OrderDeliveryStates::STATE_READY_FOR_PICKUP
        );

        $this->orderDeliveryRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(function (Criteria $criteria): bool {
                    return $criteria->getIds() === [self::DELIVERY_ID]
                        && $criteria->getAssociation('order.orderCustomer') !== null;
                }),
                $event->getContext()
            )
            ->willReturn($this->deliveryResult($order));

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === [self::LOCATION_ID]),
                $event->getContext()
            )
            ->willReturn($this->firstResult($pickupLocation));

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(
                    static fn (object $dispatched): bool => $dispatched instanceof PickupOrderReadyEvent
                        && $dispatched->getOrder() === $order
                        && $dispatched->getPickupLocation() === $pickupLocation
                        && $dispatched->getContext() === $event->getContext()
                ),
                PickupOrderReadyEvent::EVENT_NAME
            )
            ->willReturnArgument(0);

        $this->listener->onOrderDeliveryStateChanged($event);
    }

    public function testDoesNotDispatchForNonPickupOrder(): void
    {
        $this->orderDeliveryRepository->method('search')->willReturn($this->deliveryResult($this->order([])));
        $this->pickupLocationRepository->expects(static::never())->method('search');
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                OrderDeliveryStates::STATE_READY_FOR_PICKUP
            )
        );
    }

    public function testDoesNotDispatchWhenPickupLocationCustomFieldIsEmptyString(): void
    {
        $this->orderDeliveryRepository
            ->method('search')
            ->willReturn($this->deliveryResult($this->order([
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => '',
            ])));

        $this->pickupLocationRepository->expects(static::never())->method('search');
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                OrderDeliveryStates::STATE_READY_FOR_PICKUP
            )
        );
    }

    public function testDoesNotDispatchWhenOrderDeliveryCannotBeResolved(): void
    {
        $this->orderDeliveryRepository
            ->method('search')
            ->willReturn($this->emptyDeliveryResult());
        $this->pickupLocationRepository->expects(static::never())->method('search');
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                OrderDeliveryStates::STATE_READY_FOR_PICKUP
            )
        );
    }

    public function testDoesNotDispatchWhenPickupLocationCannotBeResolved(): void
    {
        $this->orderDeliveryRepository
            ->method('search')
            ->willReturn($this->deliveryResult($this->order([
                CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID,
            ])));
        $this->pickupLocationRepository
            ->method('search')
            ->willReturn($this->firstResult(null));
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                OrderDeliveryStates::STATE_READY_FOR_PICKUP
            )
        );
    }

    public function testDoesNotDispatchForUnrelatedStateTransition(): void
    {
        $this->orderDeliveryRepository->expects(static::never())->method('search');
        $this->pickupLocationRepository->expects(static::never())->method('search');
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
                'shipped'
            )
        );
    }

    public function testDoesNotDispatchOnLeaveSideOfReady(): void
    {
        $this->orderDeliveryRepository->expects(static::never())->method('search');
        $this->pickupLocationRepository->expects(static::never())->method('search');
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onOrderDeliveryStateChanged(
            $this->stateChangeEvent(
                StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
                OrderDeliveryStates::STATE_READY_FOR_PICKUP
            )
        );
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function order(array $customFields): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setSalesChannelId('11111111111111111111111111111111');
        $order->setCustomFields($customFields);

        return $order;
    }

    private function stateChangeEvent(string $side, string $stateTechnicalName): StateMachineStateChangeEvent&MockObject
    {
        $state = $this->createMock(StateMachineStateEntity::class);
        $state->method('getTechnicalName')->willReturn($stateTechnicalName);

        $transition = $this->createMock(Transition::class);
        $transition->method('getEntityId')->willReturn(self::DELIVERY_ID);

        $event = $this->createMock(StateMachineStateChangeEvent::class);
        $event->method('getTransitionSide')->willReturn($side);
        $event->method('getNextState')->willReturn($state);
        $event->method('getTransition')->willReturn($transition);
        $event->method('getContext')->willReturn(Context::createDefaultContext());

        return $event;
    }

    private function deliveryResult(OrderEntity $order): EntitySearchResult&MockObject
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(self::DELIVERY_ID);
        $delivery->setOrder($order);

        $collection = $this->createMock(EntityCollection::class);
        $collection->method('first')->willReturn($delivery);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
    }

    private function emptyDeliveryResult(): EntitySearchResult&MockObject
    {
        $collection = $this->createMock(EntityCollection::class);
        $collection->method('first')->willReturn(null);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
    }

    private function firstResult(?object $first): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($first);

        return $result;
    }
}
