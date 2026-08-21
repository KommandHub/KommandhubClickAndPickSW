<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Listener;

use Kommandhub\ClickAndPickSW\Event\PickupOrderPlacedEvent;
use Kommandhub\ClickAndPickSW\Event\PickupOrderReadyEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

readonly class BusinessEventCollectorListener
{
    /**
     * @var list<class-string>
     */
    private const FLOW_EVENTS = [
        PickupOrderPlacedEvent::class,
        PickupOrderReadyEvent::class,
    ];

    public function __construct(
        private BusinessEventCollector $businessEventCollector
    ) {
    }

    #[AsEventListener(event: BusinessEventCollectorEvent::NAME, priority: 1000)]
    public function onAddPickupFlowEvents(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        foreach (self::FLOW_EVENTS as $eventClass) {
            $definition = $this->businessEventCollector->define($eventClass);

            if (!$definition) {
                continue;
            }

            $collection->set($definition->getName(), $definition);
        }
    }
}
