<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

final class NotificationTest extends AbstractWebTestCase
{
    /**
     * @requires function \Symfony\Bundle\MercureBundle\MercureBundle::build
     */
    public function testNotifierAssertion()
    {
        $client = $this->createClient(['test_case' => 'Notifier', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_notification');

        $this->assertNotificationCount(2);
        $first = 0;
        $second = 1;
        $this->assertNotificationIsNotQueued($this->getNotifierEvent($first));
        $this->assertNotificationIsNotQueued($this->getNotifierEvent($second));

        $notification = $this->getNotifierMessage($first);
        $this->assertNotificationSubjectContains($notification, 'Hello World!');
        $this->assertNotificationSubjectNotContains($notification, 'New urgent notification');
        $this->assertNotificationTransportIsEqual($notification, 'slack');
        $this->assertNotificationTransportIsNotEqual($notification, 'mercure');

        $notification = $this->getNotifierMessage($second);
        $this->assertNotificationSubjectContains($notification, 'New urgent notification');
        $this->assertNotificationSubjectNotContains($notification, 'Hello World!');
        $this->assertNotificationTransportIsEqual($notification, 'mercure');
        $this->assertNotificationTransportIsNotEqual($notification, 'slack');
    }
}
