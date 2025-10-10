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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Firebase\Notification\IOSNotification;

final class IOSNotificationTest extends TestCase
{
    public function testIOSNotificationOptions()
    {
        $notification = new IOSNotification('device_token', [
            'title' => 'Test Title',
            'body' => 'Test Body',
        ], ['key' => 'value']);

        $this->assertSame([
            'to' => 'device_token',
            'notification' => [
                'title' => 'Test Title',
                'body' => 'Test Body',
            ],
            'data' => ['key' => 'value'],
        ], $notification->toArray());

        $this->assertSame('device_token', $notification->getRecipientId());
    }

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
            'to' => 'device_token',
            'notification' => [
                'title' => 'New Title',
                'body' => 'New Body',
                'sound' => 'default',
                'badge' => '5',
                'click_action' => 'OPEN_ACTIVITY',
                'subtitle' => 'Test Subtitle',
                'body_loc_key' => 'body_key',
                'body_loc_args' => ['arg1', 'arg2'],
                'title_loc_key' => 'title_key',
                'title_loc_args' => ['title_arg1', 'title_arg2'],
            ],
            'data' => ['custom' => 'data'],
        ];

        $this->assertSame($expected, $notification->toArray());
    }

    public function testIOSNotificationChaining()
    {
        $notification = new IOSNotification('device_token', []);

        $result = $notification
            ->sound('ping.aiff')
            ->badge('10')
            ->subtitle('Important');

        $this->assertSame($notification, $result);
        $this->assertSame([
            'to' => 'device_token',
            'notification' => [
                'sound' => 'ping.aiff',
                'badge' => '10',
                'subtitle' => 'Important',
            ],
            'data' => [],
        ], $notification->toArray());
    }
}
