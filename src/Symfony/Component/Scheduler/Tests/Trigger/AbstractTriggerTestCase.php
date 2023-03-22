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
use Symfony\Component\Scheduler\Trigger\DatePeriodTrigger;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

abstract class AbstractTriggerTestCase extends TestCase
{
    /**
     * @dataProvider providerGetNextRunDate
     */
    public function testGetNextRunDate(\DateTimeImmutable $from, TriggerInterface $trigger, array $expected)
    {
        $this->assertEquals($expected, $this->getNextRunDates($from, $trigger));
    }

    abstract public static function providerGetNextRunDate(): iterable;

    protected static function createTrigger(string $interval): DatePeriodTrigger
    {
        return new DatePeriodTrigger(
            new \DatePeriod(new \DateTimeImmutable('13:45'), \DateInterval::createFromDateString($interval), new \DateTimeImmutable('2023-06-19'))
        );
    }

    private function getNextRunDates(\DateTimeImmutable $from, TriggerInterface $trigger): array
    {
        $dates = [];
        $i = 0;
        $next = $from;
        while ($i++ < 20) {
            $next = $trigger->getNextRunDate($next);
            if (!$next) {
                break;
            }

            $dates[] = $next;
        }

        return $dates;
    }
}
