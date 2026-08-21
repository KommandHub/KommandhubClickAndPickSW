<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PickupLocationSpecialHourEntity extends Entity
{
    use EntityIdTrait;

    protected string $pickupLocationId;

    protected \DateTimeInterface $date;

    protected bool $closed;

    protected ?string $openTime = null;

    protected ?string $closeTime = null;

    protected ?PickupLocationEntity $pickupLocation = null;

    public function getPickupLocationId(): string
    {
        return $this->pickupLocationId;
    }

    public function setPickupLocationId(string $pickupLocationId): void
    {
        $this->pickupLocationId = $pickupLocationId;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): void
    {
        $this->date = $date;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function setClosed(bool $closed): void
    {
        $this->closed = $closed;
    }

    public function getOpenTime(): ?string
    {
        return $this->openTime;
    }

    public function setOpenTime(?string $openTime): void
    {
        $this->openTime = $openTime;
    }

    public function getCloseTime(): ?string
    {
        return $this->closeTime;
    }

    public function setCloseTime(?string $closeTime): void
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
