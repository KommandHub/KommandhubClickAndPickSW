<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Flow\Storer;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Flow\Aware\PickupLocationAware;
use Kommandhub\ClickAndPickSW\Flow\Storer\PickupLocationFlowStorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\FlowEventAware;

#[CoversClass(PickupLocationFlowStorer::class)]
class PickupLocationFlowStorerTest extends TestCase
{
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private EntityRepository&MockObject $repository;

    private PickupLocationFlowStorer $storer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->storer = new PickupLocationFlowStorer($this->repository);
    }

    public function testStoreKeepsPickupLocationIdForPickupAwareEvent(): void
    {
        $event = $this->createMock(PickupLocationAware::class);
        $event->method('getPickupLocationId')->willReturn(self::LOCATION_ID);

        $stored = $this->storer->store($event, []);

        static::assertSame(
            [PickupLocationAware::PICKUP_LOCATION_ID => self::LOCATION_ID],
            $stored
        );
    }

    public function testStoreIgnoresNonPickupEvents(): void
    {
        // A normal (non-pickup) order dispatches events that are not pickup-aware.
        $event = $this->createMock(FlowEventAware::class);

        static::assertSame([], $this->storer->store($event, []));
    }

    public function testStoreDoesNotOverwriteExistingValue(): void
    {
        $event = $this->createMock(PickupLocationAware::class);
        $event->expects(static::never())->method('getPickupLocationId');

        $stored = $this->storer->store($event, [PickupLocationAware::PICKUP_LOCATION_ID => 'existing']);

        static::assertSame([PickupLocationAware::PICKUP_LOCATION_ID => 'existing'], $stored);
    }

    public function testRestoreLoadsPickupLocationLazily(): void
    {
        $entity = new PickupLocationEntity();
        $entity->setId(self::LOCATION_ID);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn($entity);
        $this->repository->method('search')->willReturn($result);

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [PickupLocationAware::PICKUP_LOCATION_ID => self::LOCATION_ID]
        );

        $this->storer->restore($storable);

        static::assertSame(self::LOCATION_ID, $storable->getData(PickupLocationAware::PICKUP_LOCATION_ID));
        // getData resolves the registered lazy closure on first access.
        static::assertSame($entity, $storable->getData(PickupLocationAware::PICKUP_LOCATION));
    }

    public function testRestoreDoesNothingWithoutStoredId(): void
    {
        $this->repository->expects(static::never())->method('search');

        $storable = new StorableFlow('pickup.order.placed', $this->createMock(Context::class));

        $this->storer->restore($storable);

        static::assertFalse($storable->hasData(PickupLocationAware::PICKUP_LOCATION));
    }

    public function testRestoreReturnsNullWhenStoredPickupLocationIdIsNotAString(): void
    {
        $this->repository->expects(static::never())->method('search');

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [PickupLocationAware::PICKUP_LOCATION_ID => 123]
        );

        $this->storer->restore($storable);

        static::assertNull($storable->getData(PickupLocationAware::PICKUP_LOCATION));
    }

    public function testRestoreReturnsNullWhenRepositoryDoesNotReturnPickupLocationEntity(): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('first')->willReturn(new \stdClass());
        $this->repository->method('search')->willReturn($result);

        $storable = new StorableFlow(
            'pickup.order.placed',
            $this->createMock(Context::class),
            [PickupLocationAware::PICKUP_LOCATION_ID => self::LOCATION_ID]
        );

        $this->storer->restore($storable);

        static::assertNull($storable->getData(PickupLocationAware::PICKUP_LOCATION));
    }
}
