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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGeneratorWithInstanceLocking;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class MessageGeneratorWithInstanceLockingTest extends TestCase
{
    #[DataProvider('messagesProvider')]
    public function testGetMessagesFromSchedule(string $startTime, array $runs, array $schedule)
    {
        $clock = new MockClock(self::makeDateTime($startTime));

        foreach ($schedule as $i => $s) {
            if (\is_array($s)) {
                $schedule[$i] = $this->createMessage(...$s);
            }
        }
        $schedule = (new Schedule())->add(...$schedule);

        $scheduler = new MessageGeneratorWithInstanceLocking($schedule, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        foreach ($runs as $time => $expected) {
            $clock->modify($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages(), false));
        }
    }

    #[DataProvider('messagesProvider')]
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

                return $schedule;
            }
        };

        $scheduler = new MessageGeneratorWithInstanceLocking($scheduleProvider, 'dummy', $clock);

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

        $scheduler = new MessageGeneratorWithInstanceLocking($scheduleProvider, 'dummy', $clock);

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

        $scheduler = new MessageGeneratorWithInstanceLocking($schedule, 'dummy', $clock);

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

    public function testScheduleResumesWhereLeftOff()
    {
        $clock = new MockClock(self::makeDateTime('22:15:00'));

        $message = RecurringMessage::every('1 minute', (object) ['id' => 'message']);
        $schedule = (new Schedule())
            ->add($message)
            ->stateful(new ArrayAdapter())
            ->lockFactory(new LockFactory(new InMemoryStore()));

        $scheduler = new MessageGeneratorWithInstanceLocking($schedule, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(60 + 10); // 22:16:10

        $this->assertCount(1, iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(2 * 60); // 22:18:10

        // A new instance simulates the consumer process being restarted
        $scheduler = new MessageGeneratorWithInstanceLocking($schedule, 'dummy', $clock);

        $this->assertCount(2, iterator_to_array($scheduler->getMessages(), false));
    }

    public function testScheduleWithProcessOnlyLastMissedRun()
    {
        $clock = new MockClock(self::makeDateTime('22:15:00'));

        $schedule = (new Schedule())
            ->add(RecurringMessage::every('1 minute', (object) ['id' => 'message']))
            ->stateful(new ArrayAdapter())
            ->lockFactory(new LockFactory(new InMemoryStore()))
            ->processOnlyLastMissedRun(true);

        $scheduler = new MessageGeneratorWithInstanceLocking($schedule, 'dummy', $clock);

        // Warmup. The first run always returns nothing.
        $this->assertCount(0, iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(60 + 10); // 22:16:10
        $this->assertCount(1, iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(2 * 60); // 22:18:10
        $this->assertCount(1, iterator_to_array($scheduler->getMessages(), false));

        $clock->sleep(5 * 60); // 22:23:10
        $this->assertCount(1, iterator_to_array($scheduler->getMessages(), false));
    }

    public function testGranularLockAllowsSequentialAcquisitionWithJitter()
    {
        // Run multiple iterations to ensure the test is not flaky due to randomness
        for ($iteration = 0; $iteration < 100; ++$iteration) {
            // message class stringable representation is used as lock key
            $message1 = (object) ['id' => 'message1'];
            $message2 = (object) ['id' => 'message2'];
            $message3 = (object) ['id' => 'message3'];

            $clock = new MockClock(self::makeDateTime('22:15:00'));

            $lockFactory = new LockFactory(new InMemoryStore());
            $cache = new ArrayAdapter();

            // Use a large interval with small jitter
            $schedule1 = (new Schedule())
                ->add(RecurringMessage::every('5 minutes', $message1)->withJitter(15))
                ->add(RecurringMessage::every('5 minutes', $message2)->withJitter(15))
                ->add(RecurringMessage::every('5 minutes', $message3)->withJitter(15))
                ->stateful($cache)
                ->lockFactory($lockFactory)
            ;

            // Clone schedule for second consumer with its own cache to simulate two separate workers
            $schedule2 = (new Schedule())
                ->add(RecurringMessage::every('5 minutes', $message1)->withJitter(15))
                ->add(RecurringMessage::every('5 minutes', $message2)->withJitter(15))
                ->add(RecurringMessage::every('5 minutes', $message3)->withJitter(15))
                ->stateful($cache)
                ->lockFactory($lockFactory)
            ;

            // First consumer
            $generator1 = new MessageGeneratorWithInstanceLocking(
                $schedule1,
                'schedule_1',
                $clock,
            );

            // Second consumer
            $generator2 = new MessageGeneratorWithInstanceLocking(
                $schedule2,
                'schedule_1',
                $clock,
            );

            // Warmup. The first run always returns nothing.
            $this->assertSame([], iterator_to_array($generator1->getMessages(), false));
            // PeriodicTriggers depend on the consumer's start time; this sleep mimics the two consumers not starting at
            // the exact same time.
            $clock->sleep(75);
            $this->assertSame([], iterator_to_array($generator2->getMessages(), false));

            // Advance clock well beyond interval + max jitter to ensure all messages are ready
            $clock->sleep(4 * 60 + 45); // 22:21:00 (5 min + 60 sec, well beyond max 15 sec jitter)

            $processedIds = [];
            $generator1GetMessages = $generator1->getMessages();
            $generator2GetMessages = $generator2->getMessages();
            while ($generator1GetMessages->valid() || $generator2GetMessages->valid()) {
                $iterateGen1 = $generator1GetMessages->valid() && random_int(0, 1);
                $iterateGen2 = $generator2GetMessages->valid() && random_int(0, 1);
                if ($iterateGen1) {
                    $processedIds[] = $generator1GetMessages->current()->id;
                }
                if ($iterateGen2) {
                    $processedIds[] = $generator2GetMessages->current()->id;
                }
                if ($iterateGen1) {
                    $generator1GetMessages->next();
                }
                if ($iterateGen2) {
                    $generator2GetMessages->next();
                }
            }

            // Each message should be processed exactly once across both generators (no duplicates)
            $this->assertEqualsCanonicalizing(['message1', 'message2', 'message3'], $processedIds, \sprintf('Iteration %d failed', $iteration));
        }
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
                RecurringMessage::trigger(new class implements TriggerInterface {
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
        $runs = array_map(static fn ($time) => self::makeDateTime($time), $runs);
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
