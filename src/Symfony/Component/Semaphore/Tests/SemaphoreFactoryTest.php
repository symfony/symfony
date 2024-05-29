<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Semaphore\Key;
use Symfony\Component\Semaphore\PersistingStoreInterface;
use Symfony\Component\Semaphore\SemaphoreFactory;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SemaphoreFactoryTest extends TestCase
{
    public function testCreateSemaphore()
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $keys = [];
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $factory = new SemaphoreFactory($store);
        $factory->setLogger($logger);

        $semaphore1 = $factory->createSemaphore('foo', 4);
        $semaphore2 = $factory->createSemaphore('foo', 4);

        // assert lock1 and lock2 don't share the same state
        $semaphore1->acquire();
        $semaphore2->acquire();

        $this->assertNotSame($keys[0], $keys[1]);
    }

    public function testCreateSemaphoreFromKey()
    {
        $store = $this->createMock(PersistingStoreInterface::class);

        $keys = [];
        $store
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function ($key) use (&$keys) {
                $keys[] = $key;

                return true;
            }));

        $logger = $this->createMock(LoggerInterface::class);
        $factory = new SemaphoreFactory($store);
        $factory->setLogger($logger);

        $key = new Key('foo', 4);
        $semaphore1 = $factory->createSemaphoreFromKey($key);
        $semaphore2 = $factory->createSemaphoreFromKey($key);

        // assert lock1 and lock2 share the same state
        $semaphore1->acquire();
        $semaphore2->acquire();

        $this->assertSame($keys[0], $keys[1]);
    }
}
