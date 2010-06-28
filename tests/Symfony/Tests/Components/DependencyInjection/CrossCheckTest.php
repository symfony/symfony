<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\DependencyInjection;

use Symfony\Components\DependencyInjection\Builder;

class CrossCheckTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = __DIR__.'/Fixtures/';

        require_once self::$fixturesPath.'/includes/classes.php';
        require_once self::$fixturesPath.'/includes/foo.php';
    }

    /**
     * @dataProvider crossCheckLoadersDumpers
     */
    public function testCrossCheck($fixture, $type)
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

        $this->assertEquals(serialize($container2), serialize($container1), 'loading a dump from a previously loaded container returns the same container');

        $this->assertEquals($container2->getParameterBag()->all(), $container1->getParameterBag()->all(), '->getParameterBag() returns the same value for both containers');

        $services1 = array();
        foreach ($container1 as $id => $service) {
            $services1[$id] = serialize($service);
        }
        $services2 = array();
        foreach ($container2 as $id => $service) {
            $services2[$id] = serialize($service);
        }

        unset($services1['service_container'], $services2['service_container']);

        $this->assertEquals($services2, $services1, 'Iterator on the containers returns the same services');
    }

    public function crossCheckLoadersDumpers()
    {
        return array(
            array('services1.xml', 'xml'),
            array('services2.xml', 'xml'),
            array('services6.xml', 'xml'),
            array('services8.xml', 'xml'),
            array('services9.xml', 'xml'),

            array('services1.yml', 'yaml'),
            array('services2.yml', 'yaml'),
            array('services6.yml', 'yaml'),
            array('services8.yml', 'yaml'),
            array('services9.yml', 'yaml'),
        );
    }
}
