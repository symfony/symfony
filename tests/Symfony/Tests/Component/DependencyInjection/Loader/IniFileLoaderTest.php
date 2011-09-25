<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\Config\FileLocator;

class IniFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    /**
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::__construct
     * @covers Symfony\Component\DependencyInjection\Loader\IniFileLoader::load
     */
    public function testLoader()
    {
        $container = new ContainerBuilder();
        $loader = new IniFileLoader($container, new FileLocator(self::$fixturesPath.'/ini'));
        $loader->load('parameters.ini');
        $this->assertEquals(array('foo' => 'bar', 'bar' => '%foo%'), $container->getParameterBag()->all(), '->load() takes a single file name as its first argument');

        try {
            $loader->load('foo.ini');
            $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->load() throws an InvalidArgumentException if the loaded file does not exist');
            $this->assertStringStartsWith('The file "foo.ini" does not exist (in: ', $e->getMessage(), '->load() throws an InvalidArgumentException if the loaded file does not exist');
        }

        try {
            @$loader->load('nonvalid.ini');
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
