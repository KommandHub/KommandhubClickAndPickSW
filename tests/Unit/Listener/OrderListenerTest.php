<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Listener;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Installer\CustomFieldsInstaller;
use Kommandhub\ClickAndPickSW\Listener\OrderListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(OrderListener::class)]
#[UsesClass(PickupOrderPlacedEvent::class)]
class OrderListenerTest extends TestCase
{
    private const ORDER_ID = '0123456789abcdef0123456789abcdef';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';
    private const SECOND_LOCATION_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private EntityRepository&MockObject $orderRepository;

    private EntityRepository&MockObject $pickupLocationRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private OrderListener $listener;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->pickupLocationRepository = $this->createMock(EntityRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->listener = new OrderListener(
            $this->orderRepository,
            $this->pickupLocationRepository,
            $this->eventDispatcher,
        );
    }

    public function testDispatchesFlowTriggerAndPersistsPickupLocationForValidPickupOrder(): void
    {
        $order = $this->order(['existing' => 'value']);
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->salesChannelContext(new ArrayStruct(['id' => self::LOCATION_ID]), $context);
        $pickupLocation = $this->location(self::LOCATION_ID, 'Downtown Store');

        $this->orderRepository
            ->expects(static::once())
            ->method('update')
            ->with([[
                'id' => self::ORDER_ID,
                'customFields' => [
                    'existing' => 'value',
                    CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID,
                ],
            ]], $context);

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === [self::LOCATION_ID]),
                $context
            )
            ->willReturn($this->firstResult($pickupLocation));

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(
                    static fn (object $event): bool => $event instanceof PickupOrderPlacedEvent
                        && $event->getPickupLocation() === $pickupLocation
                        && $event->getOrder() === $order
                        && $event->getSalesChannelContext() === $salesChannelContext
                ),
                PickupOrderPlacedEvent::EVENT_NAME
            )
            ->willReturnArgument(0);

        $this->listener->onCheckoutOrderPlacedEvent(
            new CheckoutOrderPlacedEvent($salesChannelContext, $order)
        );
    }

    public function testDoesNothingForNormalDeliveryOrder(): void
    {
        $order = $this->order();

        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->orderRepository->expects(static::never())->method('update');
        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->listener->onCheckoutOrderPlacedEvent(
            new CheckoutOrderPlacedEvent(
                $this->salesChannelContext(null, Context::createDefaultContext()),
                $order
            )
        );
    }

    public function testDoesNothingForInvalidPickupLocationExtensionData(): void
    {
        $order = $this->order();

        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->orderRepository->expects(static::never())->method('update');
        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->listener->onCheckoutOrderPlacedEvent(
            new CheckoutOrderPlacedEvent(
                $this->salesChannelContext(new ArrayStruct(['id' => 123]), Context::createDefaultContext()),
                $order
            )
        );
    }

    public function testDoesNothingForEmptyPickupLocationExtensionData(): void
    {
        $order = $this->order();

        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->orderRepository->expects(static::never())->method('update');
        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->listener->onCheckoutOrderPlacedEvent(
            new CheckoutOrderPlacedEvent(
                $this->salesChannelContext(new ArrayStruct(['id' => '']), Context::createDefaultContext()),
                $order
            )
        );
    }

    public function testDoesNotPersistOrDispatchWhenLocationCannotBeResolved(): void
    {
        $order = $this->order();
        $context = Context::createDefaultContext();

        // A dangling/deleted location id must not be written to the order.
        $this->orderRepository->expects(static::never())->method('update');
        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($this->firstResult(null));
        $this->eventDispatcher->expects(static::never())->method('dispatch');

        $this->listener->onCheckoutOrderPlacedEvent(
            new CheckoutOrderPlacedEvent(
                $this->salesChannelContext(new ArrayStruct(['id' => self::LOCATION_ID]), $context),
                $order
            )
        );
    }

    public function testAttachesPickupLocationExtensionsOnOrderLoaded(): void
    {
        $orderWithMatch = $this->order([
            CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID,
        ]);
        $orderWithoutMatch = new OrderEntity();
        $orderWithoutMatch->setId('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $orderWithoutMatch->setCustomFields([
            CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::SECOND_LOCATION_ID,
        ]);

        $pickupLocation = $this->location(self::LOCATION_ID, 'Downtown Store');
        $event = $this->loadedEvent([$orderWithMatch, new PickupLocationEntity(), $orderWithoutMatch]);

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === [
                    self::LOCATION_ID,
                    self::SECOND_LOCATION_ID,
                ]),
                $event->getContext()
            )
            ->willReturn($this->entitiesResult([$pickupLocation]));

        $this->listener->onOrderLoaded($event);

        static::assertSame($pickupLocation, $orderWithMatch->getExtension(OrderListener::PICKUP_LOCATION_EXTENSION));
        static::assertNull($orderWithoutMatch->getExtension(OrderListener::PICKUP_LOCATION_EXTENSION));
    }

    public function testSkipsOrderLoadedWhenNoPickupLocationsAreStoredOnOrders(): void
    {
        $event = $this->loadedEvent([$this->order(), new PickupLocationEntity()]);

        $this->pickupLocationRepository->expects(static::never())->method('search');

        $this->listener->onOrderLoaded($event);
    }

    public function testSkipsOrderLoadedWhenStoredPickupLocationsCannotBeResolved(): void
    {
        $order = $this->order([
            CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID,
        ]);
        $event = $this->loadedEvent([$order]);

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($this->entitiesResult([]));

        $this->listener->onOrderLoaded($event);

        static::assertNull($order->getExtension(OrderListener::PICKUP_LOCATION_EXTENSION));
    }

    public function testSkipsUnexpectedRepositoryEntitiesWhenAttachingPickupLocations(): void
    {
        $order = $this->order([
            CustomFieldsInstaller::ORDER_PICKUP_LOCATION_CUSTOM_FIELD => self::LOCATION_ID,
        ]);
        $event = $this->loadedEvent([$order]);

        $this->pickupLocationRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($this->mixedEntitiesResult([new \stdClass()]));

        $this->listener->onOrderLoaded($event);

        static::assertNull($order->getExtension(OrderListener::PICKUP_LOCATION_EXTENSION));
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function order(array $customFields = []): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setCustomFields($customFields);

        return $order;
    }

    private function location(string $id, string $name): PickupLocationEntity
    {
        $location = new PickupLocationEntity();
        $location->setId($id);
        $location->setName($name);

        return $location;
    }

    private function salesChannelContext(?ArrayStruct $pickupExtension, Context $context): SalesChannelContext&MockObject
    {
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getContext'])
            ->getMock();
        $salesChannelContext->method('getContext')->willReturn($context);

        if ($pickupExtension !== null) {
            $salesChannelContext->addExtension(OrderListener::PICKUP_LOCATION_EXTENSION, $pickupExtension);
        }

        return $salesChannelContext;
    }

    /**
     * @param list<object> $entities
     */
    private function loadedEvent(array $entities): EntityLoadedEvent
    {
        $definition = $this->createMock(\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('order');

        return new EntityLoadedEvent($definition, $entities, Context::createDefaultContext());
    }

    private function firstResult(?object $first): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($first);

        return $result;
    }

    /**
     * @param list<PickupLocationEntity> $entities
     */
    private function entitiesResult(array $entities): EntitySearchResult&MockObject
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new EntityCollection($entities));

        return $result;
    }

    /**
     * @param list<object> $entities
     */
    private function mixedEntitiesResult(array $entities): EntitySearchResult&MockObject
    {
        $collection = $this->createMock(EntityCollection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator($entities));

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
    }
}
