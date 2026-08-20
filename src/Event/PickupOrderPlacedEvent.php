<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Event;

use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Flow\Aware\OrderPickupLocationAware;
use Kommandhub\ClickAndPickSW\Flow\Aware\PickupLocationAware;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\CustomerGroupAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Script\Execution\Awareness\SalesChannelContextAware;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Flow Builder trigger fired when an order that carries a valid pickup location
 * is placed. Exposes both the order and the pickup location as flow data, so
 * pickup-specific flows (mails, status changes, notifications) can be built on
 * top of it. Normal delivery orders never dispatch this event.
 */
class PickupOrderPlacedEvent extends Event implements SalesChannelAware, SalesChannelContextAware, OrderAware, MailAware, CustomerAware, CustomerGroupAware, FlowEventAware, PickupLocationAware, OrderPickupLocationAware
{
    public const EVENT_NAME = 'pickup.order.placed';

    public function __construct(
        private readonly SalesChannelContext $context,
        private readonly OrderEntity $order,
        private readonly PickupLocationEntity $pickupLocation,
        private readonly OrderPickupLocationEntity $pickupOrderLocation,
        private ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class))
            ->add(PickupLocationAware::PICKUP_LOCATION, new EntityType(PickupLocationDefinition::class))
            ->add(OrderPickupLocationAware::ORDER_PICKUP_LOCATION, new EntityType(OrderPickupLocationDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getPickupLocationId(): string
    {
        return $this->pickupLocation->getId();
    }

    public function getPickupLocation(): PickupLocationEntity
    {
        return $this->pickupLocation;
    }

    public function getOrderPickupLocationId(): string
    {
        return $this->pickupOrderLocation->getId();
    }

    public function getOrderPickupLocation(): OrderPickupLocationEntity
    {
        return $this->pickupOrderLocation;
    }

    public function getCustomerId(): string
    {
        $customerId = $this->order->getOrderCustomer()?->getCustomerId();

        if (!$customerId) {
            throw CartException::orderCustomerDeleted($this->order->getId());
        }

        return $customerId;
    }

    public function getCustomerGroupId(): string
    {
        return $this->context->getCustomerGroupId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct === null) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->order->getOrderCustomer()?->getEmail() => $this->order->getOrderCustomer()?->getFirstName() . ' ' . $this->order->getOrderCustomer()?->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getSalesChannelId(): string
    {
        return $this->context->getSalesChannelId();
    }
}
