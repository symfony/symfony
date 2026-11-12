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
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\LockRegistry;
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
class ChainAdapterEarlyExpirationTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    #[DataProvider('provideChains')]
    public function testAsynchronousRecomputation(string $callbackForm, bool $clearUpperLayers, float $beta)
    {
        [$pool, $adapters] = $this->createChain();
        $computation = new EarlyExpirationComputation();
        $callback = match ($callbackForm) {
            'object' => $computation,
            'array' => [$computation, '__invoke'],
            'closure' => $computation(...),
        };
        [$bus, $reverseContainer, $transport] = $this->createBus($pool, $computation);

        $this->assertSame(1, $pool->get('key', $callback));
        $this->assertSame(1, $computation->calls);
        $this->assertCount(0, $transport->getSent());

        if ($clearUpperLayers) {
            foreach (\array_slice($adapters, 0, -1) as $adapter) {
                $adapter->clear();
            }
        }

        $before = $this->snapshot($adapters);
        $value = $pool->get('key', $callback, $beta);

        $this->assertCount(1, $transport->getSent());
        $this->assertSame(1, $value);
        $this->assertSame(1, $computation->calls);
        $this->assertSame($before, $this->snapshot($adapters));

        $envelope = $transport->get()[0];
        $message = $envelope->getMessage();
        $this->assertInstanceOf(EarlyExpirationMessage::class, $message);
        $this->assertSame('pool', $message->getPool());
        $this->assertSame($pool, $message->findPool($reverseContainer));
        $this->assertSame('object' === $callbackForm ? '@callback' : ['@callback', '__invoke'], $message->getCallback());
        $this->assertSame($computation, \is_array($reversedCallback = $message->findCallback($reverseContainer)) ? $reversedCallback[0] : $reversedCallback);

        $bus->dispatch($envelope->with(new ReceivedStamp('async')));

        $this->assertSame(2, $computation->calls);
        $this->assertGreaterThan(0, $adapters[array_key_last($adapters)]->getItem('key')->getMetadata()[CacheItem::METADATA_CTIME] ?? 0);
        foreach ($adapters as $adapter) {
            $item = $adapter->getItem('key');
            $this->assertTrue($item->isHit());
            $this->assertSame(2, $item->get());
        }
    }

    public function testSynchronousRecomputationReturnsAndCachesNull()
    {
        [$pool, $adapters] = $this->createChain();
        $computation = new EarlyExpirationComputation([1, null]);
        [$bus, $reverseContainer] = $this->createBus($pool, $computation, false);
        $dispatcherBus = $this->createMock(MessageBusInterface::class);
        $dispatcherBus->expects($this->once())->method('dispatch')->willReturnCallback($bus->dispatch(...));
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($dispatcherBus, $reverseContainer));

        $this->assertSame(1, $pool->get('key', $computation));
        $this->assertNull($pool->get('key', $computation, \INF));
        $this->assertSame(2, $computation->calls);

        foreach ($adapters as $adapter) {
            $item = $adapter->getItem('key');
            $this->assertTrue($item->isHit());
            $this->assertNull($item->get());
            $this->assertArrayHasKey(CacheItem::METADATA_EXPIRY, $item->getMetadata());
        }
    }

    #[DataProvider('provideDispatchModes')]
    public function testRecomputationCanSkipSaving(bool $async)
    {
        [$pool, $adapters] = $this->createChain();
        $computation = new EarlyExpirationComputation();
        [$bus, , $transport] = $this->createBus($pool, $computation, $async);

        $this->assertSame(1, $pool->get('key', $computation));
        $before = $this->snapshot($adapters);
        $computation->save = false;

        $this->assertSame($async ? 1 : 2, $pool->get('key', $computation, \INF));
        if ($async) {
            $this->assertCount(1, $transport->getSent());
            $bus->dispatch($transport->get()[0]->with(new ReceivedStamp('async')));
        }

        $this->assertSame(2, $computation->calls);
        $this->assertSame($before, $this->snapshot($adapters));
    }

    public function testUnreversibleCallbackIsComputedOnline()
    {
        [$pool, $adapters] = $this->createChain();
        $computation = new EarlyExpirationComputation();
        [, , $transport] = $this->createBus($pool, $computation);

        $this->assertSame(1, $pool->get('key', $computation));
        $this->assertSame('online', $pool->get('key', static fn () => 'online', \INF));
        $this->assertCount(0, $transport->getSent());
        foreach ($adapters as $adapter) {
            $this->assertSame('online', $adapter->getItem('key')->get());
        }
    }

    public function testUnreversiblePoolIsComputedOnline()
    {
        [$pool, $adapters] = $this->createChain();
        $computation = new EarlyExpirationComputation();
        [, , $transport] = $this->createBus($pool, $computation, true, false);

        $this->assertSame(1, $pool->get('key', $computation));
        $adapters[0]->clear();

        $this->assertSame(2, $pool->get('key', $computation, 1e12));
        $this->assertSame(2, $computation->calls);
        $this->assertCount(0, $transport->getSent());
        foreach ($adapters as $adapter) {
            $this->assertSame(2, $adapter->getItem('key')->get());
        }
    }

    public function testOnlineLockingPreservesPropagationLifetimeAndSaveFlag()
    {
        $fast = new ArrayAdapter(200);
        $slow = new FilesystemAdapter('chain-early-expiration', 300);
        $slow->clear();
        $pool = new ChainAdapter([$fast, $slow], 100);
        $computation = new EarlyExpirationComputation();
        [$bus, $reverseContainer, $transport] = $this->createBus($pool, $computation);
        $wrapper = static function (\Closure $callback, CacheItem $item, bool &$save, AdapterInterface $backend, \Closure $setMetadata) use ($slow) {
            self::assertSame($slow, $backend);

            return LockRegistry::compute($callback, $item, $save, $backend, $setMetadata);
        };
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer, $wrapper));

        $this->assertSame('value', $pool->get('key', static fn () => 'value'));
        $this->assertEqualsWithDelta(microtime(true) + 100, $fast->getItem('key')->getMetadata()[CacheItem::METADATA_EXPIRY], 1);
        $this->assertSame('unsaved', $pool->get('other', static function (CacheItem $item, bool &$save) {
            $save = false;

            return 'unsaved';
        }));
        $this->assertFalse($fast->hasItem('other'));
        $this->assertFalse($slow->hasItem('other'));
        $this->assertCount(0, $transport->getSent());
    }

    public function testComputationCanBeRetriedAfterFailure()
    {
        [$pool, $adapters] = $this->createChain();
        $failure = new \RuntimeException('Failed computation.');
        $computation = new EarlyExpirationComputation([$failure, 2]);
        [, , $transport] = $this->createBus($pool, $computation);

        try {
            $pool->get('key', $computation);
            $this->fail('The computation should have failed.');
        } catch (\RuntimeException $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame(2, $pool->get('key', $computation));
        $this->assertSame(2, $computation->calls);
        $this->assertCount(0, $transport->getSent());
        foreach ($adapters as $adapter) {
            $this->assertSame(2, $adapter->getItem('key')->get());
        }
    }

    public function testRecursiveComputationKeepsSaveFlagsSeparate()
    {
        [$pool, $adapters] = $this->createChain();
        [, , $transport] = $this->createBus($pool, new EarlyExpirationComputation());

        $value = $pool->get('outer', function () use ($pool) {
            $this->assertSame('unsaved', $pool->get('inner', static function (CacheItem $item, bool &$save) {
                $save = false;

                return 'unsaved';
            }));
            $this->assertFalse($pool->hasItem('inner'));

            return $pool->get('inner', static fn () => 'saved');
        });

        $this->assertSame('saved', $value);
        $this->assertCount(0, $transport->getSent());
        foreach ($adapters as $adapter) {
            $this->assertSame('saved', $adapter->getItem('inner')->get());
            $this->assertSame('saved', $adapter->getItem('outer')->get());
        }
    }

    public function testCallbackWrapperCanBeRestored()
    {
        [$pool, $adapters] = $this->createChain();
        $calls = 0;
        $wrapper = static function (\Closure $callback, CacheItem $item, bool &$save, AdapterInterface $backend) use (&$calls, $adapters) {
            ++$calls;
            self::assertSame($adapters[1], $backend);

            return $callback($item, $save);
        };
        $previousWrapper = $pool->setCallbackWrapper($wrapper);

        $this->assertSame('first', $pool->get('first', static fn () => 'first'));
        $this->assertSame($wrapper, $pool->setCallbackWrapper($previousWrapper));
        $this->assertSame('second', $pool->get('second', static fn () => 'second'));
        $this->assertSame(1, $calls);
    }

    public static function provideChains(): iterable
    {
        foreach (['object', 'array', 'closure'] as $callbackForm) {
            // A finite beta lets only the last adapter elect the item.
            yield $callbackForm.' cold' => [$callbackForm, true, \PHP_FLOAT_MAX];
            yield $callbackForm.' forced' => [$callbackForm, false, \INF];
        }
    }

    public static function provideDispatchModes(): iterable
    {
        yield 'async' => [true];
        yield 'sync' => [false];
    }

    /**
     * @return array{ChainAdapter, list<AdapterInterface>}
     */
    private function createChain(): array
    {
        $slow = new FilesystemAdapter('chain-early-expiration');
        $slow->clear();
        $adapters = [new ArrayAdapter(), $slow];

        return [new ChainAdapter($adapters), $adapters];
    }

    /**
     * @return array{MessageBus, ReverseContainer, InMemoryTransport|null}
     */
    private function createBus(ChainAdapter $pool, EarlyExpirationComputation $computation, bool $async = true, bool $registerPool = true): array
    {
        $container = new Container();
        if ($registerPool) {
            $container->set('pool', $pool);
        }
        $container->set('callback', $computation);
        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));
        $middlewares = [];
        $transport = null;

        if ($async) {
            $container->set('async', $transport = new InMemoryTransport(new PhpSerializer()));
            $middlewares[] = new SendMessageMiddleware(new SendersLocator([EarlyExpirationMessage::class => ['async']], $container));
        }

        $middlewares[] = new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [new EarlyExpirationHandler($reverseContainer)]]));
        $bus = new MessageBus($middlewares);
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer));

        return [$bus, $reverseContainer, $transport];
    }

    /**
     * @param list<AdapterInterface> $adapters
     *
     * @return list<array{bool, mixed, array{expiry?: int|float, ctime?: int, tags?: array<string, string>}}>
     */
    private function snapshot(array $adapters): array
    {
        return array_map(static function (AdapterInterface $adapter): array {
            $item = $adapter->getItem('key');

            return [$item->isHit(), $item->get(), $item->getMetadata()];
        }, $adapters);
    }
}
