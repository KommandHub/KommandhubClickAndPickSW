<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

readonly class BusinessEventCollectorListener
{
    public function __construct(
        private BusinessEventCollector   $businessEventCollector
    ) {
    }

    #[AsEventListener(event: BusinessEventCollectorEvent::NAME, priority: 1000)]
    public function onAddPickupOrderPlacedEvent(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        $definition = $this->businessEventCollector->define(PickupOrderPlacedEvent::class);

        if (!$definition) {
            return;
        }

        $collection->set($definition->getName(), $definition);
    }
}