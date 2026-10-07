<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\DecodeFailedMessageMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedispatchStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Generator\MessageGeneratorInterface;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\Messenger\SchedulerTransport;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class SchedulerTransportTest extends TestCase
{
    public function testGetFromIterator()
    {
        $messages = [
            (object) ['id' => 'first'],
            (object) ['id' => 'second'],
        ];
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function () use ($messages): \Generator {
            $trigger = $this->createStub(TriggerInterface::class);
            $triggerAt = new \DateTimeImmutable('2020-02-20T02:00:00', new \DateTimeZone('UTC'));
            yield (new MessageContext('default', 'id1', $trigger, $triggerAt)) => $messages[0];
            yield (new MessageContext('default', 'id2', $trigger, $triggerAt)) => $messages[1];
        });
        $transport = new SchedulerTransport($generator);

        foreach ($transport->get() as $i => $envelope) {
            $this->assertInstanceOf(Envelope::class, $envelope);
            $this->assertNotNull($stamp = $envelope->last(ScheduledStamp::class));
            $this->assertSame(array_shift($messages), $envelope->getMessage());
            $this->assertSame('default', $stamp->messageContext->name);
            $this->assertSame('id'.$i + 1, $stamp->messageContext->id);
        }

        $this->assertSame([], $messages);
    }

    public function testRedispatchMessageIsUnwrapped()
    {
        $message = new \stdClass();
        $envelopes = iterator_to_array((new SchedulerTransport($this->createGenerator(new RedispatchMessage(new Envelope($message, [new DelayStamp(10)]), ['transport']))))->get());

        $this->assertSame($message, $envelopes[0]->getMessage());
        $this->assertNotNull($envelopes[0]->last(RedispatchStamp::class));
        $this->assertSame(['transport'], $envelopes[0]->last(TransportNamesStamp::class)?->getTransportNames());
        $this->assertEquals(new DelayStamp(10), $envelopes[0]->last(DelayStamp::class));
        $this->assertSame('default', $envelopes[0]->last(ScheduledStamp::class)->messageContext->name);
        $this->assertSame('id', $envelopes[0]->last(ScheduledStamp::class)->messageContext->id);
    }

    #[TestWith([[], null])]
    #[TestWith(['', null])]
    #[TestWith([[''], null])]
    #[TestWith(['0', ['0']])]
    #[TestWith([['', 'async', 'orders'], ['async', 'orders']])]
    public function testTransportNamesOfARedispatchMessage(array|string $transportNames, ?array $expected)
    {
        $envelopes = iterator_to_array((new SchedulerTransport($this->createGenerator(new RedispatchMessage(new \stdClass(), $transportNames))))->get());

        $this->assertNotNull($envelopes[0]->last(RedispatchStamp::class));
        $this->assertSame($expected, $envelopes[0]->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testTransportNamesOfARedispatchMessageOverrideTheOnesOfItsEnvelope()
    {
        $envelopes = iterator_to_array((new SchedulerTransport($this->createGenerator(new RedispatchMessage(new Envelope(new \stdClass(), [new TransportNamesStamp('inner')]), 'async'))))->get());

        $this->assertSame(['async'], $envelopes[0]->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testRedispatchedMessageIsSentInsteadOfHandled()
    {
        $message = new \stdClass();
        $sender = $this->createSender();
        $handled = [];
        $bus = $this->createBus($sender, $handled);

        foreach ((new SchedulerTransport($this->createGenerator(new RedispatchMessage($message, 'async'))))->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('scheduler_default')));
        }

        $this->assertCount(1, $sender->sent);
        $this->assertSame($message, $sender->sent[0]->getMessage());
        $this->assertSame([], $handled);
    }

    public function testRedispatchingARedispatchedMessageFromTheFailureTransportSendsItToItsTransports()
    {
        $envelopes = iterator_to_array((new SchedulerTransport($this->createGenerator(new RedispatchMessage((object) ['text' => 'Hello'], 'async'))))->get());

        // the failure transport keeps the sendable stamps only
        $serializer = new PhpSerializer();
        $failed = $serializer->decode($serializer->encode($envelopes[0]->with(new ReceivedStamp('scheduler_default'), new SentToFailureTransportStamp('scheduler_default'))));

        $sender = $this->createSender();
        $handled = [];
        $this->createBus($sender, $handled)->dispatch(FailedMessageRepository::prepareForRedispatch($failed));

        $this->assertCount(1, $sender->sent);
        $this->assertEquals((object) ['text' => 'Hello'], $sender->sent[0]->getMessage());
        $this->assertSame([], $handled);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testEveryYieldedEnvelopeIsTrusted(bool $useMessengerRouting)
    {
        foreach ([new \stdClass(), new RedispatchMessage(new \stdClass(), 'async'), new RedispatchMessage(new Envelope(new \stdClass(), [TrustStamp::untrusted()]), 'async')] as $message) {
            $envelopes = iterator_to_array((new SchedulerTransport($this->createGenerator($message), $useMessengerRouting))->get());

            $this->assertTrue($envelopes[0]->last(TrustStamp::class)?->isTrusted());
        }
    }

    public function testFailedScheduledMessageOfASignedTypeIsRetriedFromAFailureTransportThatSignsIt()
    {
        $failureSerializer = new SigningSerializer(new PhpSerializer(), 'signing-key', [\stdClass::class]);
        $failureTransport = new InMemoryTransport($failureSerializer);

        $handled = [];
        $bus = new MessageBus([
            new DecodeFailedMessageMiddleware(new ServiceLocator(['failed' => static fn () => $failureSerializer])),
            new HandleMessageMiddleware(new HandlersLocator([\stdClass::class => [static function (\stdClass $message) use (&$handled) {
                $handled[] = $message;

                if (1 === \count($handled)) {
                    throw new \RuntimeException('The first run fails.');
                }
            }]])),
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(new ServiceLocator(['scheduler_default' => static fn () => $failureTransport])));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        (new Worker(['scheduler_default' => new SchedulerTransport($this->createGenerator((object) ['text' => 'Hello']))], $bus, $dispatcher))->run();

        $this->assertCount(1, $handled);
        $this->assertCount(1, $failed = $failureTransport->all());
        $this->assertEquals((object) ['text' => 'Hello'], $failed[0]->getMessage());
        $this->assertTrue($failed[0]->last(TrustStamp::class)?->isTrusted());

        // messenger:failed:retry consumes the failure transport
        (new Worker(['failed' => $failureTransport], $bus, $dispatcher))->run();

        $this->assertCount(2, $handled);
        $this->assertEquals((object) ['text' => 'Hello'], $handled[1]);
        $this->assertSame(0, $failureTransport->getMessageCount());
    }

    public function testMessageIsNotWrappedWhenUseMessengerRoutingIsDisabled()
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function (): \Generator {
            yield new MessageContext('default', 'id', $this->createStub(TriggerInterface::class), new \DateTimeImmutable()) => new \stdClass();
        });
        $envelopes = iterator_to_array((new SchedulerTransport($generator, useMessengerRouting: false))->get());

        $this->assertInstanceOf(\stdClass::class, $envelopes[0]->getMessage());
        $this->assertNull($envelopes[0]->last(RedispatchStamp::class));
    }

    public function testMessageIsRedispatchedWhenUseMessengerRoutingIsEnabled()
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function (): \Generator {
            yield new MessageContext('default', 'id', $this->createStub(TriggerInterface::class), new \DateTimeImmutable()) => new \stdClass();
        });
        $envelopes = iterator_to_array((new SchedulerTransport($generator, useMessengerRouting: true))->get());

        $this->assertInstanceOf(\stdClass::class, $envelopes[0]->getMessage());
        $this->assertNotNull($envelopes[0]->last(RedispatchStamp::class));
        $this->assertNull($envelopes[0]->last(TransportNamesStamp::class));
        $this->assertNotNull($envelopes[0]->last(ScheduledStamp::class));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testMessageTriggersDeprecationAndIsNotWrappedWhenUseMessengerRoutingIsNull()
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function (): \Generator {
            yield new MessageContext('default', 'id', $this->createStub(TriggerInterface::class), new \DateTimeImmutable()) => new \stdClass();
        });

        $this->expectUserDeprecationMessage('Since symfony/scheduler 8.2: Not setting the "scheduler.use_messenger_routing" configuration option is deprecated, it will default to "true" in version 9.0.');

        $envelopes = iterator_to_array((new SchedulerTransport($generator, useMessengerRouting: null))->get());

        $this->assertInstanceOf(\stdClass::class, $envelopes[0]->getMessage());
    }

    public function testExplicitRedispatchMessageDoesNotTriggerDeprecationWhenUseMessengerRoutingIsNull()
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function (): \Generator {
            yield new MessageContext('default', 'id', $this->createStub(TriggerInterface::class), new \DateTimeImmutable()) => new RedispatchMessage(new \stdClass(), ['transport']);
        });

        $envelopes = iterator_to_array((new SchedulerTransport($generator, useMessengerRouting: null))->get());

        $this->assertNotNull($envelopes[0]->last(RedispatchStamp::class));
        $this->assertSame(['transport'], $envelopes[0]->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testNoMessageMeansNoDeprecationWhenUseMessengerRoutingIsNull()
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(static function (): \Generator {
            return;
            yield;
        });

        $envelopes = iterator_to_array((new SchedulerTransport($generator, useMessengerRouting: null))->get());

        $this->assertSame([], $envelopes);
    }

    public function testAckIgnored()
    {
        $transport = new SchedulerTransport($this->createStub(MessageGeneratorInterface::class));

        $this->expectNotToPerformAssertions();
        $transport->ack(new Envelope(new \stdClass()));
    }

    public function testRejectException()
    {
        $transport = new SchedulerTransport($this->createStub(MessageGeneratorInterface::class));

        $this->expectNotToPerformAssertions();
        $transport->reject(new Envelope(new \stdClass()));
    }

    public function testSendException()
    {
        $transport = new SchedulerTransport($this->createStub(MessageGeneratorInterface::class));

        $this->expectException(LogicException::class);
        $transport->send(new Envelope(new \stdClass()));
    }

    private function createGenerator(object $message): MessageGeneratorInterface
    {
        $generator = $this->createStub(MessageGeneratorInterface::class);
        $generator->method('getMessages')->willReturnCallback(function () use ($message): \Generator {
            yield new MessageContext('default', 'id', $this->createStub(TriggerInterface::class), new \DateTimeImmutable()) => $message;
        });

        return $generator;
    }

    private function createSender(): SenderInterface
    {
        return new class implements SenderInterface {
            public array $sent = [];

            public function send(Envelope $envelope): Envelope
            {
                return $this->sent[] = $envelope;
            }
        };
    }

    private function createBus(SenderInterface $sender, array &$handled): MessageBus
    {
        $senders = new Container();
        $senders->set('async', $sender);

        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator([], $senders)),
            new HandleMessageMiddleware(new HandlersLocator(['*' => [static function (object $message) use (&$handled) {
                $handled[] = $message;
            }]])),
        ]);
    }
}
