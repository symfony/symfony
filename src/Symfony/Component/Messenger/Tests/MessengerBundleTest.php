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
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Tests\Fixtures\FailingDummyMessageHandler;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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
