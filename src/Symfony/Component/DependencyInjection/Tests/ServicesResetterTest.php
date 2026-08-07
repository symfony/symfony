<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClearableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LazyResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\MultiResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ResettableService;
use Symfony\Component\VarExporter\ProxyHelper;

class ServicesResetterTest extends TestCase
{
    protected function setUp(): void
    {
        ResettableService::$counter = 0;
        ClearableService::$counter = 0;
        MultiResettableService::$resetFirstCounter = 0;
        MultiResettableService::$resetSecondCounter = 0;
    }

    public function testResetServices()
    {
        $resetter = new ServicesResetter(new \ArrayIterator([
            'id1' => new ResettableService(),
            'id2' => new ClearableService(),
            'id3' => new MultiResettableService(),
        ]), [
            'id1' => ['reset'],
            'id2' => ['clear'],
            'id3' => ['resetFirst', 'resetSecond'],
        ]);

        $resetter->reset();

        $this->assertSame(1, ResettableService::$counter);
        $this->assertSame(1, ClearableService::$counter);
        $this->assertSame(1, MultiResettableService::$resetFirstCounter);
        $this->assertSame(1, MultiResettableService::$resetSecondCounter);
    }

    public function testResetLazyServices()
    {
        // eval() is used here intentionally to create a lazy proxy class at runtime for testing purposes
        $proxyCode = ProxyHelper::generateLazyProxy(new \ReflectionClass(LazyResettableService::class));
        eval('class DI_LazyResettableServiceProxy'.$proxyCode);

        $lazyService = \DI_LazyResettableServiceProxy::createLazyProxy(static fn (): LazyResettableService => new LazyResettableService());

        $resetter = new ServicesResetter(new \ArrayIterator([
            'lazy' => $lazyService,
        ]), [
            'lazy' => ['reset'],
        ]);

        $resetter->reset();
        $this->assertSame(0, LazyResettableService::$counter);

        $resetter->reset();
        $this->assertSame(0, LazyResettableService::$counter);

        $this->assertTrue($lazyService->foo());

        $resetter->reset();
        $this->assertSame(1, LazyResettableService::$counter);
    }

    public function testResetNonSharedServices()
    {
        $resetMap = new \WeakMap();
        $nonSharedInstance = new ResettableService();
        $resetMap[$nonSharedInstance] = ['reset'];

        $resetter = new ServicesResetter(new \ArrayIterator([]), [], $resetMap);

        $resetter->reset();

        $this->assertSame(1, ResettableService::$counter);
    }

    public function testResetMixedSharedAndNonSharedServices()
    {
        $resetMap = new \WeakMap();
        $nonSharedInstance = new ClearableService();
        $resetMap[$nonSharedInstance] = ['clear'];

        $resetter = new ServicesResetter(new \ArrayIterator([
            'id1' => new ResettableService(),
        ]), [
            'id1' => ['reset'],
        ], $resetMap);

        $resetter->reset();

        $this->assertSame(1, ResettableService::$counter);
        $this->assertSame(1, ClearableService::$counter);
    }

    public function testResetNonSharedWithOptionalMethod()
    {
        $resetMap = new \WeakMap();
        $instance = new \stdClass();
        $resetMap[$instance] = ['?nonExistentMethod'];

        $resetter = new ServicesResetter(new \ArrayIterator([]), [], $resetMap);

        // Should not throw - '?' prefix means the method is optional
        $resetter->reset();

        $this->addToAssertionCount(1);
    }

    public function testNonSharedInstancesGarbageCollected()
    {
        $resetMap = new \WeakMap();

        $instance = new ResettableService();
        $resetMap[$instance] = ['reset'];

        $this->assertCount(1, $resetMap);

        unset($instance);

        $this->assertCount(0, $resetMap);
    }

    public function testResetThrowingServiceDoesNotPreventLaterServicesReset()
    {
        $throwingService = new class {
            public function reset(): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $resetter = new ServicesResetter(new \ArrayIterator([
            'id1' => $throwingService,
            'id2' => new ResettableService(),
        ]), [
            'id1' => ['reset'],
            'id2' => ['reset'],
        ]);

        $thrown = null;

        try {
            $resetter->reset();
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertSame('boom', $thrown->getMessage());
        $this->assertSame(1, ResettableService::$counter);
    }

    public function testFirstThrowableIsRethrownAfterAllMethodsRun()
    {
        $service = new class {
            public int $secondCounter = 0;

            public function resetFirst(): void
            {
                throw new \RuntimeException('first');
            }

            public function resetSecond(): void
            {
                ++$this->secondCounter;

                throw new \RuntimeException('second');
            }
        };

        $resetter = new ServicesResetter(new \ArrayIterator([
            'id1' => $service,
        ]), [
            'id1' => ['resetFirst', 'resetSecond'],
        ]);

        $thrown = null;

        try {
            $resetter->reset();
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertSame('first', $thrown->getMessage());
        $this->assertSame(1, $service->secondCounter);
    }
}
