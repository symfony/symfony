<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\Generator\Checkpoint;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class MessageGeneratorTest extends TestCase
{
    /**
     * @dataProvider messagesProvider
     */
    public function testGetMessagesFromSchedule(string $startTime, array $runs, array $schedule)
    {
        $clock = new MockClock(self::makeDateTime($startTime));

        foreach ($schedule as $i => $s) {
            if (\is_array($s)) {
                $schedule[$i] = $this->createMessage(...$s);
            }
        }
        $schedule = (new Schedule())->add(...$schedule);
        $schedule->stateful(new ArrayAdapter());

        $scheduler = new MessageGenerator($schedule, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        foreach ($runs as $time => $expected) {
            $clock->modify($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages(), false));
        }
    }

    /**
     * @dataProvider messagesProvider
     */
    public function testGetMessagesFromScheduleProvider(string $startTime, array $runs, array $schedule)
    {
        $clock = new MockClock(self::makeDateTime($startTime));

        foreach ($schedule as $i => $s) {
            if (\is_array($s)) {
                $schedule[$i] = $this->createMessage(...$s);
            }
        }

        $scheduleProvider = new class($schedule) implements ScheduleProviderInterface {
            public function __construct(private readonly array $schedule)
            {
            }

            public function getSchedule(): Schedule
            {
                $schedule = (new Schedule())->add(...$this->schedule);
                $schedule->stateful(new ArrayAdapter());

                return $schedule;
            }
        };

        $scheduler = new MessageGenerator($scheduleProvider, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        foreach ($runs as $time => $expected) {
            $clock->modify($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages(), false));
        }
    }

    public function testGetMessagesFromScheduleProviderWithRestart()
    {
        $first = (object) ['id' => 'first'];
        $startTime = '22:12:00';
        $runs = [
            '22:12:00' => [],
            '22:12:01' => [],
            '22:13:00' => [$first],
            '22:13:01' => [],
        ];
        $schedule = [[$first, '22:13:00', '22:14:00']];

        $clock = new MockClock(self::makeDateTime($startTime));

        foreach ($schedule as $i => $s) {
            if (\is_array($s)) {
                $schedule[$i] = $this->createMessage(...$s);
            }
        }

        $scheduleProvider = new class($schedule) implements ScheduleProviderInterface {
            private Schedule $schedule;

            public function __construct(array $schedule)
            {
                $this->schedule = (new Schedule())->with(...$schedule);
                $this->schedule->stateful(new ArrayAdapter());
            }

            public function getSchedule(): Schedule
            {
                return $this->schedule;
            }

            public function add(RecurringMessage $message): self
            {
                $this->schedule->add($message);

                return $this;
            }
        };

        $scheduler = new MessageGenerator($scheduleProvider, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $toAdd = (object) ['id' => 'added-after-start'];

        foreach ($runs as $time => $expected) {
            $clock->modify($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages(), false));
        }

        $scheduleProvider->add($this->createMessage($toAdd, '22:13:10', '22:13:11'));

        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(9);
        $this->assertSame([$toAdd], iterator_to_array($scheduler->getMessages(), false));
    }

    public function testYieldedContext()
    {
        $clock = new MockClock(self::makeDateTime('22:12:00'));

        $message = $this->createMessage((object) ['id' => 'message'], '22:13:00', '22:14:00', '22:16:00');
        $schedule = (new Schedule())->add($message);
        $schedule->stateful(new ArrayAdapter());

        $scheduler = new MessageGenerator($schedule, 'dummy', $clock);

        // Warmup. The first run is alw ays returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(2 * 60 + 10);
        $iterator = $scheduler->getMessages();

        $this->assertInstanceOf(MessageContext::class, $context = $iterator->key());
        $this->assertSame($message->getTrigger(), $context->trigger);
        $this->assertEquals(self::makeDateTime('22:13:00'), $context->triggeredAt);
        $this->assertEquals(self::makeDateTime('22:14:00'), $context->nextTriggerAt);

        $iterator->next();
        $this->assertInstanceOf(MessageContext::class, $context = $iterator->key());
        $this->assertSame($message->getTrigger(), $context->trigger);
        $this->assertEquals(self::makeDateTime('22:14:00'), $context->triggeredAt);
        $this->assertEquals(self::makeDateTime('22:16:00'), $context->nextTriggerAt);
    }

    public function testCheckpointSavedInBrokenLoop()
    {
        $clock = new MockClock(self::makeDateTime('22:12:00'));

        $message = $this->createMessage((object) ['id' => 'message'], '22:13:00', '22:14:00', '22:16:00');
        $schedule = (new Schedule())->add($message);

        $cache = new ArrayAdapter();
        $schedule->stateful($cache);
        $checkpoint = new Checkpoint('dummy', cache: $cache);

        $scheduler = new MessageGenerator($schedule, 'dummy', clock: $clock, checkpoint: $checkpoint);

        // Warmup. The first run is always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(60 + 10); // 22:13:10

        foreach ($scheduler->getMessages() as $message) {
            // Message is handled but loop is broken just after
            break;
        }

        $this->assertEquals(self::makeDateTime('22:13:00'), $checkpoint->time());
    }

    public static function messagesProvider(): \Generator
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
            'schedule' => [[$first, '22:13:00', '22:14:00']],
        ];

        yield 'microseconds' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:59.999999' => [],
                '22:13:00' => [$first],
                '22:13:01' => [],
            ],
            'schedule' => [[$first, '22:13:00', '22:14:00', '22:15:00']],
        ];

        yield 'skipped' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:14:01' => [$first, $first],
            ],
            'schedule' => [[$first, '22:13:00', '22:14:00', '22:15:00']],
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
            'schedule' => [[$first, '22:13:00', '22:14:00', '22:15:00']],
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
                [$first, '22:12:59', '22:13:00', '22:13:01', '22:13:02', '22:13:03'],
                [$second, '22:13:00', '22:14:00'],
                [$third, '22:12:30', '22:13:30'],
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
                [$first, '22:13:00', '22:14:00', '22:15:00'],
                [$second, '22:13:00', '22:14:00', '22:15:00'],
            ],
        ];

        yield 'past' => [
            'startTime' => '22:12:00',
            'runs' => [
                '22:12:01' => [],
            ],
            'schedule' => [
                RecurringMessage::trigger(new class() implements TriggerInterface {
                    public function __toString(): string
                    {
                        return 'foo';
                    }

                    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
                    {
                        return null;
                    }
                }, (object) []),
            ],
        ];
    }

    private function createMessage(object $message, string ...$runs): RecurringMessage
    {
        $runs = array_map(fn ($time) => self::makeDateTime($time), $runs);
        sort($runs);

        $ticks = [self::makeDateTime(''), 0];
        $trigger = $this->createMock(TriggerInterface::class);
        $trigger
            ->method('getNextRunDate')
            ->willReturnCallback(function (\DateTimeImmutable $lastTick) use ($runs, &$ticks): \DateTimeImmutable {
                [$tick, $count] = $ticks;
                if ($lastTick > $tick) {
                    $ticks = [$lastTick, 1];
                } elseif ($lastTick == $tick && $count < 2) {
                    $ticks = [$lastTick, ++$count];
                } else {
                    $this->fail(\sprintf('Invalid tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
                }

                foreach ($runs as $run) {
                    if ($lastTick < $run) {
                        return $run;
                    }
                }

                $this->fail(\sprintf('There is no next run for tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
            });

        return RecurringMessage::trigger($trigger, $message);
    }

    private static function makeDateTime(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2020-02-20T'.$time, new \DateTimeZone('UTC'));
    }
}
