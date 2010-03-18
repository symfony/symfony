<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection\Loader;

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Loader\IniFileLoader;

class IniLoaderTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');
  }

  public function testLoader()
  {
    $loader = new IniFileLoader(self::$fixturesPath.'/ini');
    $config = $loader->load('parameters.ini');
    $this->assertEquals($config->getParameters(), array('foo' => 'bar', 'bar' => '%foo%'), '->load() takes a single file name as its first argument');

    try
    {
      $loader->load('foo.ini');
      $this->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
    }
    catch (\InvalidArgumentException $e)
    {
    }

    try
    {
      @$loader->load('nonvalid.ini');
      $this->fail('->load() throws an InvalidArgumentException if the loaded file is not parseable');
    }
    catch (\InvalidArgumentException $e)
    {
    }
  }
}
