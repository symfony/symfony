<?php

namespace Symfony\Component\Mailer\Tests\Messenger\Middleware;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\Middleware\SendMailAfterCurrentBusMiddleware;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Mime\RawMessage;

class SendMailAfterCurrentBusMiddlewareTest extends TestCase
{
    public function testMailSentAfterMainMessage()
    {
        $message = new DummyMessage();
        $sendMail = new SendEmailMessage(new RawMessage('foo'));

        $middleware = new SendMailAfterCurrentBusMiddleware();
        $handlingMiddleware = $this->createMock(MiddlewareInterface::class);

        $bus = new MessageBus([
            $middleware,
            new DispatchAfterCurrentBusMiddleware(),
            $dispatchingMiddleware = new DispatchingMiddleware([
                $sendMail,
            ]),
            $handlingMiddleware,
        ]);

        $dispatchingMiddleware->setBus($bus);

        // Expect main dispatched message to be handled first:
        $this->expectHandledMessage($handlingMiddleware, 0, $message);
        // Then, expect mail to be sent:
        $this->expectHandledMessage($handlingMiddleware, 1, $sendMail);

        $bus->dispatch($message);
    }

    /**
     * @param MiddlewareInterface|MockObject $handlingMiddleware
     */
    private function expectHandledMessage(MiddlewareInterface $handlingMiddleware, int $at, $message): void
    {
        $handlingMiddleware->expects($this->at($at))->method('handle')->with($this->callback(function (Envelope $envelope) use ($message) {
            return $envelope->getMessage() === $message;
        }))->willReturnCallback(function ($envelope, StackInterface $stack) {
            return $stack->next()->handle($envelope, $stack);
        });
    }
}

class DummyMessage
{
}

class DispatchingMiddleware implements MiddlewareInterface
{
    /** @var MessageBusInterface */
    private $bus;
    private $messages;

    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function setBus(MessageBusInterface $bus): void
    {
        $this->bus = $bus;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        while ($message = array_shift($this->messages)) {
            $this->bus->dispatch($message);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
