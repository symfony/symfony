<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Messenger;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Messenger\EarlyExpirationDispatcher;
use Symfony\Component\Cache\Messenger\EarlyExpirationHandler;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\Cache\Messenger\EarlyExpirationResult;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\InMemoryTransport as LegacyInMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

class EarlyExpirationDispatcherTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testFetch()
    {
        $logger = new TestLogger();
        $pool = new FilesystemAdapter();
        $pool->setLogger($logger);

        $item = $pool->getItem('foo');

        $computationService = new class {
            public function __invoke(CacheItem $item)
            {
                return 123;
            }
        };

        $container = new Container();
        $container->set('computation_service', $computationService);
        $container->set('cache_pool', $pool);

        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));

        $dispatcher = new EarlyExpirationDispatcher(new MessageBus(), $reverseContainer);

        $saveResult = null;
        $pool->setCallbackWrapper(static function (callable $callback, CacheItem $item, bool &$save, AdapterInterface $pool, \Closure $setMetadata, ?LoggerInterface $logger) use ($dispatcher, &$saveResult) {
            try {
                return $dispatcher($callback, $item, $save, $pool, $setMetadata, $logger);
            } finally {
                $saveResult = $save;
            }
        });

        $this->assertSame(345, $pool->get('foo', static fn () => 345));
        $this->assertTrue($saveResult);

        $expected = [
            [
                'level' => 'info',
                'message' => 'Computing item "{key}" online: item is stale',
                'context' => ['key' => 'foo'],
            ],
        ];
        $this->assertSame($expected, $logger->records);
    }

    public function testEarlyExpiration()
    {
        $logger = new TestLogger();
        $pool = new FilesystemAdapter();
        $pool->setLogger($logger);

        $item = $pool->getItem('foo');
        $pool->save($item->set(789));
        $item = $pool->getItem('foo');

        $computationService = new class {
            public function __invoke(CacheItem $item)
            {
                return 123;
            }
        };

        $container = new Container();
        $container->set('computation_service', $computationService);
        $container->set('cache_pool', $pool);

        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));
        $msg = EarlyExpirationMessage::create($reverseContainer, $computationService, $item, $pool);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($msg)
            ->willReturn(new Envelope($msg));

        $dispatcher = new EarlyExpirationDispatcher($bus, $reverseContainer);

        $saveResult = true;
        $setMetadata = static function () {
        };
        $dispatcher($computationService, $item, $saveResult, $pool, $setMetadata, $logger);

        $this->assertFalse($saveResult);

        $expected = [
            [
                'level' => 'info',
                'message' => 'Item "{key}" sent for recomputation',
                'context' => ['key' => 'foo'],
            ],
        ];
        $this->assertSame($expected, $logger->records);
    }

    #[Group('time-sensitive')]
    public function testSynchronousEarlyExpirationReturnsNullAndSkipsDuplicates()
    {
        $pool = new class extends FilesystemAdapter {
            public bool $failSave = false;

            public function save(CacheItemInterface $item): bool
            {
                return !$this->failSave && parent::save($item);
            }
        };
        $pool->get('null-sync', static function (CacheItem $item) {
            $item->expiresAfter(3600);
            usleep(1000);

            return 234;
        });
        $pool->failSave = true;

        $callback = new class {
            public int $calls = 0;

            public function __invoke()
            {
                ++$this->calls;

                return null;
            }
        };

        $container = new Container();
        $container->set('callback', $callback);
        $container->set('pool', $pool);
        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));
        $handler = new EarlyExpirationHandler($reverseContainer);
        $observer = new HandlerDescriptor(static fn (EarlyExpirationMessage $message) => $message->getItem(), ['alias' => 'observer']);
        $tupleObserver = new HandlerDescriptor(static fn (EarlyExpirationMessage $message) => [$message->getItem()], ['alias' => 'tuple']);
        $otherItem = $pool->getItem('unrelated');
        $foreignObserver = new HandlerDescriptor(static fn () => new EarlyExpirationResult($otherItem), ['alias' => 'foreign']);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [$handler, $observer, $tupleObserver, $foreignObserver]]))]);
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer));

        $this->assertNull($pool->get('null-sync', $callback, \INF));
        $this->assertSame(234, $pool->getItem('null-sync')->get());
        $this->assertSame(234, $pool->get('null-sync', $callback, \INF));
        $this->assertSame(1, $callback->calls);
    }

    public function testAsynchronousEarlyExpirationPreservesCurrentValue()
    {
        $pool = new FilesystemAdapter();
        $pool->save($pool->getItem('null-async')->set(234));
        $callback = new class {
            public int $calls = 0;

            public function __invoke()
            {
                ++$this->calls;

                return null;
            }
        };

        $container = new Container();
        $container->set('callback', $callback);
        $container->set('pool', $pool);
        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher(new MessageBus(), $reverseContainer));
        $this->assertSame(234, $pool->get('null-async', $callback, \INF));
        $this->assertSame(0, $callback->calls);

        $transportClass = class_exists(InMemoryTransport::class) ? InMemoryTransport::class : LegacyInMemoryTransport::class;
        $transport = new $transportClass(new PhpSerializer());
        $container->set('async', $transport);
        $handler = new EarlyExpirationHandler($reverseContainer);
        $bus = new MessageBus([
            new SendMessageMiddleware(new SendersLocator([EarlyExpirationMessage::class => ['async']], $container)),
            new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [$handler]])),
        ]);
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer));

        $this->assertSame(234, $pool->get('null-async', $callback, \INF));
        $this->assertSame(0, $callback->calls);
        $this->assertCount(1, $transport->getSent());

        $bus->dispatch($transport->get()[0]->with(new ReceivedStamp('async')));

        $this->assertSame(1, $callback->calls);
        $this->assertTrue($pool->hasItem('null-async'));
        $this->assertNull($pool->get('null-async', $this->fail(...), 0));
    }
}

final class TestLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
