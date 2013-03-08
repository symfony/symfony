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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\PhpFileLoader;

class PhpFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\FileLocator')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    public function testSupports()
    {
        $loader = new PhpFileLoader($this->getMock('Symfony\Component\Config\FileLocator'));

        $this->assertTrue($loader->supports('foo.php'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('foo.php', 'php'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('foo.php', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadWithRoute()
    {
        $loader = new PhpFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $routeCollection = $loader->load('validpattern.php');
        $routes = $routeCollection->all();

        $this->assertCount(2, $routes, 'Two routes are loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            $this->assertEquals('/blog/{slug}', $route->getPath());
            $this->assertEquals('MyBlogBundle:Blog:show', $route->getDefault('_controller'));
            $this->assertEquals('GET', $route->getRequirement('_method'));
            $this->assertEquals('https', $route->getRequirement('_scheme'));
            $this->assertEquals('{locale}.example.com', $route->getHost());
            $this->assertEquals('RouteCompiler', $route->getOption('compiler_class'));
        }
    }
}
