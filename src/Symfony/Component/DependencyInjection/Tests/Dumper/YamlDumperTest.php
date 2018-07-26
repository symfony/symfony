<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Yaml;

class YamlDumperTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new YamlDumper($container = new ContainerBuilder());

        $this->assertEqualYamlStructure(file_get_contents(self::$fixturesPath.'/yaml/services1.yml'), $dumper->dump(), '->dump() dumps an empty container as an empty YAML file');
    }

    public function testAddParameters()
    {
        $container = include self::$fixturesPath.'/containers/container8.php';
        $dumper = new YamlDumper($container);
        $this->assertEqualYamlStructure(file_get_contents(self::$fixturesPath.'/yaml/services8.yml'), $dumper->dump(), '->dump() dumps parameters');
    }

    /**
     * @group legacy
     */
    public function testLegacyAddService()
    {
        $container = include self::$fixturesPath.'/containers/legacy-container9.php';
        $dumper = new YamlDumper($container);

        $this->assertEquals(str_replace('%path%', self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, file_get_contents(self::$fixturesPath.'/yaml/legacy-services9.yml')), $dumper->dump(), '->dump() dumps services');

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

    public function testAddService()
    {
        $container = include self::$fixturesPath.'/containers/container9.php';
        $dumper = new YamlDumper($container);
        $this->assertEqualYamlStructure(str_replace('%path%', self::$fixturesPath.\DIRECTORY_SEPARATOR.'includes'.\DIRECTORY_SEPARATOR, file_get_contents(self::$fixturesPath.'/yaml/services9.yml')), $dumper->dump(), '->dump() dumps services');

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

    public function testDumpAutowireData()
    {
        $container = include self::$fixturesPath.'/containers/container24.php';
        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services24.yml', $dumper->dump());
    }

    public function testDumpLoad()
    {
        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator(self::$fixturesPath.'/yaml'));
        $loader->load('services_dump_load.yml');

        $dumper = new YamlDumper($container);
        $this->assertStringEqualsFile(self::$fixturesPath.'/yaml/services_dump_load.yml', $dumper->dump());
    }

    private function assertEqualYamlStructure($yaml, $expected, $message = '')
    {
        $this->assertEquals(Yaml::parse($expected), Yaml::parse($yaml), $message);
    }
}
