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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Tests\Fixtures\AnEnvelopeStamp;
use Symfony\Component\Messenger\Tests\Fixtures\ChildDummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommand;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessageInterface;

class MessageBusTest extends TestCase
{
    public function testItHasTheRightInterface()
    {
        $bus = new MessageBus();

        $this->assertInstanceOf(MessageBusInterface::class, $bus);
    }

    public function testItCallsMiddleware()
    {
        $message = new DummyMessage('Hello');
        $envelope = new Envelope($message);

        $firstMiddleware = $this->createMock(MiddlewareInterface::class);
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(static fn ($envelope, $stack) => $stack->next()->handle($envelope, $stack));

        $secondMiddleware = $this->createMock(MiddlewareInterface::class);
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
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

        $firstMiddleware = $this->createMock(MiddlewareInterface::class);
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(static fn ($envelope, $stack) => $stack->next()->handle($envelope->with(new AnEnvelopeStamp()), $stack));

        $secondMiddleware = $this->createMock(MiddlewareInterface::class);
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
            ->willReturnCallback(static fn ($envelope, $stack) => $stack->next()->handle($envelope, $stack));

        $thirdMiddleware = $this->createMock(MiddlewareInterface::class);
        $thirdMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelopeWithAnotherStamp, $this->anything())
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

        $firstMiddleware = $this->createMock(MiddlewareInterface::class);
        $firstMiddleware->expects($this->once())
            ->method('handle')
            ->with($envelope, $this->anything())
            ->willReturnCallback(static fn ($envelope, $stack) => $stack->next()->handle($expectedEnvelope, $stack));

        $secondMiddleware = $this->createMock(MiddlewareInterface::class);
        $secondMiddleware->expects($this->once())
            ->method('handle')
            ->with($expectedEnvelope, $this->anything())
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
        $this->assertCount(2, $finalEnvelope->all());
    }

    public function testItAddsTheStampsToEnvelope()
    {
        $finalEnvelope = (new MessageBus())->dispatch(new Envelope(new \stdClass()), [new DelayStamp(5), new BusNameStamp('bar')]);
        $this->assertCount(2, $finalEnvelope->all());
    }

    public static function provideConstructorDataStucture(): iterable
    {
        yield 'iterator' => [new \ArrayObject([
            new SimpleMiddleware(),
            new SimpleMiddleware(),
        ])];

        yield 'array' => [[
            new SimpleMiddleware(),
            new SimpleMiddleware(),
        ]];

        yield 'generator' => [(static function (): \Generator {
            yield new SimpleMiddleware();
            yield new SimpleMiddleware();
        })()];
    }

    #[DataProvider('provideConstructorDataStucture')]
    public function testConstructDataStructure(iterable $dataStructure)
    {
        $bus = new MessageBus($dataStructure);
        $envelope = new Envelope(new DummyMessage('Hello'));
        $newEnvelope = $bus->dispatch($envelope);
        $this->assertSame($envelope->getMessage(), $newEnvelope->getMessage());

        // Test rewindable capacity
        $envelope = new Envelope(new DummyMessage('Hello'));
        $newEnvelope = $bus->dispatch($envelope);
        $this->assertSame($envelope->getMessage(), $newEnvelope->getMessage());
    }

    public function testItDispatchesTheMessagesOfItsTypes()
    {
        $bus = new MessageBus([], [DummyMessageInterface::class]);

        $this->assertSame($message = new ChildDummyMessage('Hello'), $bus->dispatch($message)->getMessage());
    }

    public function testItRejectsTheMessagesOfOtherTypes()
    {
        $bus = new MessageBus([], [DummyMessageInterface::class, DummyCommand::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('This bus only dispatches messages of type "%s" or "%s", "stdClass" given.', DummyMessageInterface::class, DummyCommand::class));

        $bus->dispatch(new \stdClass());
    }

    public function testItDispatchesReceivedMessagesOfAnyType()
    {
        $bus = new MessageBus([], [DummyMessageInterface::class]);
        $envelope = new Envelope(new \stdClass(), [new ReceivedStamp('transport')]);

        $this->assertSame($envelope->getMessage(), $bus->dispatch($envelope)->getMessage());
    }

    public function testItThrowsTheExceptionOfTheFailingHandler()
    {
        $exception = new \DomainException('Not found.');
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static fn () => throw $exception]]))], [], true);

        try {
            $bus->dispatch(new DummyMessage('Hello'));
            $this->fail('The exception of the handler is thrown.');
        } catch (\DomainException $e) {
            $this->assertSame($exception, $e);
        }
    }

    public function testANestedDispatchThrowsTheExceptionOfTheInnerHandler()
    {
        $exception = new \DomainException('Not found.');
        $innerBus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyCommand::class => [static fn () => throw $exception]]))], [], true);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => [static fn () => $innerBus->dispatch(new DummyCommand())]]))], [], true);

        try {
            $bus->dispatch(new DummyMessage('Hello'));
            $this->fail('The exception of the inner handler is thrown.');
        } catch (\DomainException $e) {
            $this->assertSame($exception, $e);
        }
    }

    #[DataProvider('provideFailuresThatKeepHandlerFailedException')]
    public function testHandlerFailedExceptionIsKept(bool $unwrapExceptions, array $handlerNames, array $stamps)
    {
        $handlers = array_map(static fn ($name) => new HandlerDescriptor(static fn () => throw new \DomainException('Not found.'), ['alias' => $name]), $handlerNames);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([DummyMessage::class => $handlers]))], [], $unwrapExceptions);

        $this->expectException(HandlerFailedException::class);

        $bus->dispatch(new DummyMessage('Hello'), $stamps);
    }

    public static function provideFailuresThatKeepHandlerFailedException(): iterable
    {
        yield 'unwrapping disabled' => [false, ['first'], []];
        yield 'several failing handlers' => [true, ['first', 'second'], []];
        yield 'received message' => [true, ['first'], [new ReceivedStamp('transport')]];
    }
}

class SimpleMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        return $envelope;
    }
}
