<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Listener;

use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Event\PickupOrderReadyEvent;
use Kommandhub\ClickAndPickSW\Listener\BusinessEventCollectorListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;

#[CoversClass(BusinessEventCollectorListener::class)]
class BusinessEventCollectorListenerTest extends TestCase
{
    private BusinessEventCollector&MockObject $collector;

    private BusinessEventCollectorListener $listener;

    protected function setUp(): void
    {
        $this->collector = $this->createMock(BusinessEventCollector::class);
        $this->listener = new BusinessEventCollectorListener($this->collector);
    }

    public function testRegistersPickupBusinessEvents(): void
    {
        $placedDefinition = new BusinessEventDefinition('pickup.order.placed', PickupOrderPlacedEvent::class, []);
        $readyDefinition = new BusinessEventDefinition('pickup.order.ready', PickupOrderReadyEvent::class, []);
        $event = new BusinessEventCollectorEvent(new BusinessEventCollectorResponse(), Context::createDefaultContext());

        $this->collector
            ->expects(static::exactly(2))
            ->method('define')
            ->willReturnCallback(static fn (string $class): ?BusinessEventDefinition => match ($class) {
                PickupOrderPlacedEvent::class => $placedDefinition,
                PickupOrderReadyEvent::class => $readyDefinition,
            });

        $this->listener->onAddPickupFlowEvents($event);

        static::assertSame($placedDefinition, $event->getCollection()->get('pickup.order.placed'));
        static::assertSame($readyDefinition, $event->getCollection()->get('pickup.order.ready'));
    }

    public function testSkipsEventsThatCannotBeDefined(): void
    {
        $placedDefinition = new BusinessEventDefinition('pickup.order.placed', PickupOrderPlacedEvent::class, []);
        $event = new BusinessEventCollectorEvent(new BusinessEventCollectorResponse(), Context::createDefaultContext());

        $this->collector
            ->expects(static::exactly(2))
            ->method('define')
            ->willReturnCallback(static fn (string $class): ?BusinessEventDefinition => match ($class) {
                PickupOrderPlacedEvent::class => $placedDefinition,
                PickupOrderReadyEvent::class => null,
            });

        $this->listener->onAddPickupFlowEvents($event);

        static::assertSame($placedDefinition, $event->getCollection()->get('pickup.order.placed'));
        static::assertNull($event->getCollection()->get('pickup.order.ready'));
    }
}
