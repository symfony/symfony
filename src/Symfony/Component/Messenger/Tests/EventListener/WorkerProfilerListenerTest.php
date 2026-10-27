<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\TraceableCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Debug\VirtualRequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\FileProfilerStorage;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Messenger\DataCollector\MessengerDataCollector;
use Symfony\Component\Messenger\DataCollector\WorkerMessageDataCollector;
use Symfony\Component\Messenger\Debug\WorkerMessageRequest;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\WorkerProfilerListener;
use Symfony\Component\Messenger\Handler\Acknowledger;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\BatchHandlerTrait;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ProfilerTokenStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Stopwatch\Stopwatch;

class WorkerProfilerListenerTest extends TestCase
{
    private FileProfilerStorage $storage;
    private Profiler $profiler;
    private RequestStack $requestStack;
    private Stopwatch $stopwatch;
    private MessengerDataCollector $collector;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->storage = new FileProfilerStorage('file:'.sys_get_temp_dir().'/sf_worker_profiler');
        $this->storage->purge();

        $this->stopwatch = new Stopwatch();
        $this->collector = new MessengerDataCollector();
        $this->profiler = new Profiler($this->storage);
        $this->profiler->add($this->collector);
        $this->profiler->add(new WorkerMessageDataCollector());
        $this->profiler->add(new TimeDataCollector($this->createConfiguredStub(KernelInterface::class, ['getStartTime' => 0.0]), $this->stopwatch));
        $this->requestStack = new VirtualRequestStack(new RequestStack());
    }

    protected function tearDown(): void
    {
        $this->storage->purge();
    }

    public function testItCollectsOneProfilePerConsumedMessage()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        $handled = new DummyMessage('handled');
        $failing = new SecondMessage();
        $startTime = microtime(true) * 1000;

        $this->runWorker([
            new Envelope($handled, [new ProfilerTokenStamp('0r1g1n')]),
            new Envelope($failing),
        ]);

        $profiles = $this->profiler->find(null, null, 10, null, null, null, null, static fn ($profile) => 'worker_message' === $profile['virtual_type']);
        $this->assertCount(2, $profiles);

        // the latest profile comes first
        [$failedProfile, $handledProfile] = array_map(fn ($profile) => $this->profiler->loadProfile($profile['token']), $profiles);

        $this->assertSame(DummyMessage::class, $handledProfile->getUrl());
        $this->assertSame('transport', $handledProfile->getMethod());
        $this->assertSame('0r1g1n', $handledProfile->getIp());
        $this->assertSame(WorkerMessageRequest::HANDLED, $handledProfile->getStatusCode());
        $this->assertFalse($handledProfile->hasErrors());

        $workerMessage = $handledProfile->getCollector('worker_message');
        $this->assertSame(DummyMessage::class, $workerMessage->getMessage()->getType());
        $this->assertSame('transport', $workerMessage->getTransport());
        $this->assertSame(1, $workerMessage->getAttempt());
        $this->assertSame('0r1g1n', $workerMessage->getOriginToken());
        // the worker dispatched the consumed message on the bus, and only that one
        $this->assertCount(1, $handledProfile->getCollector('messenger')->getMessages());

        $this->assertSame(SecondMessage::class, $failedProfile->getUrl());
        $this->assertSame('', $failedProfile->getIp());
        $this->assertSame(WorkerMessageRequest::REJECTED, $failedProfile->getStatusCode());
        $this->assertTrue($failedProfile->hasErrors());
        $this->assertCount(1, $failedProfile->getCollector('messenger')->getMessages());

        // the time collected for a message starts when the worker received it, not when the process started
        $this->assertGreaterThanOrEqual($startTime, $handledProfile->getCollector('time')->getStartTime());
        $this->assertArrayHasKey('__section__', $handledProfile->getCollector('time')->getEvents());
    }

    public function testItTellsTheMessagesThatWillBeRetried()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        $this->runWorker([new Envelope(new SecondMessage(), [new RedeliveryStamp(1)])], [
            WorkerMessageFailedEvent::class => static fn (WorkerMessageFailedEvent $event) => $event->setForRetry(),
        ]);

        $profile = $this->loadMessageProfiles()[0];

        $this->assertSame(WorkerMessageRequest::RETRIED, $profile->getStatusCode());
        $this->assertTrue($profile->hasErrors());
        $this->assertSame(2, $profile->getCollector('worker_message')->getAttempt());
    }

    public function testItKeepsTheOriginAndAttemptOfTheReceivedMessage()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        // e.g. an outbox relay sends the message on with the token of its own profile
        $this->runWorker([new Envelope(new DummyMessage('Hello'), [new ProfilerTokenStamp('0r1g1n'), new RedeliveryStamp(1)])], [
            WorkerMessageHandledEvent::class => static fn (WorkerMessageHandledEvent $event) => $event->addStamps(new ProfilerTokenStamp('r3l4y')),
        ]);

        $profile = $this->loadMessageProfiles()[0];

        $this->assertSame('0r1g1n', $profile->getIp());
        $this->assertSame('0r1g1n', $profile->getCollector('worker_message')->getOriginToken());
        $this->assertSame(2, $profile->getCollector('worker_message')->getAttempt());
    }

    public function testItCollectsNothingForTheMessagesTheWorkerSkips()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        // a listener running after the profile was started
        $skip = static fn (WorkerMessageReceivedEvent $event) => $event->shouldHandle(!$event->getEnvelope()->getMessage() instanceof DummyMessage);

        $this->runWorker([new Envelope(new DummyMessage('skipped')), new Envelope(new SecondMessage())], [
            WorkerMessageReceivedEvent::class => [$skip, -4096],
        ]);

        $profiles = $this->loadMessageProfiles();
        $this->assertCount(1, $profiles);
        $this->assertSame(SecondMessage::class, $profiles[0]->getUrl());
    }

    public function testTheMessagesHandledByABatchHandlerArePendingUntilTheBatchIsProcessed()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        $handler = new class implements BatchHandlerInterface {
            use BatchHandlerTrait;

            public function __invoke(DummyMessage $message, ?Acknowledger $ack = null): mixed
            {
                return $this->handle($message, $ack);
            }

            private function process(array $jobs): void
            {
                foreach ($jobs as [$message, $ack]) {
                    $ack->ack($message);
                }
            }

            private function getBatchSize(): int
            {
                return 2;
            }
        };

        $this->runWorker([new Envelope(new DummyMessage('first')), new Envelope(new DummyMessage('second'))], handlers: [DummyMessage::class => [$handler]]);

        // the batch is processed while handling the last message, whose profile holds the work done for the whole batch
        $this->assertSame([WorkerMessageRequest::HANDLED, WorkerMessageRequest::PENDING], array_map(static fn ($profile) => $profile->getStatusCode(), $this->loadMessageProfiles()));
    }

    public function testItCollectsNothingWhenTheMessagesAreHandledConcurrently()
    {
        $command = new TraceableCommand((new Command('messenger:consume'))->addOption('concurrency', null, InputOption::VALUE_REQUIRED, '', 1), $this->stopwatch);
        $command->input = new ArrayInput(['--concurrency' => '2'], $command->getDefinition());
        $command->output = $output = new BufferedOutput();
        $this->requestStack->push(new CliRequest($command));

        $listener = new WorkerProfilerListener($this->profiler, $this->requestStack, $this->stopwatch);
        $this->runWorker([new Envelope(new DummyMessage('concurrent'))], listener: $listener);

        $this->assertSame([], $this->loadMessageProfiles());
        $this->assertStringContainsString('The messages are not profiled one by one', $output->fetch());

        // a later worker handling one message at a time is profiled again
        $command->input = new ArrayInput(['--concurrency' => '1'], $command->getDefinition());
        $this->runWorker([new Envelope(new DummyMessage('sequential'))], listener: $listener);

        $this->assertCount(1, $this->loadMessageProfiles());
    }

    public function testItWarnsAboutConcurrencyOnlyWhenProfiling()
    {
        $command = new TraceableCommand((new Command('messenger:consume'))->addOption('concurrency', null, InputOption::VALUE_REQUIRED, '', 1), $this->stopwatch);
        $command->input = new ArrayInput(['--concurrency' => '2'], $command->getDefinition());
        $command->output = $output = new BufferedOutput();
        $this->requestStack->push(new CliRequest($command));
        $this->profiler->disable();

        $this->runWorker([new Envelope(new DummyMessage('concurrent'))]);

        $this->assertSame('', $output->fetch());
    }

    public function testItSavesTheProfileOfTheMessageBeingHandledWhenTheWorkerFails()
    {
        $command = new TraceableCommand(new Command('messenger:consume'), $this->stopwatch);
        $this->requestStack->push($cliRequest = new CliRequest($command));

        try {
            $this->runWorker([new Envelope(new DummyMessage('acked'))], [
                WorkerMessageHandledEvent::class => static fn () => throw new \RuntimeException('Connection lost.'),
            ]);
            $this->fail('The worker should fail.');
        } catch (\RuntimeException $e) {
        }

        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $this->dispatcher->dispatch(new ConsoleErrorEvent($input, $output, $e, $command), ConsoleEvents::ERROR);
        $this->dispatcher->dispatch(new ConsoleTerminateEvent($command, $input, $output, 1), ConsoleEvents::TERMINATE);

        $profile = $this->loadMessageProfiles()[0];
        $this->assertSame(WorkerMessageRequest::PENDING, $profile->getStatusCode());
        $this->assertTrue($profile->hasErrors());
        // the profile of the command can be saved
        $this->assertSame($cliRequest, $this->requestStack->getCurrentRequest());
    }

    public function testItIgnoresTheCommandsRunWhileHandlingAMessage()
    {
        $this->requestStack->push(new CliRequest(new TraceableCommand(new Command('messenger:consume'), $this->stopwatch)));

        $innerCommand = new Command('app:scheduled-task');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        // like RunCommandMessageHandler, which runs the command of a scheduled task
        $this->runWorker([new Envelope(new DummyMessage('run command'))], handlers: [DummyMessage::class => [function () use ($innerCommand, $input, $output) {
            $this->dispatcher->dispatch(new ConsoleErrorEvent($input, $output, new \RuntimeException('Recovered.'), $innerCommand), ConsoleEvents::ERROR);
            $this->dispatcher->dispatch(new ConsoleTerminateEvent($innerCommand, $input, $output, 1), ConsoleEvents::TERMINATE);

            $this->assertInstanceOf(WorkerMessageRequest::class, $this->requestStack->getCurrentRequest());
        }]]);

        $profiles = $this->loadMessageProfiles();
        $this->assertCount(1, $profiles);
        $this->assertSame(WorkerMessageRequest::HANDLED, $profiles[0]->getStatusCode());
        $this->assertFalse($profiles[0]->hasErrors());
    }

    public function testItCollectsNothingWhenTheWorkerIsNotProfiled()
    {
        $this->runWorker([new Envelope(new DummyMessage('not profiled'))]);

        $this->assertSame([], $this->profiler->find(null, null, 10, null, null, null, null));
    }

    public function testItStampsTheSentMessagesWithTheTokenOfTheCurrentProfile()
    {
        $listener = new WorkerProfilerListener($this->profiler, $this->requestStack, $this->stopwatch);

        $event = new SendMessageToTransportsEvent(new Envelope(new DummyMessage('sent')), []);
        $listener->stampProfilerToken($event);
        $this->assertNull($event->getEnvelope()->last(ProfilerTokenStamp::class), 'Nothing is profiled, so nothing links to a profile.');

        $this->requestStack->push($request = new Request());
        $listener->stampProfilerToken($event);
        $token = $event->getEnvelope()->last(ProfilerTokenStamp::class)?->getToken();
        $this->assertIsString($token);

        $second = new SendMessageToTransportsEvent(new Envelope(new DummyMessage('sent too')), []);
        $listener->stampProfilerToken($second);
        $this->assertSame($token, $second->getEnvelope()->last(ProfilerTokenStamp::class)->getToken());

        $this->assertSame($token, $this->profiler->collect($request, new Response())->getToken());
    }

    public function testItStampsTheMessagesSentFromASubRequestWithTheTokenOfTheMainRequest()
    {
        $listener = new WorkerProfilerListener($this->profiler, $this->requestStack, $this->stopwatch);

        $this->requestStack->push($mainRequest = new Request());
        $this->requestStack->push(Request::create('/fragment'));

        $event = new SendMessageToTransportsEvent(new Envelope(new DummyMessage('sent')), []);
        $listener->stampProfilerToken($event);

        $this->assertSame($this->profiler->reserveToken($mainRequest), $event->getEnvelope()->last(ProfilerTokenStamp::class)?->getToken());
    }

    #[DataProvider('provideSentMessages')]
    public function testTheCollectorTellsTheTokenOfTheSentMessages(array $stamps, array $sentStamps, ?string $token)
    {
        $bus = new class($sentStamps) implements MessageBusInterface {
            public function __construct(private array $sentStamps)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return Envelope::wrap($message, $stamps)->with(...$this->sentStamps);
            }
        };
        $this->collector->registerBus('default', $bus = new TraceableMessageBus($bus));

        $bus->dispatch(new DummyMessage('sent'), $stamps);
        $this->collector->lateCollect();

        $this->assertSame($token, $this->collector->getMessages()[0]['profiler_token'] ?? null);
        $this->assertSame($token ? DummyMessage::class : null, $this->collector->getMessages()[0]['message_class'] ?? null);
    }

    public static function provideSentMessages(): iterable
    {
        yield 'sent' => [[], [new ProfilerTokenStamp('abc123'), new SentStamp('sender', 'async')], 'abc123'];
        yield 'relayed by an outbox' => [[new ReceivedStamp('outbox'), new ProfilerTokenStamp('0r1g1n')], [new ProfilerTokenStamp('r3l4y'), new SentStamp('sender', 'async')], 'r3l4y'];
        yield 'received' => [[new ReceivedStamp('async'), new ProfilerTokenStamp('0r1g1n')], [], null];
        yield 'handled by a sync transport' => [[new ReceivedStamp('sync'), new SentStamp('sender', 'sync'), new ProfilerTokenStamp('0r1g1n')], [], null];
        yield 'not sent' => [[], [new ProfilerTokenStamp('abc123')], null];
    }

    /**
     * @return list<Profile> The latest profile first
     */
    private function loadMessageProfiles(): array
    {
        $profiles = $this->profiler->find(null, null, 10, null, null, null, null, static fn ($profile) => 'worker_message' === $profile['virtual_type']);

        return array_map(fn ($profile) => $this->profiler->loadProfile($profile['token']), $profiles);
    }

    /**
     * @param list<Envelope>                $envelopes
     * @param array<string, callable|array> $listeners
     * @param array<string, list<callable>> $handlers
     */
    private function runWorker(array $envelopes, array $listeners = [], array $handlers = [], ?WorkerProfilerListener $listener = null): void
    {
        $bus = new TraceableMessageBus(new MessageBus([new HandleMessageMiddleware(new HandlersLocator($handlers + [
            DummyMessage::class => [static fn () => null],
            SecondMessage::class => [static fn () => throw new \RuntimeException('Failure.')],
        ]))]));
        $this->collector->registerBus('default', $bus);

        $this->dispatcher = $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($listener ?? new WorkerProfilerListener($this->profiler, $this->requestStack, $this->stopwatch));
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(\count($envelopes)));
        foreach ($listeners as $eventName => $listener) {
            \is_array($listener) ? $dispatcher->addListener($eventName, ...$listener) : $dispatcher->addListener($eventName, $listener);
        }

        $worker = new Worker(['transport' => new DummyReceiver([$envelopes])], $bus, $dispatcher, clock: new MockClock());
        $worker->run();
    }
}
