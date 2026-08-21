<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;

/**
 * Immutable value object: the customer's resolved pickup choice for an order —
 * the (validated) location plus the chosen local pickup time and any
 * instructions. The single carrier of pickup selection between checkout and the
 * order pickup record.
 */
final readonly class PickupSelection
{
    public function __construct(
        public PickupLocationEntity $pickupLocation,
        public ?\DateTimeImmutable $pickupTime = null,
        public ?string $comment = null,
    ) {
    }
}
