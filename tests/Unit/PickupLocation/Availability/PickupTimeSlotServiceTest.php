<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\PickupLocation\Availability;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour\PickupLocationOpeningHourCollection;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationOpeningHour\PickupLocationOpeningHourEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour\PickupLocationSpecialHourCollection;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour\PickupLocationSpecialHourEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;
use Kommandhub\ClickAndPickSW\PickupLocation\Availability\PickupLocationAvailabilityService;
use Kommandhub\ClickAndPickSW\PickupLocation\Availability\PickupTimeSlotService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PickupTimeSlotService::class)]
#[UsesClass(PickupLocationAvailabilityService::class)]
class PickupTimeSlotServiceTest extends TestCase
{
    private PickupTimeSlotService $service;

    protected function setUp(): void
    {
        $this->service = new PickupTimeSlotService(new PickupLocationAvailabilityService(), 30);
    }

    public function testGeneratesSlotsWithinIntervalRespectingStepAndTimezone(): void
    {
        // Monday 09:00–11:00 in Lagos.
        $location = $this->location('Africa/Lagos', [[1, '09:00', '11:00']]);
        $date = new \DateTimeImmutable('2024-06-03 00:00:00', new \DateTimeZone('UTC')); // a Monday
        $farPast = new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'));

        $slots = array_map(
            static fn (\DateTimeImmutable $slot): string => $slot->format('H:i'),
            $this->service->getSlots($location, $date, $farPast)
        );

        // Last start is 10:30 (10:30 + 30m = 11:00, still inside).
        static::assertSame(['09:00', '09:30', '10:00', '10:30'], $slots);
    }

    public function testMultipleIntervalsProduceSeparateSlotBlocks(): void
    {
        $location = $this->location('UTC', [[1, '09:00', '10:00'], [1, '14:00', '15:00']]);
        $date = new \DateTimeImmutable('2024-06-03 00:00:00', new \DateTimeZone('UTC'));
        $farPast = new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'));

        $slots = array_map(
            static fn (\DateTimeImmutable $slot): string => $slot->format('H:i'),
            $this->service->getSlots($location, $date, $farPast)
        );

        static::assertSame(['09:00', '09:30', '14:00', '14:30'], $slots);
    }

    public function testPastSlotsAreExcluded(): void
    {
        $location = $this->location('UTC', [[1, '09:00', '11:00']]);
        $date = new \DateTimeImmutable('2024-06-03 00:00:00', new \DateTimeZone('UTC'));
        // "now" is 09:45 UTC → only 10:00 and 10:30 remain.
        $now = new \DateTimeImmutable('2024-06-03 09:45:00', new \DateTimeZone('UTC'));

        $slots = array_map(
            static fn (\DateTimeImmutable $slot): string => $slot->format('H:i'),
            $this->service->getSlots($location, $date, $now)
        );

        static::assertSame(['10:00', '10:30'], $slots);
    }

    public function testSpecialClosureYieldsNoSlots(): void
    {
        $location = $this->location('UTC', [[2, '09:00', '17:00']]);
        $location->setSpecialHours(new PickupLocationSpecialHourCollection([
            $this->special('2024-12-24', true, null, null),
        ]));

        $date = new \DateTimeImmutable('2024-12-24 00:00:00', new \DateTimeZone('UTC')); // Tuesday
        $farPast = new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'));

        static::assertSame([], $this->service->getSlots($location, $date, $farPast));
    }

    public function testIsBookableMatchesTheSchedule(): void
    {
        $location = $this->location('Africa/Lagos', [[1, '09:00', '17:00']]);

        // 2024-06-03 09:00 UTC = 10:00 Lagos (open); 17:30 UTC = 18:30 Lagos (closed).
        static::assertTrue($this->service->isBookable($location, new \DateTimeImmutable('2024-06-03 09:00:00', new \DateTimeZone('UTC'))));
        static::assertFalse($this->service->isBookable($location, new \DateTimeImmutable('2024-06-03 17:30:00', new \DateTimeZone('UTC'))));
    }

    /**
     * @param list<array{int, string, string}> $intervals
     */
    private function location(string $timezone, array $intervals): PickupLocationEntity
    {
        $entities = [];

        foreach ($intervals as [$dayOfWeek, $open, $close]) {
            $entity = new PickupLocationOpeningHourEntity();
            $entity->setId(bin2hex(random_bytes(16)));
            $entity->setPickupLocationId('00000000000000000000000000000000');
            $entity->setDayOfWeek($dayOfWeek);
            $entity->setOpenTime($open);
            $entity->setCloseTime($close);
            $entities[] = $entity;
        }

        $location = new PickupLocationEntity();
        $location->setId(bin2hex(random_bytes(16)));
        $location->setTimezone($timezone);
        $location->setOpeningHoursSchedule(new PickupLocationOpeningHourCollection($entities));

        return $location;
    }

    private function special(string $date, bool $closed, ?string $open, ?string $close): PickupLocationSpecialHourEntity
    {
        $entity = new PickupLocationSpecialHourEntity();
        $entity->setId(bin2hex(random_bytes(16)));
        $entity->setPickupLocationId('00000000000000000000000000000000');
        $entity->setDate(new \DateTimeImmutable($date));
        $entity->setClosed($closed);
        $entity->setOpenTime($open);
        $entity->setCloseTime($close);

        return $entity;
    }
}
