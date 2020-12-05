<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure\Tests;

use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Notifier\Bridge\Mercure\MercureOptions;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Tests\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use TypeError;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportTest extends TransportTestCase
{
    public function createTransport(?HttpClientInterface $client = null, ?PublisherInterface $publisher = null, string $publisherId = 'publisherId', $topics = null): TransportInterface
    {
        $publisher = $publisher ?? $this->createMock(PublisherInterface::class);

        return new MercureTransport($publisher, $publisherId, $topics);
    }

    public function toStringProvider(): iterable
    {
        yield ['mercure://publisherId?topic=https%3A%2F%2Fsymfony.com%2Fnotifier', $this->createTransport()];
        yield ['mercure://customPublisherId?topic=%2Ftopic', $this->createTransport(null, null, 'customPublisherId', '/topic')];
        yield ['mercure://customPublisherId?topic%5B0%5D=%2Ftopic%2F1&topic%5B1%5D%5B0%5D=%2Ftopic%2F2', $this->createTransport(null, null, 'customPublisherId', ['/topic/1', ['/topic/2']])];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    public function testCanSetCustomPort()
    {
        $this->markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testCanSetCustomHost()
    {
        $this->markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testCanSetCustomHostAndPort()
    {
        $this->markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testConstructWithWrongTopicsThrows()
    {
        $this->expectException(TypeError::class);
        $this->createTransport(null, null, 'publisherId', 1);
    }

    public function testSendWithNonMercureOptionsThrows()
    {
        $this->expectException(LogicException::class);
        $this->createTransport()->send(new ChatMessage('testMessage', $this->createMock(MessageOptionsInterface::class)));
    }

    public function testSendWithWrongResponseThrows()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn('Service Unavailable');

        $httpException = $this->createMock(ServerExceptionInterface::class);
        $httpException->method('getResponse')->willReturn($response);

        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->method('__invoke')->willThrowException($httpException);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to post the Mercure message: "Service Unavailable".');

        $this->createTransport(null, $publisher)->send(new ChatMessage('subject'));
    }

    public function testSendWithWrongTokenThrows()
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->method('__invoke')->willThrowException(new \InvalidArgumentException('The provided JWT is not valid'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to post the Mercure message: "The provided JWT is not valid".');

        $this->createTransport(null, $publisher)->send(new ChatMessage('subject'));
    }

    public function testSendWithMercureOptions()
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Update(['/topic/1', '/topic/2'], '{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}', true, 'id', 'type', 1))
        ;

        $this->createTransport(null, $publisher)->send(new ChatMessage('subject', new MercureOptions(['/topic/1', '/topic/2'], true, 'id', 'type', 1)));
    }

    public function testSendWithMercureOptionsButWithoutOptionTopic()
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Update(['https://symfony.com/notifier'], '{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}', true, 'id', 'type', 1))
        ;

        $this->createTransport(null, $publisher)->send(new ChatMessage('subject', new MercureOptions(null, true, 'id', 'type', 1)));
    }

    public function testSendWithoutMercureOptions()
    {
        $publisher = $this->createMock(PublisherInterface::class);
        $publisher
            ->expects($this->once())
            ->method('__invoke')
            ->with(new Update(['https://symfony.com/notifier'], '{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}'))
        ;

        $this->createTransport(null, $publisher)->send(new ChatMessage('subject'));
    }

    public function testSendSuccessfully()
    {
        $messageId = 'urn:uuid:a7045be0-a75d-4d40-8bd2-29fa4e5dd10b';

        $publisher = $this->createMock(PublisherInterface::class);
        $publisher->method('__invoke')->willReturn($messageId);

        $sentMessage = $this->createTransport(null, $publisher)->send(new ChatMessage('subject'));
        $this->assertSame($messageId, $sentMessage->getMessageId());
    }
}
