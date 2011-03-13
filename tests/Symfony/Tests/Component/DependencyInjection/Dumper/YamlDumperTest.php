<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\InterfaceInjector;

class YamlDumperTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new YamlDumper($container = new ContainerBuilder());

        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services1.yml', $dumper->dump(), '->dump() dumps an empty container as an empty YAML file');

        $container = new ContainerBuilder();
        $dumper = new YamlDumper($container);
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services8.yml', $dumper->dump(), '->dump() dumps parameters');
    }

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new YamlDumper($container);
        $this->assertEquals(str_replace('%path%', self::$fixturesPath.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR, file_get_contents(self::$fixturesPath.'/yaml/services9.yml')), $dumper->dump(), '->dump() dumps services');

        $dumper = new YamlDumper($container = new ContainerBuilder());
        $container->register('foo', 'FooClass')->addArgument(new \stdClass());
        try {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource.', $e->getMessage(), '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
    }

    public function testInterfaceInjectors()
    {
        $interfaceInjector = new InterfaceInjector('FooClass');
        $interfaceInjector->addMethodCall('setBar', array('someValue'));
        $container = include self::$fixturesPath.'/containers/interfaces1.php';
        $container->addInterfaceInjector($interfaceInjector);

        $dumper = new YamlDumper($container);

        $classBody = $dumper->dump();
        //TODO: find a better way to test dumper
        //var_dump($classBody);

        $this->assertEquals("parameters:
  cla: Fo
  ss: Class

interfaces:
    FooClass:
        calls:
          - [setBar, [someValue]]
          

services:
  foo:
    class: %cla%o%ss%
", $classBody);
    }
}
