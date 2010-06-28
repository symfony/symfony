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
use Symfony\Components\DependencyInjection\Dumper\YamlDumper;

class YamlDumperTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new YamlDumper($container = new Builder());

        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services1.yml', $dumper->dump(), '->dump() dumps an empty container as an empty YAML file');

        $container = new Builder();
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

        $dumper = new YamlDumper($container = new Builder());
        $container->register('foo', 'FooClass')->addArgument(new \stdClass());
        try {
            $dumper->dump();
            $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\RuntimeException', $e, '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
            $this->assertEquals('Unable to dump a service container if a parameter is an object or a resource.', $e->getMessage(), '->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
        }
    }
}
