<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Event;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationDefinition;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\Flow\Aware\PickupLocationAware;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Flow Builder trigger fired when a pickup order's delivery enters the "ready"
 * state. Dispatched only for orders that carry a valid pickup location; normal
 * delivery orders and unrelated state transitions never dispatch it.
 *
 * Exposes the order and the pickup location as flow data, reusing the shared
 * {@see PickupLocationAware} contract and its storer.
 */
class PickupOrderReadyEvent extends Event implements SalesChannelAware, OrderAware, MailAware, CustomerAware, FlowEventAware, PickupLocationAware
{
    public const EVENT_NAME = 'pickup.order.ready';

    public function __construct(
        private readonly OrderEntity $order,
        private readonly PickupLocationEntity $pickupLocation,
        private readonly Context $context,
        private ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(OrderAware::ORDER, new EntityType(OrderDefinition::class))
            ->add(PickupLocationAware::PICKUP_LOCATION, new EntityType(PickupLocationDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getPickupLocationId(): string
    {
        return $this->pickupLocation->getId();
    }

    public function getPickupLocation(): PickupLocationEntity
    {
        return $this->pickupLocation;
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getCustomerId(): string
    {
        $customerId = $this->order->getOrderCustomer()?->getCustomerId();

        if (!$customerId) {
            throw OrderException::orderCustomerDeleted($this->order->getId());
        }

        return $customerId;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct === null) {
            $orderCustomer = $this->order->getOrderCustomer();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $orderCustomer?->getEmail() => $orderCustomer?->getFirstName() . ' ' . $orderCustomer?->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }
}
