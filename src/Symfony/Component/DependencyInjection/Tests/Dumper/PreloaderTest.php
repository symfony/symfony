<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Dumper\Preloader;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\A;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\B;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\C;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\D;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\Dummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\DummyWithInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\E;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\F;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\G;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\IntersectionDummy;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\UnionDummy;

class PreloaderTest extends TestCase
{
    public function testPreload()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\Dummy', &$preloaded]);

        self::assertTrue(class_exists(Dummy::class, false));
        self::assertTrue(class_exists(A::class, false));
        self::assertTrue(class_exists(B::class, false));
        self::assertTrue(class_exists(C::class, false));
    }

    public function testPreloadSkipsNonExistingInterface()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\DummyWithInterface', &$preloaded]);
        self::assertFalse(class_exists(DummyWithInterface::class, false));
    }

    public function testPreloadUnion()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\UnionDummy', &$preloaded]);

        self::assertTrue(class_exists(UnionDummy::class, false));
        self::assertTrue(class_exists(D::class, false));
        self::assertTrue(class_exists(E::class, false));
    }

    public function testPreloadIntersection()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\IntersectionDummy', &$preloaded]);

        self::assertTrue(class_exists(IntersectionDummy::class, false));
        self::assertTrue(class_exists(F::class, false));
        self::assertTrue(class_exists(G::class, false));
    }
}
