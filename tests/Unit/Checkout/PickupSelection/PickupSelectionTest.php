<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupSelection;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupSelection::class)]
class PickupSelectionTest extends TestCase
{
    public function testExposesLocationTimeAndComment(): void
    {
        $location = new PickupLocationEntity();
        $location->setId('fedcba9876543210fedcba9876543210');
        $time = new \DateTimeImmutable('2024-06-03T10:00:00+00:00');

        $selection = new PickupSelection($location, $time, 'Ring the bell');

        static::assertSame($location, $selection->pickupLocation);
        static::assertSame($time, $selection->pickupTime);
        static::assertSame('Ring the bell', $selection->comment);
    }

    public function testTimeAndCommentAreOptional(): void
    {
        $location = new PickupLocationEntity();
        $selection = new PickupSelection($location);

        static::assertNull($selection->pickupTime);
        static::assertNull($selection->comment);
    }
}
