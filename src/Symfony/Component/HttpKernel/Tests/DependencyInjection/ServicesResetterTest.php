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

        $lazyService = \LazyResettableServiceProxy::createLazyProxy(fn (): LazyResettableService => new LazyResettableService());

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
}
