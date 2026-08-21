<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickupLocationOpeningHourEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickupLocationId;

    protected int $dayOfWeek;

    protected string $openTime;

    protected string $closeTime;

    protected ?PickupLocationEntity $pickupLocation = null;

    public function getPickupLocationId(): string
    {
        return $this->pickupLocationId;
    }

    public function setPickupLocationId(string $pickupLocationId): void
    {
        $this->pickupLocationId = $pickupLocationId;
    }

    public function getDayOfWeek(): int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): void
    {
        $this->dayOfWeek = $dayOfWeek;
    }

    public function getOpenTime(): string
    {
        return $this->openTime;
    }

    public function setOpenTime(string $openTime): void
    {
        $this->openTime = $openTime;
    }

    public function getCloseTime(): string
    {
        return $this->closeTime;
    }

    public function setCloseTime(string $closeTime): void
    {
        $this->closeTime = $closeTime;
    }

    public function getPickupLocation(): ?PickupLocationEntity
    {
        return $this->pickupLocation;
    }

    public function setPickupLocation(?PickupLocationEntity $pickupLocation): void
    {
        $this->pickupLocation = $pickupLocation;
    }
}
