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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\PropagatedStampInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
use Symfony\Component\Messenger\Stamp\UnverifiedDecodingFailureStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\FailingDummyMessageHandler;
use Symfony\Component\Messenger\Tests\Fixtures\SecondMessage;
use Symfony\Component\Messenger\Tests\Fixtures\ThirdMessage;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Uid\Uuid;

class MessengerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_messenger_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheBusRoutesMessagesToTheirTransport()
    {
        $kernel = new TestMessengerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $bus = $container->get('test.messenger.default_bus');
        $this->assertInstanceOf(MessageBusInterface::class, $bus);

        $transport = $container->get('test.messenger.transport.async');
        $this->assertInstanceOf(InMemoryTransport::class, $transport);

        $bus->dispatch(new DummyMessage('hello'));
        $this->assertCount(1, $transport->getSent());
    }

    public function testTheServicesNeedingAnotherBundleAreDropped()
    {
        $kernel = new TestMessengerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        // these need a lock factory, a cache pool, a stopwatch and a profiler, which only other bundles register
        $this->assertFalse($container->has('messenger.middleware.deduplicate_middleware'));
        $this->assertFalse($container->has('messenger.listener.stop_worker_on_restart_signal_listener'));
        $this->assertFalse($container->has('messenger.middleware.traceable'));
        $this->assertFalse($container->has('data_collector.messenger'));
    }

    public function testTheSyncTransportRetriesThenSendsToTheFailureTransport()
    {
        $kernel = new TestSyncRetryKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        FailingDummyMessageHandler::$calls = 0;

        $envelope = $container->get('test.messenger.default_bus')->dispatch(new DummyMessage('Hey'));

        $this->assertSame(3, FailingDummyMessageHandler::$calls);
        $this->assertSame('sync_with_retry', $envelope->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());

        $failureTransport = $container->get('test.messenger.transport.failed');
        $this->assertInstanceOf(InMemoryTransport::class, $failureTransport);
        $this->assertCount(1, $failureTransport->getSent());

        $failed = $failureTransport->getSent()[0];
        $this->assertInstanceOf(DummyMessage::class, $failed->getMessage());
        $this->assertSame('sync_with_retry', $failed->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
        $this->assertSame('Handling "Hey" failed 3 time(s).', $failed->last(ErrorDetailsStamp::class)?->getExceptionMessage());
        $this->assertCount(3, $failed->all(RedeliveryStamp::class));
    }

    public function testAReplayedDecodingFailureKeepsTheIdentityAndThePropagatedStampsOfItsMessage()
    {
        $kernel = new TestFlowContextKernel('test', true, $this->varDir);
        $kernel->boot();
        FlowContextHandler::$handled = [];

        $encoded = new PhpSerializer()->encode(new Envelope(new DummyMessage('Hey'), [
            new BusNameStamp('messenger.bus.default'),
            new FlowTenantStamp('t1'),
            new MessageIdStamp('original-id'),
            new CorrelationStamp('original-correlation'),
        ]));
        // what PhpSerializer::decode() returns for a body it cannot decode: the bus name only
        $failure = MessageDecodingFailedException::wrap($encoded, 'Could not decode Envelope.')->with(new BusNameStamp('messenger.bus.default'), new ReceivedStamp('async'));

        $kernel->getContainer()->get('test.messenger.default_bus')->dispatch($failure);

        $this->assertCount(2, FlowContextHandler::$handled);
        [$decoded, $child] = FlowContextHandler::$handled;

        $this->assertInstanceOf(DummyMessage::class, $decoded->getMessage());
        $this->assertSame(['original-id'], array_map(static fn (MessageIdStamp $stamp) => $stamp->getId(), $decoded->all(MessageIdStamp::class)));
        $this->assertSame(['original-correlation'], array_map(static fn (CorrelationStamp $stamp) => $stamp->getId(), $decoded->all(CorrelationStamp::class)));

        $this->assertInstanceOf(SecondMessage::class, $child->getMessage());
        $this->assertSame(['t1'], array_map(static fn (FlowTenantStamp $stamp) => $stamp->tenant, $child->all(FlowTenantStamp::class)));
        $this->assertSame('original-correlation', $child->last(CorrelationStamp::class)?->getId());
        $this->assertSame('original-id', $child->last(CausationStamp::class)?->getId());
    }

    public function testTheStampsOfAnUnverifiedDecodingFailureDoNotReachWhatItsSignedMessageDispatches()
    {
        $kernel = new TestFlowContextKernel('test', true, $this->varDir);
        $kernel->boot();
        FlowContextHandler::$handled = [];

        $encoded = new PhpSerializer()->encode(new Envelope(new DummyMessage('Hey'), [new BusNameStamp('messenger.bus.default'), new FlowTenantStamp('good')]));
        $unverifiedStamps = [new BusNameStamp('messenger.bus.default'), new FlowTenantStamp('evil')];
        $failure = MessageDecodingFailedException::wrap($encoded, 'Could not retrieve the claim.')
            ->with(...$unverifiedStamps)
            ->with(new ReceivedStamp('async'), new UnverifiedDecodingFailureStamp($unverifiedStamps, [DummyMessage::class]));

        $kernel->getContainer()->get('test.messenger.default_bus')->dispatch($failure);

        $this->assertCount(2, FlowContextHandler::$handled);
        [$decoded, $child] = FlowContextHandler::$handled;

        $this->assertSame(['good'], array_map(static fn (FlowTenantStamp $stamp) => $stamp->tenant, $decoded->all(FlowTenantStamp::class)));
        $this->assertSame(['good'], array_map(static fn (FlowTenantStamp $stamp) => $stamp->tenant, $child->all(FlowTenantStamp::class)));
    }

    public function testAMessageHandledThroughTheSyncTransportIsNotItsOwnCause()
    {
        $kernel = new TestFlowContextKernel('test', true, $this->varDir);
        $kernel->boot();
        FlowContextHandler::$handled = [];

        $kernel->getContainer()->get('test.messenger.default_bus')->dispatch(new ThirdMessage());

        $this->assertCount(2, FlowContextHandler::$handled);
        [$handled, $child] = FlowContextHandler::$handled;

        $this->assertInstanceOf(ThirdMessage::class, $handled->getMessage());
        $this->assertNotNull($handled->last(ReceivedStamp::class));
        $id = $handled->last(MessageIdStamp::class)->getId();
        $this->assertNull($handled->last(CausationStamp::class));
        $this->assertSame($id, $handled->last(CorrelationStamp::class)?->getId());

        $this->assertInstanceOf(SecondMessage::class, $child->getMessage());
        $this->assertSame($id, $child->last(CausationStamp::class)?->getId());
        $this->assertSame($id, $child->last(CorrelationStamp::class)?->getId());
    }

    public function testAnApplicationDefinedMessageIdGeneratorIsUsed()
    {
        $kernel = new TestMessageIdGeneratorKernel('test', true, $this->varDir);
        $kernel->boot();

        $envelope = $kernel->getContainer()->get('test.messenger.default_bus')->dispatch(new DummyMessage('Hey'));

        $this->assertSame('app-message-id', $envelope->last(MessageIdStamp::class)?->getId());
    }

    #[RunInSeparateProcess]
    public function testAnApplicationDefinedMessageIdGeneratorIsUsedWithoutTheUidComponent()
    {
        self::hideTheUidComponent();

        $kernel = new TestMessageIdGeneratorKernel('test', true, $this->varDir);
        $kernel->boot();

        $envelope = $kernel->getContainer()->get('test.messenger.default_bus')->dispatch(new DummyMessage('Hey'));

        $this->assertSame('app-message-id', $envelope->last(MessageIdStamp::class)?->getId());
    }

    #[RunInSeparateProcess]
    public function testIdentityStampsGenerateTheirOwnIdsWithoutTheUidComponent()
    {
        self::hideTheUidComponent();

        $kernel = new TestFlowContextKernel('test', true, $this->varDir);
        $kernel->boot();

        $envelope = $kernel->getContainer()->get('test.messenger.default_bus')->dispatch(new SecondMessage());

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $envelope->last(MessageIdStamp::class)->getId());
    }

    public function testAMessageHandledAfterTheCurrentBusIsTheContextOfWhatItsHandlerDispatches()
    {
        $kernel = new TestFlowContextKernel('test', true, $this->varDir);
        $kernel->boot();
        FlowContextHandler::$handled = [];

        $kernel->getContainer()->get('test.messenger.default_bus')->dispatch(new FlowDelayingMessage(), [new FlowTenantStamp('root')]);

        $this->assertCount(3, FlowContextHandler::$handled);
        [$root, $delayed, $child] = FlowContextHandler::$handled;

        $this->assertInstanceOf(FlowDelayingMessage::class, $root->getMessage());
        $this->assertInstanceOf(FlowDelayedMessage::class, $delayed->getMessage());
        $this->assertInstanceOf(SecondMessage::class, $child->getMessage());

        $rootId = $root->last(MessageIdStamp::class)->getId();
        $delayedId = $delayed->last(MessageIdStamp::class)->getId();
        $this->assertSame($rootId, $delayed->last(CausationStamp::class)?->getId());
        $this->assertSame($delayedId, $child->last(CausationStamp::class)?->getId());
        $this->assertSame($rootId, $child->last(CorrelationStamp::class)?->getId());
        $this->assertSame(['delayed'], array_map(static fn (FlowTenantStamp $stamp) => $stamp->tenant, $delayed->all(FlowTenantStamp::class)));
        $this->assertSame(['delayed'], array_map(static fn (FlowTenantStamp $stamp) => $stamp->tenant, $child->all(FlowTenantStamp::class)));
    }

    public function testOnlyTheTransportsThatSignEveryMessageSignWhenNoHandlerAsksForIt()
    {
        $kernel = new TestSigningKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $container->get('test.messenger.default_bus')->dispatch(new DummyMessage('hello'));

        $this->assertCount(1, $signed = $container->get('test.messenger.transport.signed')->getSent());
        $this->assertTrue($signed[0]->last(TrustStamp::class)?->isTrusted());

        $this->assertCount(1, $plain = $container->get('test.messenger.transport.plain')->getSent());
        $this->assertInstanceOf(DummyMessage::class, $plain[0]->getMessage());
        $this->assertNull($plain[0]->last(TrustStamp::class));

        $this->assertInstanceOf(PhpSerializer::class, $container->get('test.messenger.transport.serializer_locator')->get('plain'));
    }

    public function testTheSyncTransportSendsASignedMessageToAFailureTransportThatSignsEveryMessage()
    {
        $kernel = new TestSignedSyncRetryKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        FailingDummyMessageHandler::$calls = 0;

        $container->get('test.messenger.default_bus')->dispatch(new DummyMessage('Hey'));

        $this->assertSame(2, FailingDummyMessageHandler::$calls);
        $this->assertCount(1, $failed = $container->get('test.messenger.transport.failed')->getSent());
        $this->assertInstanceOf(DummyMessage::class, $failed[0]->getMessage());
        $this->assertTrue($failed[0]->last(TrustStamp::class)?->isTrusted());
        $this->assertSame('sync_with_retry', $failed[0]->last(SentToFailureTransportStamp::class)?->getOriginalReceiverName());
    }

    public function testAFailureTransportThatSignsEveryMessageKeepsTheFailuresOfATransportThatDoesNotSignAsUnverified()
    {
        $kernel = new TestSignedFailureTransportKernel('test', true, $this->varDir);
        $kernel->boot();
        $serializer = $kernel->getContainer()->get('test.messenger.transport.serializer_locator')->get('failed');

        $failed = $serializer->decode($encoded = $serializer->encode(new Envelope(new DummyMessage('hello'), [new ReceivedStamp('plain')])));

        $this->assertSame('unverified', $encoded['headers']['Sign-Trust']);
        $this->assertInstanceOf(DummyMessage::class, $failed->getMessage());
        $this->assertFalse($failed->last(TrustStamp::class)?->isTrusted());

        $failed = $serializer->decode($serializer->encode(new Envelope(new RedispatchMessage(new DummyMessage('hello'), 'plain'), [new ReceivedStamp('plain')])));

        $this->assertInstanceOf(MessageDecodingFailedException::class, $failed->getMessage());
        $this->assertSame(\sprintf('Message "%s" requires a verified signature, but it is signed as unverified.', RedispatchMessage::class), $failed->getMessage()->getMessage());
    }

    private static function hideTheUidComponent(): void
    {
        ClassExistsMock::register(ContainerBuilder::class);
        ClassExistsMock::withMockedClasses([Uuid::class => false]);
    }
}

class TestMessengerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('messenger', [
            'transports' => ['async' => 'in-memory://'],
            'routing' => [DummyMessage::class => 'async'],
        ]);
        $container->services()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
            ->alias('test.messenger.transport.async', 'messenger.transport.async')->public()
        ;
    }
}

class TestSyncRetryKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('messenger', [
            'failure_transport' => 'failed',
            'transports' => [
                'sync_with_retry' => [
                    'dsn' => 'sync://?retry=true&failure_transport=true',
                    'retry_strategy' => ['max_retries' => 2],
                ],
                'failed' => 'in-memory://',
            ],
            'routing' => [DummyMessage::class => 'sync_with_retry'],
        ]);
        $container->services()
            ->set(FailingDummyMessageHandler::class)->autoconfigure()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
            ->alias('test.messenger.transport.failed', 'messenger.transport.failed')->public()
        ;
    }
}

class TestFlowContextKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('messenger', [
            'identity_stamps' => true,
            'transports' => [
                'async' => 'in-memory://',
                'sync' => 'sync://',
            ],
            'routing' => [ThirdMessage::class => 'sync'],
        ]);
        $container->services()
            ->set(FlowContextHandler::class)->autowire()->autoconfigure()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
        ;
    }
}

class TestMessageIdGeneratorKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    public static function generateMessageId(): string
    {
        return 'app-message-id';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('messenger', [
            'identity_stamps' => true,
            'transports' => ['async' => 'in-memory://'],
            'routing' => [DummyMessage::class => 'async'],
        ]);
        $container->services()
            ->set('messenger.message_id_generator', \Closure::class)
                ->factory([\Closure::class, 'fromCallable'])
                ->args([[self::class, 'generateMessageId']])
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
        ;
    }
}

class FlowContextHandler
{
    /** @var list<Envelope> */
    public static array $handled = [];

    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[AsMessageHandler]
    public function onDummyMessage(DummyMessage $message, Envelope $envelope): void
    {
        self::$handled[] = $envelope;
        $this->bus->dispatch(new SecondMessage());
    }

    #[AsMessageHandler]
    public function onThirdMessage(ThirdMessage $message, Envelope $envelope): void
    {
        self::$handled[] = $envelope;
        $this->bus->dispatch(new SecondMessage());
    }

    #[AsMessageHandler]
    public function onSecondMessage(SecondMessage $message, Envelope $envelope): void
    {
        self::$handled[] = $envelope;
    }

    #[AsMessageHandler]
    public function onFlowDelayingMessage(FlowDelayingMessage $message, Envelope $envelope): void
    {
        self::$handled[] = $envelope;
        $this->bus->dispatch(new FlowDelayedMessage(), [new DispatchAfterCurrentBusStamp(), new FlowTenantStamp('delayed')]);
    }

    #[AsMessageHandler]
    public function onFlowDelayedMessage(FlowDelayedMessage $message, Envelope $envelope): void
    {
        self::$handled[] = $envelope;
        $this->bus->dispatch(new SecondMessage());
    }
}

class FlowDelayingMessage
{
}

class FlowDelayedMessage
{
}

class FlowTenantStamp implements PropagatedStampInterface
{
    public function __construct(
        public readonly string $tenant,
    ) {
    }
}

class TestSigningKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        // the handler of RedispatchMessage asks for a signature: remove it so that no handler does
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->removeDefinition('messenger.redispatch_message_handler');
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('kernel.secret', 's3cr3t');
        $container->extension('messenger', [
            'transports' => [
                'signed' => ['dsn' => 'in-memory://?serialize=true', 'sign' => true],
                'plain' => 'in-memory://?serialize=true',
            ],
            'routing' => [DummyMessage::class => ['signed', 'plain']],
        ]);
        $container->services()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
            ->alias('test.messenger.transport.signed', 'messenger.transport.signed')->public()
            ->alias('test.messenger.transport.plain', 'messenger.transport.plain')->public()
            ->alias('test.messenger.transport.serializer_locator', 'messenger.transport.serializer_locator')->public()
        ;
    }
}

class TestSignedSyncRetryKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('kernel.secret', 's3cr3t');
        $container->extension('messenger', [
            'failure_transport' => 'failed',
            'transports' => [
                'sync_with_retry' => [
                    'dsn' => 'sync://?retry=true&failure_transport=true',
                    'retry_strategy' => ['max_retries' => 1],
                    'sign' => true,
                ],
                'failed' => ['dsn' => 'in-memory://?serialize=true', 'sign' => true],
            ],
            'routing' => [DummyMessage::class => 'sync_with_retry'],
        ]);
        $container->services()
            ->set(FailingDummyMessageHandler::class)->autoconfigure()
            ->alias('test.messenger.default_bus', 'messenger.default_bus')->public()
            ->alias('test.messenger.transport.failed', 'messenger.transport.failed')->public()
        ;
    }
}

class TestSignedFailureTransportKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MessengerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->parameters()->set('kernel.secret', 's3cr3t');
        $container->extension('messenger', [
            'failure_transport' => 'failed',
            'transports' => [
                'plain' => 'in-memory://?serialize=true',
                'failed' => ['dsn' => 'in-memory://?serialize=true', 'sign' => true],
            ],
        ]);
        $container->services()
            ->alias('test.messenger.transport.serializer_locator', 'messenger.transport.serializer_locator')->public()
        ;
    }
}
