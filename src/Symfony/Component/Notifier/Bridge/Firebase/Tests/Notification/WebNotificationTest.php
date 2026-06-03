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
use Symfony\Component\Notifier\Bridge\Firebase\Notification\WebNotification;

final class WebNotificationTest extends TestCase
{
    public function testWebNotificationOptions()
    {
        $notification = new WebNotification('device_token', [
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

    public function testWebNotificationWithAllOptions()
    {
        $notification = (new WebNotification('device_token', []))
            ->title('New Title')
            ->body('New Body')
            ->data(['custom' => 'data'])
            ->icon('/images/icon.png')
            ->clickAction('https://example.com');

        $expected = [
            'to' => 'device_token',
            'notification' => [
                'title' => 'New Title',
                'body' => 'New Body',
                'icon' => '/images/icon.png',
                'click_action' => 'https://example.com',
            ],
            'data' => ['custom' => 'data'],
        ];

        $this->assertSame($expected, $notification->toArray());
    }

    public function testWebNotificationChaining()
    {
        $notification = new WebNotification('device_token', []);

        $result = $notification
            ->icon('/favicon.ico')
            ->clickAction('https://myapp.com/action');

        $this->assertSame($notification, $result);
        $this->assertSame([
            'to' => 'device_token',
            'notification' => [
                'icon' => '/favicon.ico',
                'click_action' => 'https://myapp.com/action',
            ],
            'data' => [],
        ], $notification->toArray());
    }
}
