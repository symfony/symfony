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

use Symfony\Component\Mercure\Exception\InvalidArgumentException;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureRuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Notifier\Bridge\Mercure\MercureOptions;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TypeError;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null, HubInterface $hub = null, string $hubId = 'hubId', $topics = null): TransportInterface
    {
        $hub = $hub ?? self::createMock(HubInterface::class);

        return new MercureTransport($hub, $hubId, $topics);
    }

    public function toStringProvider(): iterable
    {
        yield ['mercure://hubId?topic=https%3A%2F%2Fsymfony.com%2Fnotifier', $this->createTransport()];
        yield ['mercure://customHubId?topic=%2Ftopic', $this->createTransport(null, null, 'customHubId', '/topic')];
        yield ['mercure://customHubId?topic%5B0%5D=%2Ftopic%2F1&topic%5B1%5D%5B0%5D=%2Ftopic%2F2', $this->createTransport(null, null, 'customHubId', ['/topic/1', ['/topic/2']])];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [self::createMock(MessageInterface::class)];
    }

    public function testCanSetCustomPort()
    {
        self::markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testCanSetCustomHost()
    {
        self::markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testCanSetCustomHostAndPort()
    {
        self::markTestSkipped("Mercure transport doesn't use a regular HTTP Dsn");
    }

    public function testConstructWithWrongTopicsThrows()
    {
        self::expectException(TypeError::class);
        $this->createTransport(null, null, 'publisherId', new \stdClass());
    }

    public function testSendWithNonMercureOptionsThrows()
    {
        self::expectException(LogicException::class);
        $this->createTransport()->send(new ChatMessage('testMessage', self::createMock(MessageOptionsInterface::class)));
    }

    public function testSendWithTransportFailureThrows()
    {
        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), static function (): void {
            throw new MercureRuntimeException('Cannot connect to mercure');
        });

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to post the Mercure message: Cannot connect to mercure');

        $this->createTransport(null, $hub)->send(new ChatMessage('subject'));
    }

    public function testSendWithWrongTokenThrows()
    {
        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), static function (): void {
            throw new InvalidArgumentException('The provided JWT is not valid');
        });

        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('Unable to post the Mercure message: The provided JWT is not valid');

        $this->createTransport(null, $hub)->send(new ChatMessage('subject'));
    }

    public function testSendWithMercureOptions()
    {
        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), function (Update $update): string {
            self::assertSame(['/topic/1', '/topic/2'], $update->getTopics());
            self::assertSame('{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}', $update->getData());
            self::assertSame('id', $update->getId());
            self::assertSame('type', $update->getType());
            self::assertSame(1, $update->getRetry());
            self::assertTrue($update->isPrivate());

            return 'id';
        });

        $this->createTransport(null, $hub)->send(new ChatMessage('subject', new MercureOptions(['/topic/1', '/topic/2'], true, 'id', 'type', 1)));
    }

    public function testSendWithMercureOptionsButWithoutOptionTopic()
    {
        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), function (Update $update): string {
            self::assertSame(['https://symfony.com/notifier'], $update->getTopics());
            self::assertSame('{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}', $update->getData());
            self::assertSame('id', $update->getId());
            self::assertSame('type', $update->getType());
            self::assertSame(1, $update->getRetry());
            self::assertTrue($update->isPrivate());

            return 'id';
        });

        $this->createTransport(null, $hub)->send(new ChatMessage('subject', new MercureOptions(null, true, 'id', 'type', 1)));
    }

    public function testSendWithoutMercureOptions()
    {
        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), function (Update $update): string {
            self::assertSame(['https://symfony.com/notifier'], $update->getTopics());
            self::assertSame('{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","type":"Announce","summary":"subject"}', $update->getData());
            self::assertFalse($update->isPrivate());

            return 'id';
        });

        $this->createTransport(null, $hub)->send(new ChatMessage('subject'));
    }

    public function testSendSuccessfully()
    {
        $messageId = 'urn:uuid:a7045be0-a75d-4d40-8bd2-29fa4e5dd10b';

        $hub = new MockHub('https://foo.com/.well-known/mercure', new StaticTokenProvider('foo'), function (Update $update) use ($messageId): string {
            return $messageId;
        });

        $sentMessage = $this->createTransport(null, $hub)->send(new ChatMessage('subject'));
        self::assertSame($messageId, $sentMessage->getMessageId());
    }
}
