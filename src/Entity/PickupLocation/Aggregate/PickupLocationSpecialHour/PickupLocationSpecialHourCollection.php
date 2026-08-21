<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PickupLocationSpecialHourEntity>
 */
class PickupLocationSpecialHourCollection extends EntityCollection
{
    /**
     * @return list<PickupLocationSpecialHourEntity>
     */
    public function getForDate(string $isoDate): array
    {
        return array_values(
            $this->filter(
                static fn (PickupLocationSpecialHourEntity $hour): bool => $hour->getDate()->format('Y-m-d') === $isoDate
            )->getElements()
        );
    }

    protected function getExpectedClass(): string
    {
        return PickupLocationSpecialHourEntity::class;
    }
}
