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

    $this->assertEquals($dumper->dump(), file_get_contents(self::$fixturesPath.'/php/services1.php'), '->dump() dumps an empty container as an empty PHP class');
    $this->assertEquals($dumper->dump(array('class' => 'Container', 'base_class' => 'AbstractContainer')), file_get_contents(self::$fixturesPath.'/php/services1-1.php'), '->dump() takes a class and a base_class options');

    $container = new Builder();
    $dumper = new PhpDumper($container);
  }

  public function testAddParameters()
  {
    $container = include self::$fixturesPath.'/containers/container8.php';
    $dumper = new PhpDumper($container);
    $this->assertEquals($dumper->dump(), file_get_contents(self::$fixturesPath.'/php/services8.php'), '->dump() dumps parameters');
  }

  public function testAddService()
  {
    $container = include self::$fixturesPath.'/containers/container9.php';
    $dumper = new PhpDumper($container);
    $this->assertEquals($dumper->dump(), str_replace('%path%', self::$fixturesPath.'/includes', file_get_contents(self::$fixturesPath.'/php/services9.php')), '->dump() dumps services');

    $dumper = new PhpDumper($container = new Builder());
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
