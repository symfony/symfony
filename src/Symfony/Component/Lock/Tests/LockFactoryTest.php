<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockFactoryTest extends TestCase
{
    public function testCreateLock()
    {
        $store = self::createMock(PersistingStoreInterface::class);
        $store->expects(self::any())->method('exists')->willReturn(false);

        $keys = [];
        $store
            ->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }))
            ->willReturn(true);

        $logger = self::createMock(LoggerInterface::class);
        $factory = new LockFactory($store);
        $factory->setLogger($logger);

        $lock1 = $factory->createLock('foo');
        $lock2 = $factory->createLock('foo');

        // assert lock1 and lock2 don't share the same state
        $lock1->acquire();
        $lock2->acquire();

        self::assertNotSame($keys[0], $keys[1]);
    }

    public function testCreateLockFromKey()
    {
        $store = self::createMock(PersistingStoreInterface::class);
        $store->expects(self::any())->method('exists')->willReturn(false);

        $keys = [];
        $store
            ->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }))
            ->willReturn(true);

        $logger = self::createMock(LoggerInterface::class);
        $factory = new LockFactory($store);
        $factory->setLogger($logger);

        $key = new Key('foo');
        $lock1 = $factory->createLockFromKey($key);
        $lock2 = $factory->createLockFromKey($key);

        // assert lock1 and lock2 share the same state
        $lock1->acquire();
        $lock2->acquire();

        self::assertSame($keys[0], $keys[1]);
    }
}
