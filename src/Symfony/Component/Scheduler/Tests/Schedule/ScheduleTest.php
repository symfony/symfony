<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Schedule;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Scheduler\Schedule\Schedule;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;
use Symfony\Component\Scheduler\State\State;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class ScheduleTest extends TestCase
{
    public function messagesProvider(): \Generator
    {
        $first = (object) ['id' => 'first'];
        $second = (object) ['id' => 'second'];
        $third = (object) ['id' => 'third'];

        yield 'first' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:00' => [],
                '22:12:01' => [],
                '22:13:00' => [$first],
                '22:13:01' => [],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:13:00', '22:14:00'),
            ],
        ];

        yield 'microseconds' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:59.999999' => [],
                '22:13:00' => [$first],
                '22:13:01' => [],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:13:00', '22:14:00', '22:15:00'),
            ],
        ];

        yield 'skipped' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:14:01' => [$first, $first],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:13:00', '22:14:00', '22:15:00'),
            ],
        ];

        yield 'sequence' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:59' => [],
                '22:13:00' => [$first],
                '22:13:01' => [],
                '22:13:59' => [],
                '22:14:00' => [$first],
                '22:14:01' => [],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:13:00', '22:14:00', '22:15:00'),
            ],
        ];

        yield 'concurrency' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:00.555' => [],
                '22:13:01.555' => [$third, $first, $first, $second, $first],
                '22:13:02.000' => [$first],
                '22:13:02.555' => [],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:12:59', '22:13:00', '22:13:01', '22:13:02', '22:13:03'),
                $this->makeSchedule($second, '22:13:00', '22:14:00'),
                $this->makeSchedule($third, '22:12:30', '22:13:30'),
            ],
        ];

        yield 'parallel' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:59' => [],
                '22:13:59' => [$first, $second],
                '22:14:00' => [$first, $second],
                '22:14:01' => [],
            ],
            'schedule' => [
                $this->makeSchedule($first, '22:13:00', '22:14:00', '22:15:00'),
                $this->makeSchedule($second, '22:13:00', '22:14:00', '22:15:00'),
            ],
        ];

        yield 'past' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:01' => [],
            ],
            'schedule' => [
                [$this->createMock(TriggerInterface::class), $this],
            ],
        ];
    }

    /**
     * @dataProvider messagesProvider
     */
    public function testGetMessages(string $startTime, array $runs, array $schedule)
    {
        // for referencing
        $now = $this->makeDateTime($startTime);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnReference($now);

        $scheduler = new Schedule($clock, new State(), new ScheduleConfig($schedule));

        // Warmup. The first run is always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages()));

        foreach ($runs as $time => $expected) {
            $now = $this->makeDateTime($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages()));
        }
    }

    private function makeDateTime(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2020-02-20T'.$time, new \DateTimeZone('UTC'));
    }

    /**
     * @return array{TriggerInterface, object}
     */
    private function makeSchedule(object $message, string ...$runs): array
    {
        $runs = array_map(fn ($time) => $this->makeDateTime($time), $runs);
        sort($runs);

        $ticks = [$this->makeDateTime(''), 0];

        $trigger = $this->createMock(TriggerInterface::class);
        $trigger
            ->method('nextTo')
            ->willReturnCallback(function (\DateTimeImmutable $lastTick) use ($runs, &$ticks): \DateTimeImmutable {
                [$tick, $count] = $ticks;
                if ($lastTick > $tick) {
                    $ticks = [$lastTick, 1];
                } elseif ($lastTick == $tick && $count < 2) {
                    $ticks = [$lastTick, ++$count];
                } else {
                    $this->fail(sprintf('Invalid tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
                }

                foreach ($runs as $run) {
                    if ($lastTick < $run) {
                        return $run;
                    }
                }

                $this->fail(sprintf('There is no next run for tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
            });

        return [$trigger, $message];
    }
}
