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

use Symfony\Component\Scheduler\Trigger\DatePeriodTrigger;

class DatePeriodTriggerTest extends AbstractTriggerTestCase
{
    public static function providerGetNextRunDate(): iterable
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
        ];

        yield [
            new \DateTimeImmutable('2023-03-19 13:45'),
            self::createTrigger('last day of next month'),
            [
                new \DateTimeImmutable('2023-04-30 13:45:00'),
                new \DateTimeImmutable('2023-05-31 13:45:00'),
            ],
        ];

        yield [
            new \DateTimeImmutable('2023-03-19 13:45'),
            self::createTrigger('first monday of next month'),
            [
                new \DateTimeImmutable('2023-04-03 13:45:00'),
                new \DateTimeImmutable('2023-05-01 13:45:00'),
                new \DateTimeImmutable('2023-06-05 13:45:00'),
            ],
        ];
    }

    protected static function createTrigger(string $interval): DatePeriodTrigger
    {
        return new DatePeriodTrigger(
            new \DatePeriod(new \DateTimeImmutable('2023-03-19 13:45'), \DateInterval::createFromDateString($interval), new \DateTimeImmutable('2023-06-19')),
        );
    }
}
