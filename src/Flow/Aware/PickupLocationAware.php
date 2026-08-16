<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Flow\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\IsFlowEventAware;

/**
 * Flow Builder data contract for events that carry a pickup location. Mirrors
 * Shopware's own `*Aware` interfaces (e.g. OrderAware): any future pickup-related
 * flow event can implement this to expose the pickup location as reusable flow
 * data, restored by {@see \Kommandhub\ClickAndPickSW\Flow\Storer\PickupLocationFlowStorer}.
 */
#[IsFlowEventAware]
interface PickupLocationAware extends FlowEventAware
{
    public const PICKUP_LOCATION = 'pickupLocation';
    public const PICKUP_LOCATION_ID = 'pickupLocationId';

    public function getPickupLocationId(): string;
}
