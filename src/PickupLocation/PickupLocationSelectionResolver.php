<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\PickupLocation;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\KommandhubClickAndPickSW;
use Kommandhub\ClickAndPickSW\Listener\SwitchContextEventListener;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves the customer's selected pickup location for the active sales-channel
 * context and validates that it still belongs to the current sales channel.
 */
final readonly class PickupLocationSelectionResolver
{
    private const LEGACY_EXTENSION_ID = 'id';

    public function __construct(
        private EntityRepository $kommandhubPickupLocationRepository,
    ) {
    }

    public function isPickupShippingMethod(SalesChannelContext $context): bool
    {
        return $context->getShippingMethod()->getId() === KommandhubClickAndPickSW::SHIPPING_METHOD_ID;
    }

    public function extractPickupLocationId(SalesChannelContext $context): ?string
    {
        $extension = $context->getExtension(SwitchContextEventListener::PICKUP_LOCATION_EXTENSION);

        if (!$extension instanceof ArrayStruct) {
            return null;
        }

        $pickupLocationId = $extension->getVars()[SwitchContextEventListener::PICKUP_LOCATION_ID]
            ?? $extension->getVars()[self::LEGACY_EXTENSION_ID]
            ?? null;

        if (!\is_string($pickupLocationId)) {
            return null;
        }

        $pickupLocationId = trim($pickupLocationId);

        return $pickupLocationId !== '' ? $pickupLocationId : null;
    }

    public function resolve(SalesChannelContext $context): ?PickupLocationEntity
    {
        $pickupLocationId = $this->extractPickupLocationId($context);

        if ($pickupLocationId === null) {
            return null;
        }

        $pickupLocation = $this->kommandhubPickupLocationRepository
            ->search(
                $this->createPickupLocationCriteria($pickupLocationId, $context->getSalesChannelId()),
                $context->getContext()
            )
            ->first();

        return $pickupLocation instanceof PickupLocationEntity ? $pickupLocation : null;
    }

    private function createPickupLocationCriteria(string $pickupLocationId, string $salesChannelId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('id', $pickupLocationId),
            new EqualsFilter('active', true),
            new EqualsFilter('salesChannels.id', $salesChannelId),
        ]));
        $criteria->setLimit(1);

        return $criteria;
    }
}
