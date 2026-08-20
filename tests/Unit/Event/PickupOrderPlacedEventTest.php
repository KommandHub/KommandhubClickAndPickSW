<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Event;

use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Flow\Aware\OrderPickupLocationAware;
use Kommandhub\ClickAndPickSW\Flow\Aware\PickupLocationAware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(PickupOrderPlacedEvent::class)]
class PickupOrderPlacedEventTest extends TestCase
{
    public function testExposesEventMetadataAndAwareContracts(): void
    {
        $event = $this->event();
        $available = array_keys(PickupOrderPlacedEvent::getAvailableData()->toArray());

        static::assertSame('pickup.order.placed', PickupOrderPlacedEvent::EVENT_NAME);
        static::assertSame('pickup.order.placed', $event->getName());
        static::assertContains(OrderAware::ORDER, $available);
        static::assertContains(PickupLocationAware::PICKUP_LOCATION, $available);
        static::assertContains(OrderPickupLocationAware::ORDER_PICKUP_LOCATION, $available);
        static::assertInstanceOf(OrderAware::class, $event);
        static::assertInstanceOf(PickupLocationAware::class, $event);
        static::assertInstanceOf(OrderPickupLocationAware::class, $event);
    }

    public function testExposesOrderPickupLocationAndSalesChannelData(): void
    {
        $salesChannelContext = $this->salesChannelContext();
        $event = $this->event($salesChannelContext);

        static::assertSame('0123456789abcdef0123456789abcdef', $event->getOrderId());
        static::assertSame('0123456789abcdef0123456789abcdef', $event->getOrder()->getId());
        static::assertSame('fedcba9876543210fedcba9876543210', $event->getPickupLocationId());
        static::assertSame('fedcba9876543210fedcba9876543210', $event->getPickupLocation()->getId());
        static::assertSame('cccccccccccccccccccccccccccccccc', $event->getOrderPickupLocationId());
        static::assertSame('cccccccccccccccccccccccccccccccc', $event->getOrderPickupLocation()->getId());
        static::assertSame('11111111111111111111111111111111', $event->getSalesChannelId());
        static::assertSame('22222222222222222222222222222222', $event->getCustomerGroupId());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $event->getCustomerId());
    }

    public function testBuildsAndCachesMailRecipientStructFromOrderCustomer(): void
    {
        $event = $this->event();

        $mailStruct = $event->getMailStruct();

        static::assertSame(
            ['customer@shop.test' => 'Jamie Doe'],
            $mailStruct->getRecipients()
        );
        static::assertSame($mailStruct, $event->getMailStruct());
    }

    public function testMailStructIsEmptyWhenCustomerEmailIsMissing(): void
    {
        $order = new OrderEntity();
        $order->setId('0123456789abcdef0123456789abcdef');

        $location = new PickupLocationEntity();
        $location->setId('fedcba9876543210fedcba9876543210');

        $event = new PickupOrderPlacedEvent(
            $this->salesChannelContext(),
            $order,
            $location,
            $this->pickupRecord($location)
        );

        static::assertSame([], $event->getMailStruct()->getRecipients());
    }

    public function testUsesProvidedMailRecipientStruct(): void
    {
        $mailStruct = new MailRecipientStruct(['provided@shop.test' => 'Provided Recipient']);
        $event = $this->event(null, $mailStruct);

        static::assertSame($mailStruct, $event->getMailStruct());
    }

    public function testThrowsWhenCustomerIdCannotBeResolved(): void
    {
        $order = new OrderEntity();
        $order->setId('0123456789abcdef0123456789abcdef');

        $location = new PickupLocationEntity();
        $location->setId('fedcba9876543210fedcba9876543210');

        $event = new PickupOrderPlacedEvent($this->salesChannelContext(), $order, $location, $this->pickupRecord($location));

        $this->expectExceptionObject(
            \Shopware\Core\Checkout\Cart\CartException::orderCustomerDeleted($order->getId())
        );

        $event->getCustomerId();
    }

    private function event(?SalesChannelContext $salesChannelContext = null, ?MailRecipientStruct $mailStruct = null): PickupOrderPlacedEvent
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $orderCustomer->setCustomerId('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
        $orderCustomer->setEmail('customer@shop.test');
        $orderCustomer->setFirstName('Jamie');
        $orderCustomer->setLastName('Doe');

        $order = new OrderEntity();
        $order->setId('0123456789abcdef0123456789abcdef');
        $order->setOrderCustomer($orderCustomer);

        $location = new PickupLocationEntity();
        $location->setId('fedcba9876543210fedcba9876543210');

        return new PickupOrderPlacedEvent(
            $salesChannelContext ?? $this->salesChannelContext(),
            $order,
            $location,
            $this->pickupRecord($location),
            $mailStruct
        );
    }

    private function pickupRecord(PickupLocationEntity $location): OrderPickupLocationEntity
    {
        $record = new OrderPickupLocationEntity();
        $record->setId('cccccccccccccccccccccccccccccccc');
        $record->setUniqueIdentifier('cccccccccccccccccccccccccccccccc');
        $record->setPickupLocation($location);

        return $record;
    }

    private function salesChannelContext(): SalesChannelContext
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getSalesChannelId')->willReturn('11111111111111111111111111111111');
        $salesChannelContext->method('getCustomerGroupId')->willReturn('22222222222222222222222222222222');

        return $salesChannelContext;
    }
}
