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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Messenger\EarlyExpirationDispatcher;
use Symfony\Component\Cache\Messenger\EarlyExpirationHandler;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\InMemoryTransport as LegacyInMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Cache\CallbackInterface;

#[Group('time-sensitive')]
class EarlyExpirationHandlerTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    public function testHandle()
    {
        $pool = new FilesystemAdapter();
        $item = $pool->getItem('foo');
        $item->set(234);

        $computationService = new class implements CallbackInterface {
            public function __invoke(CacheItemInterface $item, bool &$save): mixed
            {
                usleep(30000);
                $item->expiresAfter(3600);

                return 123;
            }
        };

        $container = new Container();
        $container->set('computation_service', $computationService);
        $container->set('cache_pool', $pool);

        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));

        $msg = EarlyExpirationMessage::create($reverseContainer, $computationService, $item, $pool);

        $handler = new EarlyExpirationHandler($reverseContainer);

        $handler($msg);

        $this->assertSame(123, $pool->get('foo', $this->fail(...), 0.0, $metadata));

        $this->assertGreaterThan(25, $metadata['ctime']);
        $this->assertGreaterThan(time(), $metadata['expiry']);
    }

    public function testHandleReturnsValueWithoutSavingWhenCallbackDisablesSaving()
    {
        $pool = new FilesystemAdapter();
        $item = $pool->getItem('bar');
        $pool->save($item->set(234));

        $computationService = new class implements CallbackInterface {
            public bool $called = false;

            public function __invoke(CacheItemInterface $item, bool &$save): mixed
            {
                $this->called = true;
                $save = false;

                return 123;
            }
        };

        $container = new Container();
        $container->set('computation_service', $computationService);
        $container->set('cache_pool', $pool);

        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));

        $handler = new EarlyExpirationHandler($reverseContainer);
        $bus = new MessageBus([new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [$handler]]))]);
        $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer));

        $value = $pool->get('bar', $computationService, \INF);

        $this->assertTrue($computationService->called);
        $this->assertSame(234, $pool->getItem('bar')->get());
        $this->assertSame(123, $value);
    }

    public function testWorkerRetriesFailedCallbacks()
    {
        foreach ([new \RuntimeException('Failed computation.'), new \Error('Failed computation.')] as $failure) {
            $calls = 0;
            $currentRetry = 0;
            $callbackRetries = [];
            $whileComputing = null;
            $callback = static function (CacheItemInterface $item) use (&$calls, &$currentRetry, &$callbackRetries, &$whileComputing, $failure) {
                if (3 < ++$calls) {
                    throw new \LogicException('Too many callback calls.');
                }
                $callbackRetries[] = $currentRetry;
                if (1 === $calls) {
                    $whileComputing();

                    throw $failure;
                }

                $item->expiresAfter(3600);

                return 'recovered';
            };
            [$pool, $container, $reverseContainer, $handler] = $this->createHandler($callback);
            $transportClass = class_exists(InMemoryTransport::class) ? InMemoryTransport::class : LegacyInMemoryTransport::class;
            $transport = new $transportClass(new PhpSerializer());
            $container->set('async', $transport);
            $bus = new MessageBus([
                new SendMessageMiddleware(new SendersLocator([EarlyExpirationMessage::class => ['async']], $container)),
                new HandleMessageMiddleware(new HandlersLocator([EarlyExpirationMessage::class => [$handler]])),
            ]);
            $pool->setCallbackWrapper(new EarlyExpirationDispatcher($bus, $reverseContainer));

            $this->assertSame(234, $pool->get('foo', $callback, \INF));
            $originalEnvelope = $transport->getSent()[0];
            $whileComputing = static fn () => $bus->dispatch($originalEnvelope->with(new ReceivedStamp('async')));

            $dispatcher = new EventDispatcher();
            $senders = new ServiceLocator(['async' => static fn () => $transport]);
            $strategies = new ServiceLocator(['async' => static fn () => new MultiplierRetryStrategy(1, 0)]);
            $dispatcher->addSubscriber(new SendFailedMessageForRetryListener($senders, $strategies));
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(2));
            $dispatcher->addSubscriber(new StopWorkerOnTimeLimitListener(1));
            $dispatcher->addListener(WorkerMessageReceivedEvent::class, static function (WorkerMessageReceivedEvent $event) use (&$currentRetry) {
                $currentRetry = RedeliveryStamp::getRetryCountFromEnvelope($event->getEnvelope());
            });
            $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event) use ($pool) {
                if (!$event->isWorkerIdle()) {
                    $pool->reset();
                }
            });

            $worker = new Worker(['async' => $transport], $bus, $dispatcher);
            $worker->run(['sleep' => 0]);

            $this->assertSame([0, 1], $callbackRetries);
            $this->assertSame(2, $calls);
            $this->assertSame('recovered', $pool->getItem('foo')->get());
            $bus->dispatch($originalEnvelope->with(new ReceivedStamp('async')));
            $this->assertSame(2, $calls);
        }
    }

    public function testFailureDoesNotClearReplacementComputation()
    {
        foreach ([false, true] as $evictOriginal) {
            $calls = 0;
            $handler = $replacement = null;
            $otherMessages = [];
            $failure = new \RuntimeException('Failed computation.');
            $callback = static function () use (&$calls, &$handler, &$replacement, &$otherMessages, $failure) {
                if (3 < ++$calls) {
                    throw new \LogicException('Too many callback calls.');
                }
                if (1 === $calls) {
                    foreach ($otherMessages as $message) {
                        $handler($message);
                    }
                    $handler($replacement);

                    throw $failure;
                }

                return 'recovered';
            };
            [$pool, $container, $reverseContainer, $handler, $message] = $this->createHandler($callback);

            if ($evictOriginal) {
                $otherCalls = 0;
                $otherCallback = static function () use (&$otherCalls) {
                    if (102 < ++$otherCalls) {
                        throw new \LogicException('Too many callback calls.');
                    }

                    return 0;
                };
                $container->set('other_callback', $otherCallback);

                for ($i = 0; $i < 100; ++$i) {
                    $pool->get('other-'.$i, static function (CacheItemInterface $item) {
                        $item->expiresAfter(3600);
                        usleep(1000);

                        return 0;
                    });
                    $otherMessages[] = EarlyExpirationMessage::create($reverseContainer, $otherCallback, $pool->getItem('other-'.$i), $pool);
                }

                $replacement = EarlyExpirationMessage::create($reverseContainer, $callback, $message->getItem(), $pool);
            } else {
                $pool->delete('foo');
                $pool->get('foo', static function (CacheItemInterface $item) {
                    $item->expiresAfter(7200);
                    usleep(2000);

                    return 345;
                });
                $replacement = EarlyExpirationMessage::create($reverseContainer, $callback, $pool->getItem('foo'), $pool);
            }

            try {
                $handler($message);
                $this->fail('An exception should have been thrown.');
            } catch (\RuntimeException $e) {
                $this->assertSame($failure, $e);
            }

            $this->assertSame('recovered', $pool->getItem('foo')->get());
            $handler($replacement);
            $this->assertSame(2, $calls);

            if ($evictOriginal) {
                $this->assertSame(100, $otherCalls);
                $handler($otherMessages[99]);
                $this->assertSame(100, $otherCalls);
                $handler($otherMessages[0]);
                $this->assertSame(101, $otherCalls);
            }
        }
    }

    public function testExpiredMessageIsSkippedAfterFailure()
    {
        $calls = 0;
        $callback = static function () use (&$calls) {
            if (1 === ++$calls) {
                throw new \RuntimeException('Failed computation.');
            }

            return 'recovered';
        };
        [$pool, , , $handler, $message] = $this->createHandler($callback, 1);

        try {
            $handler($message);
            $this->fail('An exception should have been thrown.');
        } catch (\RuntimeException) {
        }

        sleep(2);
        $handler($message);

        $this->assertSame(1, $calls);
        $this->assertFalse($pool->hasItem('foo'));
    }

    public function testFailureWithoutMetadataCanBeRetried()
    {
        $calls = 0;
        $callback = static function () use (&$calls) {
            if (1 === ++$calls) {
                throw new \RuntimeException('Failed computation.');
            }

            return 'recovered';
        };
        [$pool, , $reverseContainer, $handler] = $this->createHandler($callback);
        $message = EarlyExpirationMessage::create($reverseContainer, $callback, $pool->getItem('uncached'), $pool);

        try {
            $handler($message);
            $this->fail('An exception should have been thrown.');
        } catch (\RuntimeException) {
        }

        $handler($message);

        $this->assertSame(2, $calls);
        $this->assertSame('recovered', $pool->getItem('uncached')->get());
    }

    private function createHandler(\Closure $callback, int $lifetime = 3600): array
    {
        $pool = new FilesystemAdapter();
        $pool->clear();
        $pool->get('foo', static function (CacheItemInterface $item) use ($lifetime) {
            $item->expiresAfter($lifetime);
            usleep(1000);

            return 234;
        });
        $container = new Container();
        $container->set('callback', $callback);
        $container->set('cache_pool', $pool);
        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));

        return [
            $pool,
            $container,
            $reverseContainer,
            new EarlyExpirationHandler($reverseContainer),
            EarlyExpirationMessage::create($reverseContainer, $callback, $pool->getItem('foo'), $pool),
        ];
    }
}
