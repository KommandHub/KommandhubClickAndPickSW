<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Flow\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\IsFlowEventAware;

/**
 * Flow Builder data contract for events that carry an order's pickup record
 * ({@see \Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation\OrderPickupLocationEntity}),
 * the single source of truth for pickup data. Exposes the chosen pickup time and
 * customer instructions (and the linked location via its association) as reusable
 * flow data — restored by
 * {@see \Kommandhub\ClickAndPickSW\Flow\Storer\OrderPickupLocationFlowStorer}.
 *
 * Separate from {@see PickupLocationAware}: that one carries the location itself
 * (used to address the pickup-notification mail), this one carries the order's
 * appointment record built on the new entity extension.
 */
#[IsFlowEventAware]
interface OrderPickupLocationAware extends FlowEventAware
{
    public const ORDER_PICKUP_LOCATION = 'pickupOrderLocation';
    public const ORDER_PICKUP_LOCATION_ID = 'pickupOrderLocationId';

    public function getOrderPickupLocationId(): string;
}
