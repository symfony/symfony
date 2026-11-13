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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Messenger\EarlyExpirationDispatcher;
use Symfony\Component\Cache\Messenger\EarlyExpirationHandler;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\Cache\Tests\Fixtures\EarlyExpirationComputation;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[Group('time-sensitive')]
class TraceableAdapterEarlyExpirationTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    #[DataProvider('providePools')]
    public function testRecomputation(bool $chain, bool $async, bool $save, string $callbackForm, bool $disabled = false)
    {
        $filesystem = new FilesystemAdapter('traceable-early-expiration');
        $filesystem->clear();
        $adapters = $chain ? [new ArrayAdapter(), $filesystem] : [$filesystem];
        $inner = $chain ? new ChainAdapter($adapters) : $filesystem;
        $pool = new TraceableAdapter($inner, static fn () => $disabled);
        $computation = new EarlyExpirationComputation();
        $callback = match ($callbackForm) {
            'object' => $computation,
            'array' => [$computation, '__invoke'],
            'closure' => $computation(...),
        };
        $container = new Container();
        $container->set('pool', $pool);
        $container->set('callback', $computation);
        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));
        $middlewares = [];
        $container->set('async', $transport = new InMemoryTransport(new PhpSerializer()));
        if ($async) {
            $middlewares[] = new SendMessageMiddleware(new SendersLocator([EarlyExpirationMessage::class => ['async']], $container));
        }
        $middlewares[] = new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [new EarlyExpirationHandler($reverseContainer)]]));
        $bus = new MessageBus($middlewares);
        $dispatcherBus = $this->createMock(MessageBusInterface::class);
        $dispatcherBus->expects($this->once())->method('dispatch')->willReturnCallback($bus->dispatch(...));
        $inner->setCallbackWrapper(new EarlyExpirationDispatcher($dispatcherBus, $reverseContainer));

        $this->assertSame(1, $pool->get('key', $callback));
        $this->assertSame(1, $computation->calls);
        $computation->save = $save;

        $this->assertSame($async ? 1 : 2, $pool->get('key', $callback, \INF));
        $this->assertSame($async ? 1 : 2, $computation->calls);
        if ($async) {
            $this->assertCount(1, $transport->getSent());
            $envelope = $transport->get()[0];
            $message = $envelope->getMessage();
            $this->assertSame('pool', $message->getPool());
            $this->assertSame($pool, $message->findPool($reverseContainer));
            $this->assertSame('object' === $callbackForm ? '@callback' : ['@callback', '__invoke'], $message->getCallback());
            foreach ($adapters as $adapter) {
                $this->assertSame(1, $adapter->getItem('key')->get());
                $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $adapter->getItem('key')->getMetadata());
            }
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
        }

        $this->assertSame(2, $computation->calls);
        foreach ($adapters as $adapter) {
            $item = $adapter->getItem('key');
            $this->assertSame($save ? 2 : 1, $item->get());
            $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $item->getMetadata());
        }
        $this->assertSame($save ? 2 : 1, $pool->get('key', $callback, 0));
        if ($disabled) {
            $this->assertSame([], $pool->getCalls());
        } else {
            $calls = array_values(array_filter($pool->getCalls(), static fn ($call) => 'get' === $call->name));
            $this->assertCount(3, $calls);
            $this->assertSame([0, 1, 1], array_column($calls, 'hits'));
            $this->assertSame([1, 0, 0], array_column($calls, 'misses'));
        }
    }

    public static function providePools(): iterable
    {
        foreach ([false, true] as $chain) {
            foreach ([false, true] as $async) {
                foreach ([false, true] as $save) {
                    foreach (['object', 'array', 'closure'] as $callbackForm) {
                        yield ($chain ? 'chain' : 'single').' '.($async ? 'async' : 'sync').' '.($save ? 'save' : 'no save').' '.$callbackForm => [$chain, $async, $save, $callbackForm];
                    }
                }
            }
        }

        foreach ([false, true] as $chain) {
            foreach ([false, true] as $async) {
                yield ($chain ? 'chain' : 'single').' '.($async ? 'async' : 'sync').' profiler disabled' => [$chain, $async, true, 'object', true];
            }
        }
    }
}
