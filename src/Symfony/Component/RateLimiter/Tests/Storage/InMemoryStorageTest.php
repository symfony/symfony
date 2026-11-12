<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\Policy\Window;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class InMemoryStorageTest extends TestCase
{
    public function testFetchKeepsTheStateUntilItExpiresOnTheClock()
    {
        $clock = new MockClock('@1000000000.25');
        $storage = new InMemoryStorage($clock);
        $storage->save(new Window('foo', 10, 5, 1000000000.25));

        $clock->sleep(9.5);
        $this->assertInstanceOf(Window::class, $storage->fetch('foo'));
    }

    public function testFetchDropsTheStateOnceItExpiredOnTheClock()
    {
        $clock = new MockClock('@1000000000.25');
        $storage = new InMemoryStorage($clock);
        $storage->save(new Window('foo', 10, 5, 1000000000.25));

        $clock->sleep(10);
        $this->assertNull($storage->fetch('foo'));
        // the expired entry is removed
        $clock->modify('-1 hour');
        $this->assertNull($storage->fetch('foo'));
    }

    public function testExpirationIsComputedFromTheClockAtSaveTime()
    {
        $clock = new MockClock('@1000000000.25');
        $storage = new InMemoryStorage($clock);

        $clock->sleep(100);
        $storage->save(new Window('foo', 10, 5, 1000000100.25));

        $clock->sleep(5);
        $this->assertInstanceOf(Window::class, $storage->fetch('foo'));

        $clock->sleep(5);
        $this->assertNull($storage->fetch('foo'));
    }
}
