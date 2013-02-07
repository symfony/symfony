<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\Config\FileLocator;

class IniFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    protected $container;
    protected $loader;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Config\Loader\Loader')) {
            $this->markTestSkipped('The "Config" component is not available');
        }

        $this->container = new ContainerBuilder();
        $this->loader    = new IniFileLoader($this->container, new FileLocator(self::$fixturesPath.'/ini'));
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::__construct
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::load
     */
    public function testIniFileCanBeLoaded()
    {
        $this->loader->load('parameters.ini');
        $this->assertEquals(array('foo' => 'bar', 'bar' => '%foo%'), $this->container->getParameterBag()->all(), '->load() takes a single file name as its first argument');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::__construct
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::load
     */
    public function testExceptionIsRaisedWhenIniFileDoesNotExist()
    {
        try {
            $this->loader->load('foo.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.ini" does not exist (in: ', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::__construct
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::load
     */
    public function testExceptionIsRaisedWhenIniFileCannotBeParsed()
    {
        try {
            @$this->loader->load('nonvalid.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file is not parseable');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file is not parseable');
            $this->assertEquals('The "nonvalid.ini" file is not valid.', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file is not parseable');
        }
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::supports
     */
    public function testSupports()
    {
        $loader = new IniFileLoader(new ContainerBuilder(), new FileLocator());

        $this->assertTrue($loader->supports('foo.ini'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns true if the resource is loadable');
    }
}
