<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\StoredPickupSelection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StoredPickupSelection::class)]
class StoredPickupSelectionTest extends TestCase
{
    public function testHoldsTheStoredValues(): void
    {
        $selection = new StoredPickupSelection('loc-id', '2024-06-03T10:00:00+00:00', 'Ring the bell');

        static::assertSame('loc-id', $selection->pickupLocationId);
        static::assertSame('2024-06-03T10:00:00+00:00', $selection->pickupTime);
        static::assertSame('Ring the bell', $selection->comment);
        static::assertFalse($selection->isEmpty());
    }

    public function testIsEmptyWhenNoLocation(): void
    {
        static::assertTrue((new StoredPickupSelection())->isEmpty());
        // A time/comment without a location is still empty (nothing selectable).
        static::assertTrue((new StoredPickupSelection(null, '2024-06-03T10:00:00+00:00'))->isEmpty());
    }
}
