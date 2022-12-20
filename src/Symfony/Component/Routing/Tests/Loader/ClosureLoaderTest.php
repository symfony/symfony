<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ClosureLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new ClosureLoader();

        $closure = function () {};

        self::assertTrue($loader->supports($closure), '->supports() returns true if the resource is loadable');
        self::assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        self::assertTrue($loader->supports($closure, 'closure'), '->supports() checks the resource type if specified');
        self::assertFalse($loader->supports($closure, 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoad()
    {
        $loader = new ClosureLoader('some-env');

        $route = new Route('/');
        $routes = $loader->load(function (string $env = null) use ($route) {
            self::assertSame('some-env', $env);

            $routes = new RouteCollection();

            $routes->add('foo', $route);

            return $routes;
        });

        self::assertEquals($route, $routes->get('foo'), '->load() loads a \Closure resource');
    }
}
