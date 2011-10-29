<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing\Loader;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\Route;

class XmlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
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
    }

    public function testLoadWithImport()
    {
        $loader = new XmlFileLoader(new FileLocator(array(__DIR__.'/../Fixtures')));
        $routeCollection = $loader->load('validresource.xml');
        $routes = $routeCollection->all();

        $this->assertEquals(1, count($routes), 'One route is loaded');
        $this->assertContainsOnly('Symfony\Component\Routing\Route', $routes);
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
}

/**
 * XmlFileLoader with schema validation turned off
 */
class CustomXmlFileLoader extends XmlFileLoader
{
    protected function validate(\DOMDocument $dom)
    {
        return true;
    }
}

