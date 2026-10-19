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

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ClearableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\LazyResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\MultiResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ResettableService;
use Symfony\Component\DependencyInjection\Tests\Fixtures\ShutdownAwareService;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Contracts\Service\ShutdownInterface;

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
            'id1' => 'reset',
            'id2' => ['clear'],
            'id3' => ['resetFirst', 'resetSecond'],
        ]);

        $resetter->reset();

        $this->assertSame(1, ResettableService::$counter);
        $this->assertSame(1, ClearableService::$counter);
        $this->assertSame(1, MultiResettableService::$resetFirstCounter);
        $this->assertSame(1, MultiResettableService::$resetSecondCounter);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testResetPassesShutdownFlagOnlyToOptedInMethods(bool $nonShared)
    {
        $service = new class {
            public array $calls = [];

            public function reset(bool $shutdown = false): void
            {
                $this->calls[] = [$shutdown, \func_num_args()];
            }

            public function clear(): void
            {
                $this->calls[] = ['clear', \func_num_args()];
            }
        };
        $methods = [['reset', false, 'flag'], '?missing', ['missingWithFlag', true, 'flag'], ['clear', false, null], ['reset', false, null], 'clear'];
        $resetMap = null;
        $services = ['service' => $service];
        $resetMethods = ['service' => $methods];
        if ($nonShared) {
            $resetMap = new \WeakMap();
            $resetMap[$service] = $methods;
            $services = $resetMethods = [];
        }
        $resetter = new ServicesResetter(new \ArrayIterator($services), $resetMethods, $resetMap);

        $resetter->reset();
        $resetter->reset(true);
        $resetter->reset();

        $this->assertSame([[false, 0], ['clear', 0], [false, 0], ['clear', 0], [true, 1], ['clear', 0], [false, 0], ['clear', 0]], $service->calls);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testShutdownOnlyMethods(bool $nonShared)
    {
        $service = new class {
            public array $calls = [];

            public function close(): void
            {
                $this->calls[] = \func_num_args();
            }
        };
        $methods = [['close', false, 'only'], ['missing', true, 'only']];
        $resetMap = null;
        $services = ['service' => $service];
        $resetMethods = ['service' => $methods];
        if ($nonShared) {
            $resetMap = new \WeakMap();
            $resetMap[$service] = $methods;
            $services = $resetMethods = [];
        }
        $resetter = new ServicesResetter(new \ArrayIterator($services), $resetMethods, $resetMap);

        $resetter->reset();
        $resetter->reset();
        $this->assertSame([], $service->calls);

        $resetter->reset(true);
        $resetter->reset(true);
        $this->assertSame([0], $service->calls);
    }

    public function testShutdownThroughContract()
    {
        $service = new ShutdownAwareService();
        $resetter = new ServicesResetter(new \ArrayIterator(['service' => $service]), ['service' => [['reset', false, 'flag']]]);

        (static function (ShutdownInterface $resetter) {
            $resetter->reset(true);
        })($resetter);

        $this->assertSame([true], $service->calls);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testResetSameInstanceWithDifferentMethods(bool $shutdown)
    {
        $service = new class {
            public array $calls = [];

            public function reset(): void
            {
                $this->calls[] = 'reset';
            }

            public function clear(): void
            {
                $this->calls[] = 'clear';
            }
        };
        $resetter = new ServicesResetter(new \ArrayIterator(['first' => $service, 'second' => $service]), ['first' => [['reset', false, null]], 'second' => [['clear', false, null]]]);

        $resetter->reset($shutdown);

        $this->assertSame(['reset', 'clear'], $service->calls);
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

    public function testResetNativeLazyObjects()
    {
        $service = new class {
            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
            }
        };
        $class = new \ReflectionClass($service);
        $lazyService = $class->newLazyGhost(static function () {});

        $resetter = new ServicesResetter(new \ArrayIterator([
            'service' => $service,
            'lazy' => $lazyService,
        ]), [
            'service' => ['reset'],
            'lazy' => ['reset'],
        ]);

        $resetter->reset();
        $this->assertSame(1, $service->resets);
        $this->assertTrue($class->isUninitializedLazyObject($lazyService));

        $class->initializeLazyObject($lazyService);

        $resetter->reset();
        $this->assertSame(2, $service->resets);
        $this->assertSame(1, $lazyService->resets);
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
