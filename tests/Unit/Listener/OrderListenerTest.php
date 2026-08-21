<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Listener;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\OrderPickupLocationWriter;
use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupContextStorage;
use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupSelection;
use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Listener\OrderListener;
use Kommandhub\ClickAndPickSW\PickupLocation\PickupLocationSelectionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(OrderListener::class)]
#[UsesClass(PickupOrderPlacedEvent::class)]
#[UsesClass(PickupSelection::class)]
class OrderListenerTest extends TestCase
{
    private const ORDER_ID = '0123456789abcdef0123456789abcdef';
    private const LOCATION_ID = 'fedcba9876543210fedcba9876543210';

    private PickupLocationSelectionResolver&MockObject $resolver;
    private OrderPickupLocationWriter&MockObject $writer;
    private PickupContextStorage&MockObject $pickupContextStorage;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private OrderListener $listener;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(PickupLocationSelectionResolver::class);
        $this->writer = $this->createMock(OrderPickupLocationWriter::class);
        $this->pickupContextStorage = $this->createMock(PickupContextStorage::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->listener = new OrderListener(
            $this->resolver,
            $this->writer,
            $this->pickupContextStorage,
            $this->eventDispatcher
        );
    }

    public function testPersistsPickupRecordAndDispatchesTriggerForPickupOrder(): void
    {
        $order = $this->order();
        $context = Context::createDefaultContext();
        $salesChannelContext = $this->salesChannelContext($context);

        $location = new PickupLocationEntity();
        $location->setId(self::LOCATION_ID);
        $pickupTime = new \DateTimeImmutable('2024-06-03 10:00:00');
        $selection = new PickupSelection($location, $pickupTime, 'Ring the bell');

        $record = new OrderPickupLocationEntity();
        $record->setId('cccccccccccccccccccccccccccccccc');
        $record->setPickupLocation($location);

        $this->resolver->method('isPickupShippingMethod')->with($salesChannelContext)->willReturn(true);
        $this->resolver->method('resolveSelection')->with($salesChannelContext)->willReturn($selection);

        $this->writer
            ->expects(static::once())
            ->method('persist')
            ->with(self::ORDER_ID, $selection, $context)
            ->willReturn($record);

        $this->eventDispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(
                static::callback(
                    static fn (object $event): bool => $event instanceof PickupOrderPlacedEvent
                        && $event->getPickupLocation() === $location
                        && $event->getOrder() === $order
                        && $event->getOrderPickupLocation() === $record
                ),
                PickupOrderPlacedEvent::EVENT_NAME
            )
            ->willReturnArgument(0);

        // The context selection is cleared once it is persisted on the order.
        $this->pickupContextStorage->expects(static::once())->method('clear')->with($salesChannelContext);

        $this->listener->onCheckoutOrderPlacedEvent(new CheckoutOrderPlacedEvent($salesChannelContext, $order));
    }

    public function testDoesNothingForNonPickupShippingMethod(): void
    {
        $salesChannelContext = $this->salesChannelContext(Context::createDefaultContext());
        $this->resolver->method('isPickupShippingMethod')->willReturn(false);

        $this->writer->expects(static::never())->method('persist');
        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->pickupContextStorage->expects(static::never())->method('clear');

        $this->listener->onCheckoutOrderPlacedEvent(new CheckoutOrderPlacedEvent($salesChannelContext, $this->order()));
    }

    public function testDoesNothingWhenSelectionCannotBeResolved(): void
    {
        $salesChannelContext = $this->salesChannelContext(Context::createDefaultContext());
        $this->resolver->method('isPickupShippingMethod')->willReturn(true);
        $this->resolver->method('resolveSelection')->willReturn(null);

        $this->writer->expects(static::never())->method('persist');
        $this->eventDispatcher->expects(static::never())->method('dispatch');
        $this->pickupContextStorage->expects(static::never())->method('clear');

        $this->listener->onCheckoutOrderPlacedEvent(new CheckoutOrderPlacedEvent($salesChannelContext, $this->order()));
    }

    private function order(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);

        return $order;
    }

    private function salesChannelContext(Context $context): SalesChannelContext&MockObject
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn($context);

        return $salesChannelContext;
    }
}
