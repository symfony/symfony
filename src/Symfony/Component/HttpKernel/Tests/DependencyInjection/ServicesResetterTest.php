<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\LazyResettableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\MultiResettableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;
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
        $proxyCode = ProxyHelper::generateLazyProxy(new \ReflectionClass(LazyResettableService::class));
        eval('class LazyResettableServiceProxy'.$proxyCode);

        $lazyService = \LazyResettableServiceProxy::createLazyProxy(static fn (): LazyResettableService => new LazyResettableService());

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
