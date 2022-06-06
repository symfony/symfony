<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Expo\Tests;

use Symfony\Component\Notifier\Bridge\Expo\ExpoTransport;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Imad ZAIRIG <https://github.com/zairigimad>
 */
final class ExpoTransportTest extends TransportTestCase
{
    /**
     * @return ExpoTransport
     */
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return new ExpoTransport('token', $client ?? $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['expo://exp.host/--/api/v2/push/send', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Hello!', 'Symfony Notifier')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0670802161', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
