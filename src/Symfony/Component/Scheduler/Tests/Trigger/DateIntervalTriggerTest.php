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

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Trigger\DateIntervalTrigger;
use Symfony\Component\Scheduler\Trigger\DatePeriodTrigger;

class DateIntervalTriggerTest extends DatePeriodTriggerTest
{
    /**
     * @dataProvider provideForConstructor
     */
    public function testConstructor(DateIntervalTrigger $trigger)
    {
        $run = new \DateTimeImmutable('2222-02-22 13:34:00');

        $this->assertSame('2222-02-23 13:34:00', $trigger->getNextRunDate($run)->format('Y-m-d H:i:s'));
    }

    public static function provideForConstructor(): iterable
    {
        $from = new \DateTimeImmutable($now = '2222-02-22 13:34:00');
        $until = new \DateTimeImmutable($farFuture = '3000-01-01');
        $day = new \DateInterval('P1D');

        return [
            [new DateIntervalTrigger(86400, $from, $until)],
            [new DateIntervalTrigger('86400', $from, $until)],
            [new DateIntervalTrigger('P1D', $from, $until)],
            [new DateIntervalTrigger($day, $now, $farFuture)],
            [new DateIntervalTrigger($day, $now)],
        ];
    }

    /**
     * @dataProvider getInvalidIntervals
     */
    public function testInvalidInterval($interval)
    {
        $this->expectException(InvalidArgumentException::class);

        new DateIntervalTrigger($interval, $now = new \DateTimeImmutable(), $now->modify('1 day'));
    }

    public static function getInvalidIntervals(): iterable
    {
        yield ['wrong'];
        yield ['3600.5'];
        yield [-3600];
    }

    /**
     * @dataProvider providerGetNextRunDateAgain
     */
    public function testGetNextRunDateAgain(DateIntervalTrigger $trigger, \DateTimeImmutable $lastRun, ?\DateTimeImmutable $expected)
    {
        $this->assertEquals($expected, $trigger->getNextRunDate($lastRun));
    }

    public static function providerGetNextRunDateAgain(): iterable
    {
        $trigger = new DateIntervalTrigger(
            600,
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
            new \DateTimeImmutable('2020-02-20T03:00:00+02')
        );

        yield [
            $trigger,
            new \DateTimeImmutable('@0'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T01:59:59.999999+02'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:05:00+02'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:49:59.999999+02'),
            new \DateTimeImmutable('2020-02-20T02:50:00+02'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:50:00+02'),
            null,
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T03:00:00+02'),
            null,
        ];

        $trigger = new DateIntervalTrigger(
            600,
            new \DateTimeImmutable('2020-02-20T02:00:00Z'),
            new \DateTimeImmutable('2020-02-20T03:01:00Z')
        );

        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:59:59.999999Z'),
            new \DateTimeImmutable('2020-02-20T03:00:00Z'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T03:00:00Z'),
            null,
        ];
    }

    protected static function createTrigger(string $interval): DatePeriodTrigger
    {
        return new DateIntervalTrigger($interval, '2023-03-19 13:45', '2023-06-19');
    }
}
