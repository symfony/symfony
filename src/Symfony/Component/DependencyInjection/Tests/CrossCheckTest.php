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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\XmlDumper;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CrossCheckTest extends TestCase
{
    protected static string $fixturesPath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesPath = __DIR__.'/Fixtures/';

        require_once self::$fixturesPath.'/includes/classes.php';
        require_once self::$fixturesPath.'/includes/foo.php';
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    #[DataProvider('crossCheckXmlLoadersDumpers')]
    public function testXmlCrossCheck($fixture)
    {
        $this->expectUserDeprecationMessage('Since symfony/dependency-injection 7.4: XML configuration format is deprecated, use YAML or PHP instead.');

        $tmp = tempnam(sys_get_temp_dir(), 'sf');

        copy(self::$fixturesPath.'/xml/'.$fixture, $tmp);

        $container1 = new ContainerBuilder();
        $loader1 = new XmlFileLoader($container1, new FileLocator());
        $loader1->load($tmp);

        $dumper = new XmlDumper($container1);
        file_put_contents($tmp, $dumper->dump());

        $container2 = new ContainerBuilder();
        $loader2 = new XmlFileLoader($container2, new FileLocator());
        $loader2->load($tmp);

        unlink($tmp);

        $this->assertEquals($container1->getAliases(), $container2->getAliases(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container1->getDefinitions(), $container2->getDefinitions(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container1->getParameterBag()->all(), $container2->getParameterBag()->all(), '->getParameterBag() returns the same value for both containers');
        $this->assertEquals(serialize($container1), serialize($container2), 'loading a dump from a previously loaded container returns the same container');

        $services1 = [];
        foreach ($container1 as $id => $service) {
            $services1[$id] = serialize($service);
        }
        $services2 = [];
        foreach ($container2 as $id => $service) {
            $services2[$id] = serialize($service);
        }

        unset($services1['service_container'], $services2['service_container']);

        $this->assertEquals($services1, $services2, 'Iterator on the containers returns the same services');
    }

    #[DataProvider('crossCheckYamlLoadersDumpers')]
    public function testYamlCrossCheck($fixture)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sf');

        copy(self::$fixturesPath.'/yaml/'.$fixture, $tmp);

        $container1 = new ContainerBuilder();
        $loader1 = new YamlFileLoader($container1, new FileLocator());
        $loader1->load($tmp);

        $dumper = new YamlDumper($container1);
        file_put_contents($tmp, $dumper->dump());

        $container2 = new ContainerBuilder();
        $loader2 = new YamlFileLoader($container2, new FileLocator());
        $loader2->load($tmp);

        unlink($tmp);

        $this->assertEquals($container2->getAliases(), $container1->getAliases(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container2->getDefinitions(), $container1->getDefinitions(), 'loading a dump from a previously loaded container returns the same container');
        $this->assertEquals($container2->getParameterBag()->all(), $container1->getParameterBag()->all(), '->getParameterBag() returns the same value for both containers');
        $this->assertEquals(serialize($container2), serialize($container1), 'loading a dump from a previously loaded container returns the same container');

        $services1 = [];
        foreach ($container1 as $id => $service) {
            $services1[$id] = serialize($service);
        }
        $services2 = [];
        foreach ($container2 as $id => $service) {
            $services2[$id] = serialize($service);
        }

        unset($services1['service_container'], $services2['service_container']);

        $this->assertEquals($services2, $services1, 'Iterator on the containers returns the same services');
    }

    public static function crossCheckXmlLoadersDumpers()
    {
        return [
            ['services1.xml'],
            ['services2.xml'],
            ['services6.xml'],
            ['services8.xml'],
            ['services9.xml'],
        ];
    }

    public static function crossCheckYamlLoadersDumpers()
    {
        return [
            ['services1.yml'],
            ['services2.yml'],
            ['services6.yml'],
            ['services8.yml'],
            ['services9.yml'],
        ];
    }
}
