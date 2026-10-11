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

use PHPUnit\Framework\Attributes\RequiresMethod;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Notifier\Message\MessageInterface;

final class NotificationTest extends AbstractWebTestCase
{
    #[RequiresMethod(MercureBundle::class, 'build')]
    public function testNotifierAssertion()
    {
        $client = $this->createClient(['test_case' => 'Notifier', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_notification');

        $this->assertNotificationCount(3);
        $first = 0;
        $second = 1;
        $third = 2;
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

        $notification = $this->getNotifierMessage($third);
        $this->assertNotificationSubjectContains($notification, 'Hello World!');
        $this->assertNotificationTransportIsEqual($notification, null);
    }

    #[RequiresMethod(MercureBundle::class, 'build')]
    public function testNotificationsSentBeforeARebootAreKept()
    {
        $client = $this->createClient(['test_case' => 'Notifier', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_notification/Foo');
        $this->assertResponseRedirects('/notification_sent');
        $client->followRedirect();

        $this->assertNotificationCount(1);
        $this->assertNotificationSubjectContains($this->getNotifierMessage(), 'Foo');
    }

    #[RequiresMethod(MercureBundle::class, 'build')]
    public function testNotificationsSentBeforeARebootAreListedBeforeTheNewOnes()
    {
        $client = $this->createClient(['test_case' => 'Notifier', 'root_config' => 'config.yml', 'debug' => true]);
        $client->request('GET', '/send_notification/Foo');
        $client->request('GET', '/send_notification/Bar');

        $this->assertNotificationCount(2);
        $this->assertSame(['Foo', 'Bar'], array_map(static fn (MessageInterface $message) => $message->getSubject(), $this->getNotifierMessages()));
    }

    #[RequiresMethod(MercureBundle::class, 'build')]
    public function testNotificationsAreKeptWhenRebootIsDisabled()
    {
        $client = $this->createClient(['test_case' => 'Notifier', 'root_config' => 'config.yml', 'debug' => true]);
        $client->disableReboot();
        $client->request('GET', '/send_notification/Foo');
        $client->followRedirect();
        $client->request('GET', '/send_notification/Bar');

        $this->assertNotificationCount(2);
        $this->assertSame(['Foo', 'Bar'], array_map(static fn (MessageInterface $message) => $message->getSubject(), $this->getNotifierMessages()));
    }
}
