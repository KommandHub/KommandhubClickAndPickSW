<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Flow\Storer;

use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationEntity;
use Kommandhub\ClickAndPickSW\Flow\Aware\OrderPickupLocationAware;
use Kommandhub\ClickAndPickSW\Flow\Storer\OrderPickupLocationFlowStorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\FlowEventAware;

#[CoversClass(OrderPickupLocationFlowStorer::class)]
class OrderPickupLocationFlowStorerTest extends TestCase
{
    private const RECORD_ID = 'cccccccccccccccccccccccccccccccc';

    private EntityRepository&MockObject $repository;

    private OrderPickupLocationFlowStorer $storer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->storer = new OrderPickupLocationFlowStorer($this->repository);
    }

    public function testStoreKeepsRecordIdForAwareEvent(): void
    {
        $event = $this->createMock(OrderPickupLocationAware::class);
        $event->method('getOrderPickupLocationId')->willReturn(self::RECORD_ID);

        $stored = $this->storer->store($event, []);

        static::assertSame(
            [OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => self::RECORD_ID],
            $stored
        );
    }

    public function testStoreIgnoresNonAwareEvents(): void
    {
        $event = $this->createMock(FlowEventAware::class);

        static::assertSame([], $this->storer->store($event, []));
    }

    public function testStoreDoesNotOverwriteExistingValue(): void
    {
        $event = $this->createMock(OrderPickupLocationAware::class);
        $event->expects(static::never())->method('getOrderPickupLocationId');

        $stored = $this->storer->store($event, [OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => 'existing']);

        static::assertSame([OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => 'existing'], $stored);
    }

    public function testRestoreLoadsRecordLazily(): void
    {
        $entity = new OrderPickupLocationEntity();
        $entity->setId(self::RECORD_ID);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($entity);
        $this->repository->method('search')->willReturn($result);

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => self::RECORD_ID]
        );

        $this->storer->restore($storable);

        static::assertSame(self::RECORD_ID, $storable->getData(OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID));
        // getData resolves the registered lazy closure on first access.
        static::assertSame($entity, $storable->getData(OrderPickupLocationAware::ORDER_PICKUP_LOCATION));
    }

    public function testRestoreDoesNothingWithoutStoredId(): void
    {
        $this->repository->expects(static::never())->method('search');

        $storable = new StorableFlow('pickup.order.placed', $this->createMock(Context::class));

        $this->storer->restore($storable);

        static::assertFalse($storable->hasData(OrderPickupLocationAware::ORDER_PICKUP_LOCATION));
    }

    public function testRestoreReturnsNullWhenStoredIdIsNotAString(): void
    {
        $this->repository->expects(static::never())->method('search');

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => 123]
        );

        $this->storer->restore($storable);

        static::assertNull($storable->getData(OrderPickupLocationAware::ORDER_PICKUP_LOCATION));
    }

    public function testRestoreReturnsNullWhenRepositoryDoesNotReturnExpectedEntity(): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn(new \stdClass());
        $this->repository->method('search')->willReturn($result);

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [OrderPickupLocationAware::ORDER_PICKUP_LOCATION_ID => self::RECORD_ID]
        );

        $this->storer->restore($storable);

        static::assertNull($storable->getData(OrderPickupLocationAware::ORDER_PICKUP_LOCATION));
    }
}
