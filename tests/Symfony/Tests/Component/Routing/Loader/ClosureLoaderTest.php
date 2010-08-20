<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Routing\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ClosureLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers Symfony\Component\Routing\Loader\ClosureLoader::supports
     */
    public function testSupports()
    {
        $loader = new ClosureLoader();

        $this->assertTrue($loader->supports(function ($container) {}), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }

    /**
     * @covers Symfony\Component\Routing\Loader\ClosureLoader::load
     */
    public function testLoad()
    {
        $loader = new ClosureLoader();

        $route = new Route('/');
        $routes = $loader->load(function () use ($route)
        {
            $routes = new RouteCollection();

            $routes->addRoute('foo', $route);

            return $routes;
        });

        $this->assertEquals($route, $routes->getRoute('foo'), '->load() loads a \Closure resource');
    }
}
