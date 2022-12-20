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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Messenger\EarlyExpirationHandler;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @requires function Symfony\Component\DependencyInjection\ReverseContainer::__construct
 */
class EarlyExpirationHandlerTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir().'/symfony-cache');
    }

    /**
     * @group time-sensitive
     */
    public function testHandle()
    {
        $pool = new FilesystemAdapter();
        $item = $pool->getItem('foo');
        $item->set(234);

        $computationService = new class() {
            public function __invoke(CacheItem $item)
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

        self::assertSame(123, $pool->get('foo', [$this, 'fail'], 0.0, $metadata));

        self::assertGreaterThan(25, $metadata['ctime']);
        self::assertGreaterThan(time(), $metadata['expiry']);
    }
}
