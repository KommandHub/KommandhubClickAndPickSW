<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\PickupLocation\Availability;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;

/**
 * Generates and validates bookable pickup time slots for a location, driven by
 * the location's schedule + timezone (via {@see PickupLocationAvailabilityService}).
 *
 * The pipeline — resolve open intervals → step into slots → filter — is the
 * single seam for future rules (cutoff times, blackout periods, capacity
 * limits): add a SlotFilter rather than touching callers.
 */
class PickupTimeSlotService
{
    public const DEFAULT_STEP_MINUTES = 30;

    public function __construct(
        private readonly PickupLocationAvailabilityService $availabilityService,
        private readonly int $slotStepMinutes = self::DEFAULT_STEP_MINUTES,
    ) {
    }

    /**
     * Bookable slot start instants for a location on the given date, in the
     * location's timezone. A slot at time T is offered only when the whole
     * booking window [T, T+step) still fits inside an open interval, so the last
     * slot never runs past closing.
     *
     * @return list<\DateTimeImmutable>
     */
    public function getSlots(PickupLocationEntity $location, \DateTimeImmutable $date, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now');
        $step = new \DateInterval(sprintf('PT%dM', max(1, $this->slotStepMinutes)));

        $slots = [];

        foreach ($this->availabilityService->getOpenIntervalsForDate($location, $date) as $interval) {
            $cursor = $interval['start'];

            // Keep the full slot inside the interval: last start is end - step.
            while ($cursor->add($step) <= $interval['end']) {
                if ($cursor > $now && $this->isAllowed($location, $cursor)) {
                    $slots[] = $cursor;
                }

                $cursor = $cursor->add($step);
            }
        }

        return $slots;
    }

    /**
     * Whether a chosen instant is a valid pickup time: it must fall inside a
     * configured open interval (schedule + timezone + overrides) and pass the
     * extensible rules. Used for server-side validation before order placement.
     */
    public function isBookable(PickupLocationEntity $location, \DateTimeImmutable $when): bool
    {
        return $this->availabilityService->isOpenAt($location, $when) && $this->isAllowed($location, $when);
    }

    /**
     * Extension point for cutoff times, blackout periods and capacity limits.
     * Returns true today; override/decorate to add rules.
     */
    protected function isAllowed(PickupLocationEntity $location, \DateTimeImmutable $when): bool
    {
        return true;
    }
}
