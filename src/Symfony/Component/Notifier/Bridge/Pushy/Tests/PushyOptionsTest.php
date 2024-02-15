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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Pushy\Enum\InterruptionLevel;
use Symfony\Component\Notifier\Bridge\Pushy\PushyOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

class PushyOptionsTest extends TestCase
{
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

    public function testCollapseKeyTooLong()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pushy notification collapse key cannot be longer than 32 characters.');

        (new PushyOptions())
            ->collapseKey(str_repeat('a', 33));
    }
}
