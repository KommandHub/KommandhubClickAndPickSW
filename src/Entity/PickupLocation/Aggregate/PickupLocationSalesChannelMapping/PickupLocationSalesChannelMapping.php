<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSalesChannelMapping;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Mapping entity for the many-to-many relationship between pickup locations and sales channels.
 * This entity represents which pickup locations are available for which sales channels.
 */
class PickupLocationSalesChannelMapping extends Entity
{
    protected string $pickupLocationId;

    protected string $salesChannelId;

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    protected ?PickupLocationEntity $pickupLocation = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getPickupLocationId(): string
    {
        return $this->pickupLocationId;
    }

    public function setPickupLocationId(string $pickupLocationId): void
    {
        $this->pickupLocationId = $pickupLocationId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPickupLocation(): ?PickupLocationEntity
    {
        return $this->pickupLocation;
    }

    public function setPickupLocation(?PickupLocationEntity $pickupLocation): void
    {
        $this->pickupLocation = $pickupLocation;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
