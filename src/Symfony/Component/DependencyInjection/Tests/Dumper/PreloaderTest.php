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

class PreloaderTest extends TestCase
{
    /**
     * @requires PHP 7.4
     */
    public function testPreload()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');
        $r->setAccessible(true);

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\Dummy', &$preloaded]);

        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\Dummy::class, false));
        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\A::class, false));
        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\B::class, false));
        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\C::class, false));
    }

    /**
     * @requires PHP 7.4
     */
    public function testPreloadSkipsNonExistingInterface()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');
        $r->setAccessible(true);

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\DummyWithInterface', &$preloaded]);
        self::assertFalse(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\DummyWithInterface::class, false));
    }

    /**
     * @requires PHP 8
     */
    public function testPreloadUnion()
    {
        $r = new \ReflectionMethod(Preloader::class, 'doPreload');
        $r->setAccessible(true);

        $preloaded = [];

        $r->invokeArgs(null, ['Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\UnionDummy', &$preloaded]);

        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\UnionDummy::class, false));
        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\D::class, false));
        self::assertTrue(class_exists(\Symfony\Component\DependencyInjection\Tests\Fixtures\Preload\E::class, false));
    }
}
