<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Loader\ClosureLoader;

class ClosureLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new ClosureLoader(new ContainerBuilder());

        $this->assertTrue($loader->supports(function ($container) {}), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    public function testLoad()
    {
        $loader = new ClosureLoader($container = new ContainerBuilder());

        $loader->load(function ($container) {
            $container->setParameter('foo', 'foo');
        });

        $this->assertEquals('foo', $container->getParameter('foo'), '->load() loads a \Closure resource');
    }
}
