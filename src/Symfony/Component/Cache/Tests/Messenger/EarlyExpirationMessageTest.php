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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Messenger\EarlyExpirationMessage;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ReverseContainer;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @requires function Symfony\Component\DependencyInjection\ReverseContainer::__construct
 */
class EarlyExpirationMessageTest extends TestCase
{
    public function testCreate()
    {
        $pool = new ArrayAdapter();
        $item = $pool->getItem('foo');
        $item->set(234);

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

        $msg = EarlyExpirationMessage::create($reverseContainer, [$computationService, '__invoke'], $item, $pool);

        self::assertSame('cache_pool', $msg->getPool());
        self::assertSame($pool, $msg->findPool($reverseContainer));

        self::assertSame('foo', $msg->getItem()->getKey());
        self::assertNull($msg->getItem()->get());
        self::assertSame(234, $item->get());

        self::assertSame(['@computation_service', '__invoke'], $msg->getCallback());
        self::assertSame([$computationService, '__invoke'], $msg->findCallback($reverseContainer));

        $msg = EarlyExpirationMessage::create($reverseContainer, $computationService, $item, $pool);

        self::assertSame('@computation_service', $msg->getCallback());
        self::assertSame($computationService, $msg->findCallback($reverseContainer));
    }
}
