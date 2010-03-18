<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection;

require_once __DIR__.'/../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;

class CrossCheckTest extends \PHPUnit_Framework_TestCase
{
  static protected $fixturesPath;

  static public function setUpBeforeClass()
  {
    self::$fixturesPath = __DIR__.'/../../../../fixtures/Symfony/Components/DependencyInjection/';

    require_once self::$fixturesPath.'/includes/classes.php';
    require_once self::$fixturesPath.'/includes/foo.php';
  }

  public function testCrossCheck()
  {
    // cross-check loaders/dumpers

    $fixtures = array(
      'services1.xml' => 'xml',
      'services2.xml' => 'xml',
      'services6.xml' => 'xml',
      'services8.xml' => 'xml',
      'services9.xml' => 'xml',

      'services1.yml' => 'yaml',
      'services2.yml' => 'yaml',
      'services6.yml' => 'yaml',
      'services8.yml' => 'yaml',
      'services9.yml' => 'yaml',
    );

    foreach ($fixtures as $fixture => $type)
    {
      $loaderClass = 'Symfony\\Components\\DependencyInjection\\Loader\\'.ucfirst($type).'FileLoader';
      $dumperClass = 'Symfony\\Components\\DependencyInjection\\Dumper\\'.ucfirst($type).'Dumper';

      $container1 = new Builder();
      $loader1 = new $loaderClass($container1);
      $loader1->load(self::$fixturesPath.'/'.$type.'/'.$fixture);
      $container1->setParameter('path', self::$fixturesPath.'/includes');

      $dumper = new $dumperClass($container1);
      $tmp = tempnam('sf_service_container', 'sf');
      file_put_contents($tmp, $dumper->dump());

      $container2 = new Builder();
      $loader2 = new $loaderClass($container2);
      $loader2->load($tmp);
      $container2->setParameter('path', self::$fixturesPath.'/includes');

      unlink($tmp);

      $this->assertEquals(serialize($container1), serialize($container2), 'loading a dump from a previously loaded container returns the same container');

      $this->assertEquals($container1->getParameters(), $container2->getParameters(), '->getParameters() returns the same value for both containers');

      $services1 = array();
      foreach ($container1 as $id => $service)
      {
        $services1[$id] = serialize($service);
      }
      $services2 = array();
      foreach ($container2 as $id => $service)
      {
        $services2[$id] = serialize($service);
      }

      unset($services1['service_container'], $services2['service_container']);

      $this->assertEquals($services1, $services2, 'Iterator on the containers returns the same services');
    }
  }
}
