<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\OrderPickupLocation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrderPickupLocationEntity>
 */
class OrderPickupLocationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrderPickupLocationEntity::class;
    }
}
