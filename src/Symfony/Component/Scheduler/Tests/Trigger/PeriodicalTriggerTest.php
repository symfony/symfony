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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;

class PeriodicalTriggerTest extends TestCase
{
    /**
     * @dataProvider providerGetNextRunDate
     */
    public function testGetNextRunDate(PeriodicalTrigger $periodicalMessage, \DateTimeImmutable $lastRun, ?\DateTimeImmutable $expected)
    {
        $this->assertEquals($expected, $periodicalMessage->getNextRunDate($lastRun));
    }

    public static function providerGetNextRunDate(): iterable
    {
        $periodicalMessage = new PeriodicalTrigger(
            600,
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
            new \DateTimeImmutable('2020-02-20T03:00:00+02')
        );

        yield [
            $periodicalMessage,
            new \DateTimeImmutable('@0'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T01:59:59.999999+02'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T02:00:00+02'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T02:05:00+02'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T02:49:59.999999+02'),
            new \DateTimeImmutable('2020-02-20T02:50:00+02'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T02:50:00+02'),
            null,
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T03:00:00+02'),
            null,
        ];

        $periodicalMessage = new PeriodicalTrigger(
            600,
            new \DateTimeImmutable('2020-02-20T02:00:00Z'),
            new \DateTimeImmutable('2020-02-20T03:01:00Z')
        );

        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T02:59:59.999999Z'),
            new \DateTimeImmutable('2020-02-20T03:00:00Z'),
        ];
        yield [
            $periodicalMessage,
            new \DateTimeImmutable('2020-02-20T03:00:00Z'),
            null,
        ];
    }

    public function testConstructors()
    {
        $firstRun = new \DateTimeImmutable($now = '2222-02-22');
        $priorTo = new \DateTimeImmutable($farFuture = '3000-01-01');
        $day = new \DateInterval('P1D');

        $message = new PeriodicalTrigger(86400, $firstRun, $priorTo);

        $this->assertEquals($message, PeriodicalTrigger::create(86400, $firstRun, $priorTo));
        $this->assertEquals($message, PeriodicalTrigger::create('86400', $firstRun, $priorTo));
        $this->assertEquals($message, PeriodicalTrigger::create('P1D', $firstRun, $priorTo));
        $this->assertEquals($message, PeriodicalTrigger::create($day, $now, $farFuture));
        $this->assertEquals($message, PeriodicalTrigger::create($day, $now));

        $this->assertEquals($message, PeriodicalTrigger::fromPeriod(new \DatePeriod($firstRun, $day, $priorTo)));
        $this->assertEquals($message, PeriodicalTrigger::fromPeriod(new \DatePeriod($firstRun->sub($day), $day, $priorTo, \DatePeriod::EXCLUDE_START_DATE)));
        $this->assertEquals($message, PeriodicalTrigger::fromPeriod(new \DatePeriod($firstRun, $day, 284107)));
        $this->assertEquals($message, PeriodicalTrigger::fromPeriod(new \DatePeriod($firstRun->sub($day), $day, 284108, \DatePeriod::EXCLUDE_START_DATE)));
    }

    public function testTooBigInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The interval for a periodical message is too big');

        PeriodicalTrigger::create(\PHP_INT_MAX.'0', new \DateTimeImmutable('2002-02-02'));
    }

    /**
     * @dataProvider getInvalidIntervals
     */
    public function testInvalidInterval($interval)
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodicalTrigger::create($interval, $now = new \DateTimeImmutable(), $now->modify('1 day'));
    }

    public static function getInvalidIntervals(): iterable
    {
        yield ['wrong'];
        yield ['3600.5'];
        yield [0];
        yield [-3600];
    }

    public function testNegativeInterval()
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodicalTrigger::create('wrong', $now = new \DateTimeImmutable(), $now->modify('1 day'));
    }
}
