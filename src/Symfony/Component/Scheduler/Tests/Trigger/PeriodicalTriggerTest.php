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
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class PeriodicalTriggerTest extends TestCase
{
    /**
     * @dataProvider provideForConstructor
     */
    public function testConstructor(PeriodicalTrigger $trigger, bool $optimizable = true)
    {
        $run = new \DateTimeImmutable('2922-02-22 12:34:00+00:00');

        $this->assertSame('2922-02-23 13:34:00+01:00', $trigger->getNextRunDate($run)->format('Y-m-d H:i:sP'));

        if ($optimizable) {
            // test that we are using the fast algorithm for short period of time
            $p = new \ReflectionProperty($trigger, 'intervalInSeconds');
            $this->assertNotSame(0, $p->getValue($trigger));
        }
    }

    public static function provideForConstructor(): iterable
    {
        $from = new \DateTimeImmutable($now = '2022-02-22 13:34:00+01:00');
        $until = new \DateTimeImmutable($farFuture = '3000-01-01');

        yield [new PeriodicalTrigger(86400, $from, $until)];
        yield [new PeriodicalTrigger('86400', $from, $until)];
        yield [new PeriodicalTrigger('1 day', $from, $until), false];
        yield [new PeriodicalTrigger('24 hours', $from, $until)];
        yield [new PeriodicalTrigger('1440 minutes', $from, $until)];
        yield [new PeriodicalTrigger('86400 seconds', $from, $until)];
        yield [new PeriodicalTrigger('1day', $from, $until), false];
        yield [new PeriodicalTrigger('24hours', $from, $until)];
        yield [new PeriodicalTrigger('1440minutes', $from, $until)];
        yield [new PeriodicalTrigger('86400seconds', $from, $until)];
        yield [new PeriodicalTrigger('P1D', $from, $until), false];
        yield [new PeriodicalTrigger('PT24H', $from, $until)];
        yield [new PeriodicalTrigger('PT1440M', $from, $until)];
        yield [new PeriodicalTrigger('PT86400S', $from, $until)];
        yield [new PeriodicalTrigger(new \DateInterval('P1D'), $now, $farFuture), false];
        yield [new PeriodicalTrigger(new \DateInterval('P1D'), $now), false];
    }

    /**
     * @dataProvider getInvalidIntervals
     */
    public function testInvalidInterval($interval, $expectedExceptionMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        new PeriodicalTrigger($interval, $now = new \DateTimeImmutable(), $now->modify('1 day'));
    }

    public static function getInvalidIntervals(): iterable
    {
        yield ['wrong', 'Unknown or bad format (wrong) at position 0 (w): The timezone could not be found in the database'];
        yield ['3600.5', 'Unknown or bad format (3600.5) at position 5 (5): Unexpected character'];
        yield ['-3600', 'Unknown or bad format (-3600) at position 3 (0): Unexpected character'];
        yield [-3600, 'The "$interval" argument must be greater than zero.'];
        yield ['0', 'The "$interval" argument must be greater than zero.'];
        yield [0, 'The "$interval" argument must be greater than zero.'];
    }

    /**
     * @dataProvider provideForToString
     */
    public function testToString(string $expected, PeriodicalTrigger $trigger)
    {
        $this->assertSame($expected, (string) $trigger);
    }

    public static function provideForToString()
    {
        $from = new \DateTimeImmutable('2022-02-22 13:34:00+00:00');
        $until = new \DateTimeImmutable('3000-01-01');

        yield ['every 20 seconds', new PeriodicalTrigger(20, $from, $until)];
        yield ['every 20 seconds', new PeriodicalTrigger('20', $from, $until)];
        yield ['every 2 seconds (PT2S)', new PeriodicalTrigger('PT2S', $from, $until)];
        yield ['every 20 seconds', new PeriodicalTrigger('20 seconds', $from, $until)];
        yield ['every 4 minutes 20 seconds', new PeriodicalTrigger('4 minutes 20 seconds', $from, $until)];
        yield ['every 2 hours', new PeriodicalTrigger('2 hours', $from, $until)];
        yield ['every 2 seconds', new PeriodicalTrigger(new \DateInterval('PT2S'), $from, $until)];
        yield ['DateInterval', new PeriodicalTrigger(new \DateInterval('P1D'), $from, $until)];
        yield ['last day of next month', new PeriodicalTrigger(\DateInterval::createFromDateString('last day of next month'), $from, $until)];
    }

    /**
     * @dataProvider providerGetNextRunDates
     */
    public function testGetNextRunDates(\DateTimeImmutable $from, TriggerInterface $trigger, array $expected, int $count)
    {
        $this->assertEquals($expected, $this->getNextRunDates($from, $trigger, $count));
    }

    public static function providerGetNextRunDates(): iterable
    {
        yield [
            new \DateTimeImmutable('2023-03-19 13:45'),
            self::createTrigger('next tuesday'),
            [
                new \DateTimeImmutable('2023-03-21 13:45:00'),
                new \DateTimeImmutable('2023-03-28 13:45:00'),
                new \DateTimeImmutable('2023-04-04 13:45:00'),
                new \DateTimeImmutable('2023-04-11 13:45:00'),
                new \DateTimeImmutable('2023-04-18 13:45:00'),
                new \DateTimeImmutable('2023-04-25 13:45:00'),
                new \DateTimeImmutable('2023-05-02 13:45:00'),
                new \DateTimeImmutable('2023-05-09 13:45:00'),
                new \DateTimeImmutable('2023-05-16 13:45:00'),
                new \DateTimeImmutable('2023-05-23 13:45:00'),
                new \DateTimeImmutable('2023-05-30 13:45:00'),
                new \DateTimeImmutable('2023-06-06 13:45:00'),
                new \DateTimeImmutable('2023-06-13 13:45:00'),
            ],
            20,
        ];

        yield [
            new \DateTimeImmutable('2023-03-19 13:45'),
            self::createTrigger('last day of next month'),
            [
                new \DateTimeImmutable('2023-04-30 13:45:00'),
                new \DateTimeImmutable('2023-05-31 13:45:00'),
            ],
            20,
        ];

        yield [
            new \DateTimeImmutable('2023-03-19 13:45'),
            self::createTrigger('first monday of next month'),
            [
                new \DateTimeImmutable('2023-04-03 13:45:00'),
                new \DateTimeImmutable('2023-05-01 13:45:00'),
                new \DateTimeImmutable('2023-06-05 13:45:00'),
            ],
            20,
        ];
    }

    /**
     * @dataProvider providerGetNextRunDateAgain
     */
    public function testGetNextRunDateAgain(PeriodicalTrigger $trigger, \DateTimeImmutable $lastRun, ?\DateTimeImmutable $expected)
    {
        $this->assertEquals($expected, $trigger->getNextRunDate($lastRun));
    }

    public static function providerGetNextRunDateAgain(): iterable
    {
        $trigger = new PeriodicalTrigger(
            600,
            new \DateTimeImmutable('2020-02-20T02:00:00+02:00'),
            new \DateTimeImmutable('2020-02-20T03:00:00+02:00')
        );

        yield [
            $trigger,
            new \DateTimeImmutable('@0'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T01:40:00+02:00'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T01:59:00+02:00'),
            new \DateTimeImmutable('2020-02-20T02:00:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:00:00+02:00'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:05:00+02:00'),
            new \DateTimeImmutable('2020-02-20T02:10:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:49:59.999999+02:00'),
            new \DateTimeImmutable('2020-02-20T02:50:00+02:00'),
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T02:50:00+02:00'),
            null,
        ];
        yield [
            $trigger,
            new \DateTimeImmutable('2020-02-20T03:00:00+02:00'),
            null,
        ];

        $trigger = new PeriodicalTrigger(
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

    private static function createTrigger(string|int|\DateInterval $interval): PeriodicalTrigger
    {
        return new PeriodicalTrigger($interval, '2023-03-19 13:45', '2023-06-19');
    }

    private function getNextRunDates(\DateTimeImmutable $from, TriggerInterface $trigger, int $count = 1): array
    {
        $dates = [];
        $i = 0;
        $next = $from;
        while ($i++ < $count) {
            $next = $trigger->getNextRunDate($next);
            if (!$next) {
                break;
            }

            $dates[] = $next;
        }

        return $dates;
    }
}
