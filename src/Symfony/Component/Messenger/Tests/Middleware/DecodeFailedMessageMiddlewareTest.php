<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class DecodeFailedMessageMiddlewareTest extends TestCase
{
    public function testItDecodesSerializedEnvelope()
    {
        $decodedEnvelope = new Envelope(new DummyMessage('decoded'));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => 'body', 'headers' => ['type' => DummyMessage::class]])
            ->willReturn($decodedEnvelope);

        $locator = new InMemoryLocator(['async' => $serializer]);
        $middleware = new DecodeFailedMessageMiddleware($locator);

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $ack = static fn (): bool => true;
        $envelope = MessageDecodingFailedException::wrap([
            'body' => 'body',
            'headers' => ['type' => DummyMessage::class],
        ], 'Could not decode.')
            ->with(new ReceivedStamp('async'), new AckStamp($ack));

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertInstanceOf(DummyMessage::class, $nextMiddleware->envelope?->getMessage());
        $this->assertNotNull($nextMiddleware->envelope?->last(AckStamp::class));
    }

    public function testItUsesOriginalTransportNameWhenRetryingFromFailureTransport()
    {
        $decodedEnvelope = new Envelope(new DummyMessage('decoded'));

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('decode')
            ->with(['body' => 'body', 'headers' => []])
            ->willReturn($decodedEnvelope);

        // 'async' is the original transport, 'failed' is the failure transport
        $locator = new InMemoryLocator(['async' => $serializer]);
        $middleware = new DecodeFailedMessageMiddleware($locator);

        $nextMiddleware = new class implements MiddlewareInterface {
            public ?Envelope $envelope = null;

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->envelope = $envelope;
            }
        };

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.')
            ->with(
                new ReceivedStamp('failed'),
                new SentToFailureTransportStamp('async'),
            );

        $middleware->handle($envelope, new StackMiddleware($nextMiddleware));

        $this->assertInstanceOf(DummyMessage::class, $nextMiddleware->envelope?->getMessage());
    }

    public function testItThrowsWhenNoReceivedStampAndNoSentToFailureStamp()
    {
        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator([]));

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.');

        $this->expectException(\Symfony\Component\Messenger\Exception\LogicException::class);
        $this->expectExceptionMessage('ReceivedStamp');
        $middleware->handle($envelope, new StackMiddleware());
    }

    public function testItThrowsWhenDecodingStillFails()
    {
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('decode')->willThrowException(new MessageDecodingFailedException('boom'));

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['transport' => $serializer]));

        $envelope = MessageDecodingFailedException::wrap(['body' => 'body', 'headers' => []], 'Could not decode.')
            ->with(new ReceivedStamp('transport'));

        $this->expectException(MessageDecodingFailedException::class);
        $middleware->handle($envelope, new StackMiddleware());
    }

    public function testItIgnoresRegularMessages()
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->never())->method('decode');

        $middleware = new DecodeFailedMessageMiddleware(new InMemoryLocator(['async' => $serializer]));

        $envelope = new Envelope(new DummyMessage('ok'));

        $middleware->handle($envelope, new StackMiddleware());
    }
}

class InMemoryLocator implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class(\sprintf('Service "%s" not found.', $id)) extends \RuntimeException implements NotFoundExceptionInterface {
            };
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
