<?php

declare(strict_types=1);

namespace Kommandhub\ClickAndPickSW\Tests\Unit\Twig;

use Kommandhub\ClickAndPickSW\Twig\FormattedCalendarDays;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\TwigFunction;

#[CoversClass(FormattedCalendarDays::class)]
class FormattedCalendarDaysTest extends TestCase
{
    private FormattedCalendarDays $extension;

    protected function setUp(): void
    {
        // Translator returns the weekday key back (last snippet segment), so the
        // assertions read in terms of the day names the extension was given.
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => substr($id, (int)strrpos($id, '.') + 1)
        );

        $this->extension = new FormattedCalendarDays($translator);
    }

    /**
     * @param array<int, mixed>|null $input
     */
    #[DataProvider('calendarDaysProvider')]
    public function testFormatCalendarDays(?array $input, string $expected): void
    {
        static::assertSame($expected, $this->extension->formatCalendarDays($input));
    }

    public function testRegistersTwigFunction(): void
    {
        $functions = $this->extension->getFunctions();

        static::assertCount(1, $functions);
        static::assertInstanceOf(TwigFunction::class, $functions[0]);
        static::assertSame('format_calendar_days', $functions[0]->getName());
    }

    /**
     * @return iterable<string, array{0: array<int, mixed>|null, 1: string}>
     */
    public static function calendarDaysProvider(): iterable
    {
        yield 'null is empty' => [null, ''];
        yield 'empty array is empty' => [[], ''];
        yield 'single day' => [['monday'], 'monday'];
        yield 'continuous range collapses to first - last' => [
            ['monday', 'tuesday', 'wednesday'],
            'monday - wednesday',
        ];
        yield 'range is detected regardless of input order' => [
            ['wednesday', 'monday', 'tuesday'],
            'monday - wednesday',
        ];
        yield 'gap is listed, not ranged' => [
            ['monday', 'wednesday'],
            'monday, wednesday',
        ];
        yield 'weekend wrap-around is not continuous (sorted Mon-Sun)' => [
            ['saturday', 'sunday', 'monday'],
            'monday, saturday, sunday',
        ];
        yield 'duplicates are removed' => [
            ['monday', 'monday', 'tuesday'],
            'monday - tuesday',
        ];
        yield 'unknown and empty values are ignored' => [
            ['monday', 'notaday', '', 'tuesday'],
            'monday - tuesday',
        ];
        yield 'non scalar values are ignored' => [
            ['monday', new \stdClass(), 'tuesday'],
            'monday - tuesday',
        ];
        yield 'casing and whitespace are normalised' => [
            [' Monday ', 'TUESDAY'],
            'monday - tuesday',
        ];
    }
}
