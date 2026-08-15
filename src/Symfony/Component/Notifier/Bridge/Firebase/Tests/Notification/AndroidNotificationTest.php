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
use Symfony\Component\Notifier\Bridge\Firebase\Notification\AndroidNotification;

final class AndroidNotificationTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testAndroidNotificationOptions()
    {
        $notification = new AndroidNotification('device_token', [
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
    public function testAndroidNotificationWithAllOptions()
    {
        $notification = (new AndroidNotification('device_token', []))
            ->title('New Title')
            ->body('New Body')
            ->data(['custom' => 'data'])
            ->channelId('channel_123')
            ->icon('notification_icon')
            ->sound('notification_sound')
            ->tag('tag_123')
            ->color('#FF0000')
            ->clickAction('OPEN_ACTIVITY')
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
            'android' => [
                'notification' => [
                    'channel_id' => 'channel_123',
                    'icon' => 'notification_icon',
                    'sound' => 'notification_sound',
                    'tag' => 'tag_123',
                    'color' => '#FF0000',
                    'click_action' => 'OPEN_ACTIVITY',
                    'body_loc_key' => 'body_key',
                    'body_loc_args' => ['arg1', 'arg2'],
                    'title_loc_key' => 'title_key',
                    'title_loc_args' => ['title_arg1', 'title_arg2'],
                ],
            ],
            'topic' => 'device_token',
        ];

        $this->assertSame($expected, $notification->toArray());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testAndroidNotificationChaining()
    {
        $notification = new AndroidNotification('device_token', []);

        $result = $notification
            ->channelId('test_channel')
            ->icon('test_icon');

        $this->assertSame($notification, $result);
        $this->assertSame([
            'notification' => [],
            'data' => [],
            'android' => [
                'notification' => [
                    'channel_id' => 'test_channel',
                    'icon' => 'test_icon',
                ],
            ],
            'topic' => 'device_token',
        ], $notification->toArray());
    }
}
