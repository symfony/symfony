<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\ContactEveryone\ContactEveryoneTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ContactEveryoneTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): ContactEveryoneTransport
    {
        return new ContactEveryoneTransport('API_TOKEN', 'Symfony', 'Foo', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['contact-everyone://contact-everyone.orange-business.com?diffusionname=Symfony&category=Foo', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendSuccessfully()
    {
        $messageId = bin2hex(random_bytes(7));
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn($messageId);
        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('phone', 'testMessage'));

        $this->assertSame($messageId, $sentMessage->getMessageId());
    }

    public function testSmsMessageWithFrom()
    {
        $transport = $this->createTransport();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Notifier\Bridge\ContactEveryone\ContactEveryoneTransport" transport does not support "from" in "Symfony\Component\Notifier\Message\SmsMessage".');

        $transport->send(new SmsMessage('0600000000', 'test', 'foo'));
    }
}
