<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushy\Tests;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Notifier\Bridge\Pushy\Enum\InterruptionLevel;
use Symfony\Component\Notifier\Bridge\Pushy\PushyOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

class PushyOptionsTest extends TestCase
{
    use ClockSensitiveTrait;

    public function testPushyOptions()
    {
        $options = (new PushyOptions())
            ->to('device')
            ->collapseKey('key')
            ->schedule($schedule = (time() + 3600))
            ->badge(1)
            ->interruptionLevel(InterruptionLevel::TIME_SENSITIVE)
            ->body('Hello world!')
            ->contentAvailable(false)
            ->mutableContent(true)
            ->ttl(3600)
            ->threadId(123);

        self::assertSame([
            'to' => 'device',
            'collapse_key' => 'key',
            'schedule' => $schedule,
            'notification' => [
                'badge' => 1,
                'interruption_level' => 'time-sensitive',
                'body' => 'Hello world!',
                'thread_id' => 123,
            ],
            'content_available' => false,
            'mutable_content' => true,
            'time_to_live' => 3600,
        ], $options->toArray());
    }

    public function testTimeToLiveTooBig()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification time to live cannot exceed 365 days.');

        (new PushyOptions())
            ->ttl(86400 * 400);
    }

    public function testScheduleTooBig()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification schedule time cannot exceed 1 year.');

        (new PushyOptions())
            ->schedule(time() + (86400 * 400));
    }

    public function testScheduleBoundFollowsTheGlobalClock()
    {
        $oneYearLater = self::mockTime('2020-01-01 00:00:00')->now()->modify('+1 year')->getTimestamp();

        $options = (new PushyOptions())->schedule($oneYearLater - 1);
        $this->assertSame($oneYearLater - 1, $options->toArray()['schedule']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification schedule time cannot exceed 1 year.');

        (new PushyOptions())->schedule($oneYearLater);
    }

    public function testScheduleBoundFollowsTheGivenClock()
    {
        $clock = new MockClock('2020-01-01 00:00:00');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification schedule time cannot exceed 1 year.');

        (new PushyOptions([], $clock))->schedule($clock->now()->modify('+1 year')->getTimestamp());
    }

    #[RunInSeparateProcess]
    public function testScheduleBoundFollowsClockMockWithoutTheClockComponent()
    {
        ClassExistsMock::register(PushyOptions::class);
        ClassExistsMock::withMockedClasses([Clock::class => false]);
        ClockMock::register(PushyOptions::class);
        ClockMock::withClockMock(1577836800);
        $oneYearLater = 1609459200;

        $options = (new PushyOptions())->schedule($oneYearLater - 1);
        $this->assertSame($oneYearLater - 1, $options->toArray()['schedule']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification schedule time cannot exceed 1 year.');

        (new PushyOptions())->schedule($oneYearLater);
    }

    public function testCollapseKeyTooLong()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification collapse key cannot be longer than 32 characters.');

        (new PushyOptions())
            ->collapseKey(str_repeat('a', 33));
    }
}
