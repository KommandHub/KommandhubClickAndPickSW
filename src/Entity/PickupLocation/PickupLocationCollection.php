<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PickupLocationEntity>
 */
class PickupLocationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return PickupLocationEntity::class;
    }
}
