<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSalesChannelMapping;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * Collection class for PickupLocationSalesChannelMapping entities.
 * Represents a collection of many-to-many mappings between pickup locations and sales channels.
 *
 * @extends EntityCollection<PickupLocationSalesChannelMapping>
 */
class PickupLocationSalesChannelMappingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickupLocationSalesChannelMapping::class;
    }
}

