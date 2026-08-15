<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests\Notification;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Firebase\Notification\IOSNotification;

final class IOSNotificationTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testIOSNotificationOptions()
    {
        $notification = new IOSNotification('device_token', [
            'title' => 'Test Title',
            'body' => 'Test Body',
        ], ['key' => 'value']);

        $this->assertSame([
            'notification' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
            'data' => ['key' => 'value'],
            'topic' => 'device_token',
        ], $notification->toArray());

        $this->assertSame('[topic]device_token', $notification->getRecipientId());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testIOSNotificationWithAllOptions()
    {
        $notification = (new IOSNotification('device_token', []))
            ->title('New Title')
            ->body('New Body')
            ->data(['custom' => 'data'])
            ->sound('default')
            ->badge('5')
            ->clickAction('OPEN_ACTIVITY')
            ->subtitle('Test Subtitle')
            ->bodyLocKey('body_key')
            ->bodyLocArgs(['arg1', 'arg2'])
            ->titleLocKey('title_key')
            ->titleLocArgs(['title_arg1', 'title_arg2']);

        $expected = [
            'notification' => [
                'title' => 'New Title',
                'body' => 'New Body',
            ],
            'data' => [
                'custom' => 'data',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => '5',
                        'category' => 'OPEN_ACTIVITY',
                        'alert' => [
                            'subtitle' => 'Test Subtitle',
                            'body_loc_key' => 'body_key',
                            'body_loc_args' => ['arg1', 'arg2'],
                            'title_loc_key' => 'title_key',
                            'title_loc_args' => ['title_arg1', 'title_arg2'],
                        ],
                    ],
                ],
            ],
            'topic' => 'device_token',
        ];

        $this->assertSame($expected, $notification->toArray());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testIOSNotificationChaining()
    {
        $notification = new IOSNotification('device_token', []);

        $result = $notification
            ->sound('ping.aiff')
            ->badge('10')
            ->subtitle('Important');

        $this->assertSame($notification, $result);
        $this->assertSame([
            'notification' => [],
            'data' => [],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'ping.aiff',
                        'badge' => '10',
                        'alert' => [
                            'subtitle' => 'Important',
                        ],
                    ],
                ],
            ],
            'topic' => 'device_token',
        ], $notification->toArray());
    }
}
