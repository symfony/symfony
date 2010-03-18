<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Dumper;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Dumper\YamlDumper;

class YamlDumperTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');
  }

  public function testDump()
  {
    $dumper = new YamlDumper($container = new Builder());

    $this->assertEquals($dumper->dump(), file_get_contents(self::$fixturesPath.'/yaml/services1.yml'), '->dump() dumps an empty container as an empty YAML file');

    $container = new Builder();
    $dumper = new YamlDumper($container);
  }

  public function testAddParameters()
  {
    $container = include self::$fixturesPath.'/containers/container8.php';
    $dumper = new YamlDumper($container);
    $this->assertEquals($dumper->dump(), file_get_contents(self::$fixturesPath.'/yaml/services8.yml'), '->dump() dumps parameters');
  }

  public function testAddService()
  {
    $container = include self::$fixturesPath.'/containers/container9.php';
    $dumper = new YamlDumper($container);
    $this->assertEquals($dumper->dump(), str_replace('%path%', self::$fixturesPath.'/includes', file_get_contents(self::$fixturesPath.'/yaml/services9.yml')), '->dump() dumps services');

    $dumper = new YamlDumper($container = new Builder());
    $container->register('foo', 'FooClass')->addArgument(new \stdClass());
    try
    {
      $dumper->dump();
      $this->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
    }
    catch (\RuntimeException $e)
    {
    }
  }
}
