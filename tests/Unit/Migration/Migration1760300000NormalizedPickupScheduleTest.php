<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Migration;

use Kommandhub\ClickAndPickSW\Migration\Migration1760300000NormalizedPickupSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the pure backfill transformation (legacy JSON → interval rows). The DDL
 * and DB writes belong to a kernel integration test.
 */
#[CoversClass(Migration1760300000NormalizedPickupSchedule::class)]
class Migration1760300000NormalizedPickupScheduleTest extends TestCase
{
    public function testTimestamp(): void
    {
        static::assertSame(1760300000, (new Migration1760300000NormalizedPickupSchedule())->getCreationTimestamp());
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{dayOfWeek: int, openTime: string, closeTime: string}> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('backfillProvider')]
    public function testBuildIntervals(array $row, array $expected): void
    {
        $migration = new Migration1760300000NormalizedPickupSchedule();
        $method = new \ReflectionMethod($migration, 'buildIntervals');

        static::assertSame($expected, $method->invoke($migration, $row));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<array{dayOfWeek: int, openTime: string, closeTime: string}>}>
     */
    public static function backfillProvider(): iterable
    {
        yield 'two days with hours' => [
            ['open_days' => '["monday","wednesday"]', 'opening_hours' => '09:00', 'closing_hours' => '17:00'],
            [
                ['dayOfWeek' => 1, 'openTime' => '09:00', 'closeTime' => '17:00'],
                ['dayOfWeek' => 3, 'openTime' => '09:00', 'closeTime' => '17:00'],
            ],
        ];

        yield 'no hours falls back to all day (preserves prior behavior)' => [
            ['open_days' => '["friday"]', 'opening_hours' => null, 'closing_hours' => null],
            [['dayOfWeek' => 5, 'openTime' => '00:00', 'closeTime' => '23:59']],
        ];

        yield 'unpadded hours are normalized' => [
            ['open_days' => '["tuesday"]', 'opening_hours' => '9:00', 'closing_hours' => '18:30'],
            [['dayOfWeek' => 2, 'openTime' => '09:00', 'closeTime' => '18:30']],
        ];

        yield 'unknown weekday names are skipped' => [
            ['open_days' => '["monday","funday"]', 'opening_hours' => '08:00', 'closing_hours' => '12:00'],
            [['dayOfWeek' => 1, 'openTime' => '08:00', 'closeTime' => '12:00']],
        ];

        yield 'empty open days yields nothing' => [
            ['open_days' => null, 'opening_hours' => '09:00', 'closing_hours' => '17:00'],
            [],
        ];

        yield 'malformed json yields nothing' => [
            ['open_days' => 'not-json', 'opening_hours' => '09:00', 'closing_hours' => '17:00'],
            [],
        ];
    }
}
