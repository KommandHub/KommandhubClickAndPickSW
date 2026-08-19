<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\PickupLocation\Availability;

use Kommandhub\ClickAndPickSW\Entity\PickupLocation\Aggregate\PickupLocationSpecialHour\PickupLocationSpecialHourEntity;
use Kommandhub\ClickAndPickSW\Entity\PickupLocation\PickupLocationEntity;

/**
 * Decides whether a pickup location is open at a given instant.
 *
 * The decision is made in the location's own IANA timezone (never the server or
 * browser zone): resolve "now" in that zone, take the local date + weekday, apply
 * any special-date override for that date, otherwise fall back to the weekly
 * schedule, and check whether the local wall-clock time falls inside an interval.
 *
 * Pure and stateless — it reads only the entity's loaded associations, so a
 * caller that has already loaded openingHoursSchedule + specialHours evaluates
 * every location without an extra query. This is the seam to extend for future
 * cutoff times, blackout periods and capacity limits.
 */
class PickupLocationAvailabilityService
{
    public const DEFAULT_TIMEZONE = 'UTC';

    public function isOpenAt(PickupLocationEntity $location, ?\DateTimeImmutable $reference = null): bool
    {
        $timezone = $this->resolveTimezone($location->getTimezone());
        $localNow = ($reference ?? new \DateTimeImmutable('now'))->setTimezone($timezone);

        $localDate = $localNow->format('Y-m-d');
        $isoWeekday = (int)$localNow->format('N');
        $localMinutes = ((int)$localNow->format('G')) * 60 + (int)$localNow->format('i');

        $specialForDate = $location->getSpecialHours()?->getForDate($localDate) ?? [];

        if ($specialForDate !== []) {
            return $this->matchesSpecialHours($specialForDate, $localMinutes);
        }

        foreach ($location->getOpeningHoursSchedule()?->getForWeekday($isoWeekday) ?? [] as $interval) {
            if ($this->withinInterval($interval->getOpenTime(), $interval->getCloseTime(), $localMinutes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the location has any opening interval on the reference local date
     * (regardless of the current time of day). This is the right check for the
     * pickup-location *selection* list: a customer must be able to choose a
     * location that opens later today, not only one open at this exact minute.
     */
    public function isOpenOnDate(PickupLocationEntity $location, ?\DateTimeImmutable $reference = null): bool
    {
        $timezone = $this->resolveTimezone($location->getTimezone());
        $localNow = ($reference ?? new \DateTimeImmutable('now'))->setTimezone($timezone);

        $localDate = $localNow->format('Y-m-d');
        $isoWeekday = (int)$localNow->format('N');

        $specialForDate = $location->getSpecialHours()?->getForDate($localDate) ?? [];

        if ($specialForDate !== []) {
            foreach ($specialForDate as $special) {
                if ($special->isClosed()) {
                    return false;
                }
            }

            foreach ($specialForDate as $special) {
                if ($special->getOpenTime() !== null && $special->getCloseTime() !== null) {
                    return true; // @codeCoverageIgnore
                }
            }

            return false;
        }

        return ($location->getOpeningHoursSchedule()?->getForWeekday($isoWeekday) ?? []) !== [];
    }

    /**
     * Filter loaded locations down to those open at the reference instant.
     *
     * @param iterable<PickupLocationEntity> $locations
     *
     * @return list<PickupLocationEntity>
     */
    public function filterOpen(iterable $locations, ?\DateTimeImmutable $reference = null): array
    {
        return $this->filterBy($locations, fn (PickupLocationEntity $l): bool => $this->isOpenAt($l, $reference));
    }

    /**
     * Filter loaded locations down to those open at any point on the reference
     * local date (the list a customer selects from).
     *
     * @param iterable<PickupLocationEntity> $locations
     *
     * @return list<PickupLocationEntity>
     */
    public function filterOpenOnDate(iterable $locations, ?\DateTimeImmutable $reference = null): array
    {
        return $this->filterBy($locations, fn (PickupLocationEntity $l): bool => $this->isOpenOnDate($l, $reference));
    }

    /**
     * @param iterable<PickupLocationEntity> $locations
     * @param callable(PickupLocationEntity): bool $predicate
     *
     * @return list<PickupLocationEntity>
     */
    private function filterBy(iterable $locations, callable $predicate): array
    {
        $matched = [];

        foreach ($locations as $location) {
            if ($predicate($location)) {
                $matched[] = $location;
            }
        }

        return $matched;
    }

    /**
     * @param list<PickupLocationSpecialHourEntity> $specialForDate
     */
    private function matchesSpecialHours(array $specialForDate, int $localMinutes): bool
    {
        // Any closure row on the date closes the location for the whole date,
        // regardless of other rows.
        foreach ($specialForDate as $special) {
            if ($special->isClosed()) {
                return false;
            }
        }

        foreach ($specialForDate as $special) {
            $open = $special->getOpenTime();
            $close = $special->getCloseTime();

            if ($open !== null && $close !== null && $this->withinInterval($open, $close, $localMinutes)) {
                return true;
            }
        }

        return false;
    }

    private function withinInterval(string $open, string $close, int $localMinutes): bool
    {
        $openMinutes = $this->toMinutes($open);
        $closeMinutes = $this->toMinutes($close);

        if ($openMinutes === null || $closeMinutes === null) {
            return false;
        }

        // Closing time is exclusive: [open, close). A location "09:00–18:00" is
        // considered closed exactly at 18:00.
        if ($closeMinutes > $openMinutes) {
            return $localMinutes >= $openMinutes && $localMinutes < $closeMinutes;
        }

        // close <= open → interval wraps past midnight (e.g. 22:00–02:00).
        if ($closeMinutes < $openMinutes) {
            return $localMinutes >= $openMinutes || $localMinutes < $closeMinutes;
        }

        // Zero-length interval (open === close) is never open.
        return false;
    }

    private function toMinutes(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches) !== 1) {
            return null;
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];

        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return null; // @codeCoverageIgnore
        }

        return $hours * 60 + $minutes;
    }

    private function resolveTimezone(?string $timezone): \DateTimeZone
    {
        if ($timezone === null || $timezone === '') {
            return new \DateTimeZone(self::DEFAULT_TIMEZONE);
        }

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception) {
            return new \DateTimeZone(self::DEFAULT_TIMEZONE);
        }
    }
}
