<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PickupLocationOpeningHourEntity>
 */
class PickupLocationOpeningHourCollection extends EntityCollection
{
    /**
     * @return list<PickupLocationOpeningHourEntity>
     */
    public function getForWeekday(int $dayOfWeek): array
    {
        return array_values(
            $this->filter(static fn (PickupLocationOpeningHourEntity $hour): bool => $hour->getDayOfWeek() === $dayOfWeek)
                ->getElements()
        );
    }

    protected function getExpectedClass(): string
    {
        return PickupLocationOpeningHourEntity::class;
    }
}
