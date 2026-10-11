<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Trigger\ExcludeTimeOfDayTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class ExcludeTimeOfDayTriggerTest extends TestCase
{
    public function testTheWindowIsExcludedOnEveryDay()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '01:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-01-01T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T06:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T05:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-02T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-02T00:00:00Z')));
    }

    public function testTheWindowWrapsOverMidnightWhenItEndsBeforeItStarts()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '23:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-01-02T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T22:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-02T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-02T01:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T13:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T12:00:00Z')));
    }

    public function testBothEndsOfTheWindowAreExcluded()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '01:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-01-01T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T03:00:00Z')));
    }

    public function testBothEndsOfAWindowWrappingOverMidnightAreExcluded()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '23:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-01-02T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T22:00:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-02T05:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-02T03:00:00Z')));
    }

    public function testTheWindowKeepsItsLocalTimesOnTheDayAnHourIsSkipped()
    {
        $berlin = new \DateTimeZone('Europe/Berlin');
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '02:00', '02:30', $berlin);

        // on 2026-03-29 the clocks in Berlin go straight from 02:00 to 03:00, so the window never happens
        $this->assertEquals(new \DateTimeImmutable('2026-03-29T01:15:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-03-29T00:15:00Z')));
    }

    public function testTheWindowKeepsItsLocalTimesOnTheDayAnHourIsRepeated()
    {
        $berlin = new \DateTimeZone('Europe/Berlin');
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '02:00', '02:30', $berlin);

        // on 2026-10-25 Berlin runs 02:15 twice, at 00:15 and at 01:15 UTC, and both are excluded
        $this->assertEquals(new \DateTimeImmutable('2026-10-25T02:30:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-24T23:15:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2026-10-25T02:30:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-25T00:15:00Z')));
    }

    #[TestWith(['02:00'])]
    #[TestWith(['02:30'])]
    public function testTheWindowEndsWhenTheClocksSkipItsEnd(string $until)
    {
        $trigger = new ExcludeTimeOfDayTrigger(new PeriodicalTrigger(900, new \DateTimeImmutable('2026-03-29T00:00:00Z')), '01:00', $until, new \DateTimeZone('Europe/Paris'));

        // on 2026-03-29 the clocks in Paris go straight from 02:00 to 03:00, so 03:00 is the first time of day after the window
        $this->assertEquals(new \DateTimeImmutable('2026-03-29T01:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-03-29T00:00:00Z')));
    }

    public function testTheWindowEndsOnTimeTheDayAfterTheClocksSkippedItsEnd()
    {
        $trigger = new ExcludeTimeOfDayTrigger(new PeriodicalTrigger(900, new \DateTimeImmutable('2026-03-29T00:00:00Z')), '22:00', '02:30', new \DateTimeZone('Europe/Paris'));

        $this->assertEquals(new \DateTimeImmutable('2026-03-30T00:45:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-03-29T20:15:00Z')));
    }

    public function testTheTimesOfDayARepeatedHourBringsBackBeforeTheWindowAreKept()
    {
        $trigger = new ExcludeTimeOfDayTrigger(new PeriodicalTrigger(900, new \DateTimeImmutable('2026-10-25T00:00:00Z')), '02:30', '03:00', new \DateTimeZone('Europe/Paris'));

        // on 2026-10-25 the clocks in Paris go back from 03:00 to 02:00 at 01:00 UTC, so 02:00 comes back before the window starts again
        $this->assertEquals(new \DateTimeImmutable('2026-10-25T01:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-25T00:30:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2026-10-25T02:15:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-25T01:15:00Z')));
    }

    public function testARunMicrosecondsAfterTheEndOfTheWindowIsKept()
    {
        $trigger = new ExcludeTimeOfDayTrigger(new PeriodicalTrigger(60, new \DateTimeImmutable('2024-01-01T00:00:00.25Z')), '01:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-01-01T04:00:00.25Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:59:00.25Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T04:00:00.25Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T03:59:00.25Z')));
    }

    public function testTheInnerTriggerKeepsReceivingDatesInItsOwnTimezone()
    {
        $timezones = [];
        $inner = $this->createStub(TriggerInterface::class);
        $inner
            ->method('getNextRunDate')
            ->willReturnCallback(static function (\DateTimeImmutable $run) use (&$timezones) {
                $timezones[] = $run->getTimezone()->getName();

                return $run->modify('+1 hour');
            });

        $trigger = new ExcludeTimeOfDayTrigger($inner, '09:00', '11:00', new \DateTimeZone('Asia/Tokyo'));

        $this->assertEquals(new \DateTimeImmutable('2024-01-01T03:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
        $this->assertSame(['Z', 'Z'], $timezones);
    }

    public function testTheWindowIsReadInTheTimezoneOfTheRunDateByDefault()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '01:00', '04:00');

        $tokyo = new \DateTimeImmutable('2024-01-01T00:00:00', new \DateTimeZone('Asia/Tokyo'));

        // 01:00 in Tokyo is inside the window, while the same instant is 16:00 the day before in UTC
        $this->assertEquals($tokyo->modify('+5 hours'), $trigger->getNextRunDate($tokyo));
        $this->assertEquals(new \DateTimeImmutable('2023-12-31T16:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2023-12-31T15:00:00Z')));
    }

    public function testTheSearchSpansAYearOfExcludedWindows()
    {
        $inner = $this->createStub(TriggerInterface::class);
        $inner
            ->method('getNextRunDate')
            ->willReturnCallback(static fn (\DateTimeImmutable $run) => $run < new \DateTimeImmutable('2024-12-01T00:00:00Z')
                ? $run->modify('tomorrow 02:00')
                : $run->modify('tomorrow 12:00'));

        $trigger = new ExcludeTimeOfDayTrigger($inner, '01:00', '04:00');

        $this->assertEquals(new \DateTimeImmutable('2024-12-02T12:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
    }

    public function testTheWindowIsReadInTheGivenTimezone()
    {
        $trigger = new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '23:00', '04:00', new \DateTimeZone('Europe/Skopje'));

        // 22:30 UTC is 23:30 in Skopje, inside the window, which ends at 03:00 UTC
        $this->assertEquals(new \DateTimeImmutable('2024-01-02T04:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T21:30:00Z')));
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T21:00:00Z'), $trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T20:00:00Z')));
    }

    public function testThereIsNoNextRunDateWhenTheInnerTriggerOnlyRunsInsideTheWindow()
    {
        $inner = $this->createStub(TriggerInterface::class);
        $inner
            ->method('getNextRunDate')
            ->willReturnCallback(static fn (\DateTimeImmutable $run) => $run->modify('tomorrow 02:00'));

        $trigger = new ExcludeTimeOfDayTrigger($inner, '01:00', '04:00');

        $this->assertNull($trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
    }

    public function testThereIsNoNextRunDateWhenTheInnerTriggerHasNone()
    {
        $inner = $this->createStub(TriggerInterface::class);
        $inner->method('getNextRunDate')->willReturn(null);

        $trigger = new ExcludeTimeOfDayTrigger($inner, '01:00', '04:00');

        $this->assertNull($trigger->getNextRunDate(new \DateTimeImmutable('2024-01-01T00:00:00Z')));
    }

    public function testToString()
    {
        $inner = $this->createStub(TriggerInterface::class);
        $inner->method('__toString')->willReturn('every hour');

        $this->assertSame('every hour, excluding every day from 23:00:00 until 04:00:00', (string) new ExcludeTimeOfDayTrigger($inner, '23:00', '04:00'));
        $this->assertSame('every hour, excluding every day from 23:00:00 until 04:30:15 in Europe/Skopje', (string) new ExcludeTimeOfDayTrigger($inner, '23:00', '04:30:15', new \DateTimeZone('Europe/Skopje')));
    }

    #[TestWith(['24:00'])]
    #[TestWith(['25:00'])]
    #[TestWith(['23:60'])]
    #[TestWith(['23:00:60'])]
    #[TestWith(['23h00'])]
    #[TestWith(['midnight'])]
    #[TestWith([''])]
    public function testTimeOfDayMustBeValid(string $time)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "$until" argument of "Symfony\Component\Scheduler\Trigger\ExcludeTimeOfDayTrigger" must be a time of day as "H:i" or "H:i:s", got "%s".', $time));

        new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '23:00', $time);
    }

    public function testTheWindowMustNotBeEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "$from" and "$until" arguments of "Symfony\Component\Scheduler\Trigger\ExcludeTimeOfDayTrigger" must not be the same time of day, got "23:00:00".');

        new ExcludeTimeOfDayTrigger($this->hourlyTrigger(), '23:00', '23:00:00');
    }

    private function hourlyTrigger(): TriggerInterface
    {
        $inner = $this->createStub(TriggerInterface::class);
        $inner
            ->method('getNextRunDate')
            ->willReturnCallback(static fn (\DateTimeImmutable $run) => $run->modify('+1 hour'));

        return $inner;
    }
}
