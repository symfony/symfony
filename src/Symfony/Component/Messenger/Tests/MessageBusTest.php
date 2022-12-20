<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        self::assertInstanceOf(MessageBusInterface::class, $bus);
    }

    public function testItCallsMiddleware()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $firstMiddleware = self::createMock(MiddlewareInterface::class);
        $firstMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelope, self::anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope, $stack);
            });

        $secondMiddleware = self::createMock(MiddlewareInterface::class);
        $secondMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelope, self::anything())
            ->willReturn($envelope)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
        ]);

        $bus->dispatch($message);
    }

    public function testThatAMiddlewareCanAddSomeStampsToTheEnvelope()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, [new ReceivedStamp('transport')]);
        $envelopeWithAnotherStamp = $envelope->with(new AnEnvelopeStamp());

        $firstMiddleware = self::createMock(MiddlewareInterface::class);
        $firstMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelope, self::anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope->with(new AnEnvelopeStamp()), $stack);
            });

        $secondMiddleware = self::createMock(MiddlewareInterface::class);
        $secondMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, self::anything())
            ->willReturnCallback(function ($envelope, $stack) {
                return $stack->next()->handle($envelope, $stack);
            });

        $thirdMiddleware = self::createMock(MiddlewareInterface::class);
        $thirdMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, self::anything())
            ->willReturn($envelopeWithAnotherStamp)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
            $thirdMiddleware,
        ]);

        $bus->dispatch($envelope);
    }

    public function testThatAMiddlewareCanUpdateTheMessageWhileKeepingTheEnvelopeStamps()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message, $stamps = [new ReceivedStamp('transport')]);

        $changedMessage = new DummyMessage('Changed');
        $expectedEnvelope = new Envelope($changedMessage, $stamps);

        $firstMiddleware = self::createMock(MiddlewareInterface::class);
        $firstMiddleware->expects(self::once())
            ->method('handle')
            ->with($envelope, self::anything())
            ->willReturnCallback(function ($envelope, $stack) use ($expectedEnvelope) {
                return $stack->next()->handle($expectedEnvelope, $stack);
            });

        $secondMiddleware = self::createMock(MiddlewareInterface::class);
        $secondMiddleware->expects(self::once())
            ->method('handle')
            ->with($expectedEnvelope, self::anything())
            ->willReturn($envelope)
        ;

        $bus = new MessageBus([
            $firstMiddleware,
            $secondMiddleware,
        ]);

        $bus->dispatch($envelope);
    }

    public function testItAddsTheStamps()
    {
        $finalEnvelope = (new MessageBus())->dispatch(new \stdClass(), [new DelayStamp(5), new BusNameStamp('bar')]);
        self::assertCount(2, $finalEnvelope->all());
    }

    public function testItAddsTheStampsToEnvelope()
    {
        $finalEnvelope = (new MessageBus())->dispatch(new Envelope(new \stdClass()), [new DelayStamp(5), new BusNameStamp('bar')]);
        self::assertCount(2, $finalEnvelope->all());
    }

    public function provideConstructorDataStucture(): iterable
    {
        yield 'iterator' => [new \ArrayObject([
            new SimpleMiddleware(),
            new SimpleMiddleware(),
        ])];

        yield 'array' => [[
            new SimpleMiddleware(),
            new SimpleMiddleware(),
        ]];

        yield 'generator' => [(function (): \Generator {
            yield new SimpleMiddleware();
            yield new SimpleMiddleware();
        })()];
    }

    /** @dataProvider provideConstructorDataStucture */
    public function testConstructDataStructure(iterable $dataStructure)
    {
        $bus = new MessageBus($dataStructure);
        $envelope = new Envelope(new DummyMessage('Hello'));
        $newEnvelope = $bus->dispatch($envelope);
        self::assertSame($envelope->getMessage(), $newEnvelope->getMessage());

        // Test rewindable capacity
        $envelope = new Envelope(new DummyMessage('Hello'));
        $newEnvelope = $bus->dispatch($envelope);
        self::assertSame($envelope->getMessage(), $newEnvelope->getMessage());
    }
}

class SimpleMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $envelope;
    }
}
