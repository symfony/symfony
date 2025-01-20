<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\JoliNotif\Tests;

use Joli\JoliNotif\DefaultNotifier as JoliNotifier;
use Symfony\Component\Notifier\Bridge\JoliNotif\JoliNotifOptions;
use Symfony\Component\Notifier\Bridge\JoliNotif\JoliNotifTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\DesktopMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class JoliNotifTransportTest extends TransportTestCase
{
    public static function toStringProvider(): iterable
    {
        yield ['jolinotif://localhost', self::createTransport()];
    }

    public static function createTransport(?HttpClientInterface $client = null): JoliNotifTransport
    {
        return new JoliNotifTransport(new JoliNotifier());
    }

    public static function supportedMessagesProvider(): iterable
    {
        $message = new DesktopMessage('Worker Status', 'Task#2 has finished successfully');

        $message->setOptions((new JoliNotifOptions())->setIconPath('/path/to/notification/icon'));

        yield [$message];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }
}
