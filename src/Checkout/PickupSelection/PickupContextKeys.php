<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Checkout\PickupSelection;

/**
 * Canonical keys for the customer's pickup selection as it lives in the
 * sales-channel context: the payload keys persisted in `sales_channel_api_context`
 * (alongside Shopware's own `shippingMethodId`/`paymentMethodId`) and the name of
 * the context extension the storefront reads.
 *
 * Single source of truth for these strings so the storage, the switch listener
 * and the resolver never drift apart.
 */
final class PickupContextKeys
{
    /**
     * Context extension name exposed to the storefront (`context.extensions.pickupLocation`).
     */
    public const EXTENSION = 'pickupLocation';

    public const LOCATION_ID = 'pickupLocationId';
    public const TIME = 'pickupTime';
    public const COMMENT = 'pickupComment';

    /**
     * Legacy extension key that once carried the location id; still read when
     * restoring older context payloads.
     */
    public const LEGACY_ID = 'id';

    private function __construct()
    {
    }
}
