<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\PickupLocation\Availability;

/**
 * Maps between the lower-case English weekday names used by the legacy openDays
 * JSON / the admin UI and the ISO-8601 day numbers stored in the normalized
 * schedule (1 = Monday … 7 = Sunday).
 */
final class Weekday
{
    /**
     * @var array<string, int>
     */
    public const ISO_BY_NAME = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    public static function isoFromName(string $name): ?int
    {
        return self::ISO_BY_NAME[strtolower(trim($name))] ?? null;
    }

    public static function nameFromIso(int $iso): ?string
    {
        return array_search($iso, self::ISO_BY_NAME, true) ?: null;
    }
}
