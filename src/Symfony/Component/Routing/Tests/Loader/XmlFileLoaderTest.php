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
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Tests\Fixtures\CustomXmlFileLoader;

class XmlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\FileLocator')) {
            $this->markTestSkipped('The "Config" component is not available');
        }
    }

    /**
     * @covers Symfony\Component\Routing\Loader\XmlFileLoader::supports
     */
    public function testSupports()
    {
        $loader = new XmlFileLoader($this->getMock('Symfony\Component\Config\FileLocator'));

        $this->assertTrue($loader->supports('foo.xml'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');

        $this->assertTrue($loader->supports('foo.xml', 'xml'), '->supports() checks the resource type if specified');
        $this->assertFalse($loader->supports('foo.xml', 'foo'), '->supports() checks the resource type if specified');
    }

    public function testLoadWithRoute()
    {
        $loader = new XmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $routeCollection = $loader->load('validpattern.xml');
        $routes = $routeCollection->all();

        $this->assertEquals(1, count($routes), 'One route is loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);
        $route = $routes['blog_show'];
        $this->assertEquals('RouteCompiler', $route->getOption('compiler_class'));
    }

    public function testLoadWithImport()
    {
        $loader = new XmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $routeCollection = $loader->load('validresource.xml');
        $routes = $routeCollection->all();

        $this->assertEquals(1, count($routes), 'One route is loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);
        $this->assertEquals('foo', $routes['blog_show']->getDefault('foo'));
        $this->assertEquals('\d+', $routes['blog_show']->getRequirement('foo'));
        $this->assertEquals('bar', $routes['blog_show']->getOption('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFile($filePath)
    {
        $loader = new XmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $loader->load($filePath);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @dataProvider getPathsToInvalidFiles
     */
    public function testLoadThrowsExceptionWithInvalidFileEvenWithoutSchemaValidation($filePath)
    {
        $loader = new CustomXmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $loader->load($filePath);
    }

    public function getPathsToInvalidFiles()
    {
        return array(array('nonvalidnode.xml'), array('nonvalidroute.xml'), array('nonvalid.xml'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Document types are not allowed.
     */
    public function testDocTypeIsNotAllowed()
    {
        $loader = new XmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $loader->load('withdoctype.xml');
    }
}
