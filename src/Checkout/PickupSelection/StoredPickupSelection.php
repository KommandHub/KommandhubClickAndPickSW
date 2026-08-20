<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Checkout\PickupSelection;

/**
 * The customer's pickup selection as persisted in the sales-channel context —
 * raw, un-resolved values (the location id, and the chosen time/comment as
 * strings). Distinct from {@see PickupSelection}, which is the resolved choice
 * (a loaded location + parsed time) used once at the checkout gate.
 */
final readonly class StoredPickupSelection
{
    public function __construct(
        public ?string $pickupLocationId = null,
        public ?string $pickupTime = null,
        public ?string $comment = null,
    ) {
    }

    /**
     * No location chosen — there is nothing to expose to the storefront or to
     * persist as a selection.
     */
    public function isEmpty(): bool
    {
        return $this->pickupLocationId === null;
    }
}
