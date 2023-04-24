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
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGenerator;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class MessageGeneratorTest extends TestCase
{
    /**
     * @dataProvider messagesProvider
     */
    public function testGetMessages(string $startTime, array $runs, array $schedule)
    {
        // for referencing
        $now = self::makeDateTime($startTime);

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnReference($now);

        foreach ($schedule as $i => $s) {
            if (\is_array($s)) {
                $schedule[$i] = $this->createMessage(...$s);
            }
        }
        $schedule = (new Schedule())->add(...$schedule);
        $schedule->stateful(new ArrayAdapter());

        $scheduler = new MessageGenerator($schedule, 'dummy', $clock);

        // Warmup. The first run is always returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        foreach ($runs as $time => $expected) {
            $now = self::makeDateTime($time);
            $this->assertSame($expected, iterator_to_array($scheduler->getMessages(), false));
        }
    }

    public function testYieldedContext()
    {
        // for referencing
        $now = self::makeDateTime('22:12:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturnReference($now);

        $message = $this->createMessage((object) ['id' => 'message'], '22:13:00', '22:14:00', '22:16:00');
        $schedule = (new Schedule())->add($message);
        $schedule->stateful(new ArrayAdapter());

        $scheduler = new MessageGenerator($schedule, 'dummy', $clock);

        // Warmup. The first run is alw ays returns nothing.
        $this->assertSame([], iterator_to_array($scheduler->getMessages(), false));

        $now = self::makeDateTime('22:14:10');

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
                    $this->fail(sprintf('Invalid tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
                }

                foreach ($runs as $run) {
                    if ($lastTick < $run) {
                        return $run;
                    }
                }

                $this->fail(sprintf('There is no next run for tick %s', $lastTick->format(\DateTimeImmutable::RFC3339_EXTENDED)));
            });

        return RecurringMessage::trigger($trigger, $message);
    }

    private static function makeDateTime(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2020-02-20T'.$time, new \DateTimeZone('UTC'));
    }
}
