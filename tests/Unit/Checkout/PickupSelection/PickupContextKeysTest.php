<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Checkout\PickupSelection;

use Kommandhub\ClickAndPickSW\Checkout\PickupSelection\PickupContextKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupContextKeys::class)]
class PickupContextKeysTest extends TestCase
{
    public function testExposesTheContextKeys(): void
    {
        static::assertSame('pickupLocation', PickupContextKeys::EXTENSION);
        static::assertSame('pickupLocationId', PickupContextKeys::LOCATION_ID);
        static::assertSame('pickupTime', PickupContextKeys::TIME);
        static::assertSame('pickupComment', PickupContextKeys::COMMENT);
        static::assertSame('id', PickupContextKeys::LEGACY_ID);
    }

    public function testIsNotInstantiable(): void
    {
        $constructor = (new \ReflectionClass(PickupContextKeys::class))->getConstructor();

        static::assertNotNull($constructor);
        static::assertTrue($constructor->isPrivate());

        // Exercise the (otherwise unreachable) private constructor body.
        $instance = (new \ReflectionClass(PickupContextKeys::class))->newInstanceWithoutConstructor();
        $constructor->invoke($instance);

        static::assertInstanceOf(PickupContextKeys::class, $instance);
    }
}
