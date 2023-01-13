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

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Messenger\EarlyExpirationDispatcher;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

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

        $computationService = new class() {
            public function __invoke(CacheItem $item)
            {
                return 123;
            }
        };

        $container = new Container();
        $container->set('computation_service', $computationService);
        $container->set('cache_pool', $pool);

        $reverseContainer = new ReverseContainer($container, new ServiceLocator([]));

        $bus = $this->createMock(MessageBusInterface::class);

        $dispatcher = new EarlyExpirationDispatcher($bus, $reverseContainer);

        $saveResult = null;
        $pool->setCallbackWrapper(function (callable $callback, CacheItem $item, bool &$save, AdapterInterface $pool, \Closure $setMetadata, ?LoggerInterface $logger) use ($dispatcher, &$saveResult) {
            try {
                return $dispatcher($callback, $item, $save, $pool, $setMetadata, $logger);
            } finally {
                $saveResult = $save;
            }
        });

        $this->assertSame(345, $pool->get('foo', fn () => 345));
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

        $computationService = new class() {
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
        $setMetadata = function () {
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
}

final class TestLogger extends AbstractLogger
{
    public $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }
}
