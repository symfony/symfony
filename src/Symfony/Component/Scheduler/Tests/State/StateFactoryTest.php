<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\State;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\State\CacheStateDecorator;
use Symfony\Component\Scheduler\State\LockStateDecorator;
use Symfony\Component\Scheduler\State\State;
use Symfony\Component\Scheduler\State\StateFactory;
use Symfony\Contracts\Cache\CacheInterface;

class StateFactoryTest extends TestCase
{
    public function testCreateSimple()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([])
        );

        $expected = new State();

        $this->assertEquals($expected, $factory->create('name', []));
    }

    public function testCreateWithCache()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn ($key, \Closure $f) => $f());

        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer(['app' => $cache]),
        );

        $state = new State();
        $expected = new CacheStateDecorator($state, $cache, 'messenger.schedule.name');

        $this->assertEquals($expected, $factory->create('name', ['cache' => 'app']));
    }

    public function testCreateWithCacheAndLock()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn ($key, \Closure $f) => $f());

        $lockFactory = new LockFactory(new InMemoryStore());

        $factory = new StateFactory(
            $this->makeContainer(['unlock' => $lockFactory]),
            $this->makeContainer(['app' => $cache]),
        );

        $lock = $lockFactory->createLock($name = 'messenger.schedule.name');
        $state = new State();
        $state = new LockStateDecorator($state, $lock);
        $expected = new CacheStateDecorator($state, $cache, $name);

        $this->assertEquals($expected, $factory->create('name', ['cache' => 'app', 'lock' => 'unlock']));
    }

    public function testCreateWithConfiguredLock()
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(fn ($key, \Closure $f) => $f());

        $lockFactory = new LockFactory(new InMemoryStore());

        $factory = new StateFactory(
            $this->makeContainer(['unlock' => $lockFactory]),
            $this->makeContainer([]),
        );

        $lock = $lockFactory->createLock('messenger.schedule.name', $ttl = 77.7, false);
        $state = new State();
        $expected = new LockStateDecorator($state, $lock);

        $cfg = [
            'resource' => 'unlock',
            'ttl' => $ttl,
            'auto_release' => false,
        ];
        $this->assertEquals($expected, $factory->create('name', ['lock' => $cfg]));
    }

    public function testInvalidCacheName()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([])
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The cache pool "wrong-cache" does not exist.');

        $factory->create('name', ['cache' => 'wrong-cache']);
    }

    public function testInvalidLockName()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([])
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The lock resource "wrong-lock" does not exist.');

        $factory->create('name', ['lock' => 'wrong-lock']);
    }

    public function testInvalidConfiguredLockName()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([])
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The lock resource "wrong-lock" does not exist.');

        $factory->create('name', ['lock' => ['resource' => 'wrong-lock']]);
    }

    public function testInvalidCacheOption()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([]),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid cache configuration for "default" schedule.');
        $factory->create('default', ['cache' => true]);
    }

    public function testInvalidLockOption()
    {
        $factory = new StateFactory(
            $this->makeContainer([]),
            $this->makeContainer([]),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid lock configuration for "default" schedule.');
        $factory->create('default', ['lock' => true]);
    }

    private function makeContainer(array $services): ContainerInterface|\ArrayObject
    {
        return new class($services) extends \ArrayObject implements ContainerInterface {
            public function get(string $id): mixed
            {
                return $this->offsetGet($id);
            }

            public function has(string $id): bool
            {
                return $this->offsetExists($id);
            }
        };
    }
}
