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
use Symfony\Component\Notifier\Bridge\Firebase\Notification\WebNotification;

final class WebNotificationTest extends TestCase
{
    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testWebNotificationOptions()
    {
        $notification = new WebNotification('device_token', [
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
    public function testWebNotificationWithAllOptions()
    {
        $notification = (new WebNotification('device_token', []))
            ->title('New Title')
            ->body('New Body')
            ->data(['custom' => 'data'])
            ->icon('/images/icon.png')
            ->clickAction('https://example.com');

        $expected = [
            'notification' => [
                'title' => 'New Title',
                'body' => 'New Body',
            ],
            'data' => [
                'custom' => 'data',
            ],
            'webpush' => [
                'notification' => [
                    'icon' => '/images/icon.png',
                    'click_action' => 'https://example.com',
                ],
            ],
            'topic' => 'device_token',
        ];

        $this->assertSame($expected, $notification->toArray());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testWebNotificationChaining()
    {
        $notification = new WebNotification('device_token', []);

        $result = $notification
            ->icon('/favicon.ico')
            ->clickAction('https://myapp.com/action');

        $this->assertSame($notification, $result);
        $this->assertSame([
            'notification' => [],
            'data' => [],
            'webpush' => [
                'notification' => [
                    'icon' => '/favicon.ico',
                    'click_action' => 'https://myapp.com/action',
                ],
            ],
            'topic' => 'device_token',
        ], $notification->toArray());
    }
}
