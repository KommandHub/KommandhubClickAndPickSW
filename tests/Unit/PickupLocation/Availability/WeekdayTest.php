<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\PickupLocation\Availability;

use Kommandhub\ClickAndPickSW\PickupLocation\Availability\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Weekday::class)]
class WeekdayTest extends TestCase
{
    public function testIsoFromName(): void
    {
        static::assertSame(1, Weekday::isoFromName('monday'));
        static::assertSame(7, Weekday::isoFromName('sunday'));
        static::assertSame(3, Weekday::isoFromName('  Wednesday '));
        static::assertNull(Weekday::isoFromName('funday'));
    }

    public function testNameFromIso(): void
    {
        static::assertSame('monday', Weekday::nameFromIso(1));
        static::assertSame('sunday', Weekday::nameFromIso(7));
        static::assertNull(Weekday::nameFromIso(0));
        static::assertNull(Weekday::nameFromIso(8));
    }
}
