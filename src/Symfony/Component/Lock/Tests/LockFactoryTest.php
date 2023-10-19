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
        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects($this->any())->method('exists')->willReturn(false);

        $keys = [];
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $factory = new LockFactory($store);
        $factory->setLogger($logger);

        $lock1 = $factory->createLock('foo');
        $lock2 = $factory->createLock('foo');

        // assert lock1 and lock2 don't share the same state
        $lock1->acquire();
        $lock2->acquire();

        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function testCreateLockFromKey()
    {
        $store = $this->createMock(PersistingStoreInterface::class);
        $store->expects($this->any())->method('exists')->willReturn(false);

        $keys = [];
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $factory = new LockFactory($store);
        $factory->setLogger($logger);

        $key = new Key('foo');
        $lock1 = $factory->createLockFromKey($key);
        $lock2 = $factory->createLockFromKey($key);

        // assert lock1 and lock2 share the same state
        $lock1->acquire();
        $lock2->acquire();

        $this->assertSame($keys[0], $keys[1]);
    }
}
