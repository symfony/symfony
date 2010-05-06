<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Dumper;

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Dumper\PhpDumper;

class PhpDumperTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');
    }

    public function testDump()
    {
        $dumper = new PhpDumper($container = new Builder());

        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services1.php', $dumper->dump(), '->dump() dumps an empty container as an empty PHP class');
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services1-1.php', $dumper->dump(array('class' => 'Container', 'base_class' => 'AbstractContainer')), '->dump() takes a class and a base_class options');

        $container = new Builder();
        $dumper = new PhpDumper($container);
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $dumper = new PhpDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/php/services8.php', $dumper->dump(), '->dump() dumps parameters');
    }

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new PhpDumper($container);
        $this->assertEquals(str_replace('%path%', str_replace('\\','\\\\',self::$fixturesPath.DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR), file_get_contents(self::$fixturesPath.'/php/services9.php')), $dumper->dump(), '->dump() dumps services');

        $dumper = new PhpDumper($container = new Builder());
        $container->register('foo', 'FooClass')->addArgument(new \stdClass());
        try
        {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
        catch (\Exception $e)
        {
            $this->assertInstanceOf('\RuntimeException', $e, '->dump() returns a LogicException if the dump() method has not been overriden by a children class');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource.', $e->getMessage(), '->dump() returns a LogicException if the dump() method has not been overriden by a children class');
        }
    }

    public function testOverrideServiceWhenUsingADumpedContainer()
    {
        require_once self::$fixturesPath.'/php/services9.php';
        require_once self::$fixturesPath.'/includes/foo.php';

        $container = new \ProjectServiceContainer();
        $container->setService('bar', $bar = new \stdClass());
        $container->setParameter('foo_bar', 'foo_bar');

        $this->assertEquals($bar, $container->getBarService(), '->setService() overrides an already defined service');
        $this->assertEquals($bar, $container->getService('bar'), '->setService() overrides an already defined service');
    }
}
