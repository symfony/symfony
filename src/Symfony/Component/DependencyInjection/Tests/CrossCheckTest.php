<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CrossCheckTest extends TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
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
        $loaderClass = 'Symfony\\Component\\DependencyInjection\\Loader\\'.ucfirst($type).'FileLoader';
        $dumperClass = 'Symfony\\Component\\DependencyInjection\\Dumper\\'.ucfirst($type).'Dumper';

        $tmp = tempnam(sys_get_temp_dir(), 'sf');

        copy(self::$fixturesPath.'/'.$type.'/'.$fixture, $tmp);

        $container1 = new ContainerBuilder();
        $loader1 = new $loaderClass($container1, new FileLocator());
        $loader1->load($tmp);

        $dumper = new $dumperClass($container1);
        file_put_contents($tmp, $dumper->dump());

        $container2 = new ContainerBuilder();
        $loader2 = new $loaderClass($container2, new FileLocator());
        $loader2->load($tmp);

        unlink($tmp);

        $this->assertEquals($container2->getAliases(), $container1->getAliases(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container2->getDefinitions(), $container1->getDefinitions(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container2->getParameterBag()->all(), $container1->getParameterBag()->all(), '->getParameterBag() returns the same value for both containers');
        $this->assertEquals(serialize($container2), serialize($container1), 'loading a dump from a previously loaded container returns the same container');

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
