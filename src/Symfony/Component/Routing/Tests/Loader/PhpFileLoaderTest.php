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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class PhpFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new PhpFileLoader($this->getMockBuilder('Symfony\Component\Config\FileLocator')->getMock());

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

        $this->assertCount(1, $routes, 'One route is loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            $this->assertSame('/blog/{slug}', $route->getPath());
            $this->assertSame('MyBlogBundle:Blog:show', $route->getDefault('_controller'));
            $this->assertSame('{locale}.example.com', $route->getHost());
            $this->assertSame('RouteCompiler', $route->getOption('compiler_class'));
            $this->assertEquals(array('GET', 'POST', 'PUT', 'OPTIONS'), $route->getMethods());
            $this->assertEquals(array('https'), $route->getSchemes());
        }
    }

    public function testLoadWithImport()
    {
        $loader = new PhpFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $routeCollection = $loader->load('validresource.php');
        $routes = $routeCollection->all();

        $this->assertCount(1, $routes, 'One route is loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);

        foreach ($routes as $route) {
            $this->assertSame('/prefix/blog/{slug}', $route->getPath());
            $this->assertSame('MyBlogBundle:Blog:show', $route->getDefault('_controller'));
            $this->assertSame('{locale}.example.com', $route->getHost());
            $this->assertSame('RouteCompiler', $route->getOption('compiler_class'));
            $this->assertEquals(array('GET', 'POST', 'PUT', 'OPTIONS'), $route->getMethods());
            $this->assertEquals(array('https'), $route->getSchemes());
        }
    }

    public function testThatDefiningVariableInConfigFileHasNoSideEffects()
    {
        $locator = new FileLocator(array(__DIR__.'/../Fixtures'));
        $loader = new PhpFileLoader($locator);
        $routeCollection = $loader->load('with_define_path_variable.php');
        $resources = $routeCollection->getResources();
        $this->assertCount(1, $resources);
        $this->assertContainsOnly('Symfony\Component\Config\Resource\ResourceInterface', $resources);
        $fileResource = reset($resources);
        $this->assertSame(
            realpath($locator->locate('with_define_path_variable.php')),
            (string) $fileResource
        );
    }

    public function testRoutingConfigurator()
    {
        $locator = new FileLocator(array(__DIR__.'/../Fixtures'));
        $loader = new PhpFileLoader($locator);
        $routeCollection = $loader->load('php_dsl.php');

        $expectedCollection = new RouteCollection();

        $expectedCollection->add('foo', (new Route('/foo'))
            ->setOptions(array('utf8' => true))
            ->setCondition('abc')
        );
        $expectedCollection->add('buz', (new Route('/zub'))
            ->setDefaults(array('_controller' => 'foo:act'))
        );
        $expectedCollection->add('c_bar', (new Route('/sub/pub/bar'))
            ->setRequirements(array('id' => '\d+'))
        );
        $expectedCollection->add('c_pub_buz', (new Route('/sub/pub/buz'))
            ->setHost('host')
            ->setRequirements(array('id' => '\d+'))
        );
        $expectedCollection->add('ouf', (new Route('/ouf'))
            ->setSchemes(array('https'))
            ->setMethods(array('GET'))
            ->setDefaults(array('id' => 0))
        );

        $expectedCollection->addResource(new FileResource(realpath(__DIR__.'/../Fixtures/php_dsl_sub.php')));
        $expectedCollection->addResource(new FileResource(realpath(__DIR__.'/../Fixtures/php_dsl.php')));

        $this->assertEquals($expectedCollection, $routeCollection);
    }

    public function testRoutingConfiguratorCanImportGlobPatterns()
    {
        $locator = new FileLocator(array(__DIR__.'/../Fixtures/glob'));
        $loader = new PhpFileLoader($locator);
        $routeCollection = $loader->load('php_dsl.php');

        $route = $routeCollection->get('bar_route');
        $this->assertSame('AppBundle:Bar:view', $route->getDefault('_controller'));

        $route = $routeCollection->get('baz_route');
        $this->assertSame('AppBundle:Baz:view', $route->getDefault('_controller'));
    }
}
