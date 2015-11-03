<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $c = new ConfigDataCollector();
        $c->setCacheVersionInfo(false);
        $c->setKernel($kernel);
        $c->collect(new Request(), new Response());

        $this->assertSame('test', $c->getEnv());
        $this->assertTrue($c->isDebug());
        $this->assertSame('config', $c->getName());
        $this->assertSame('testkernel', $c->getAppName());
        $this->assertSame(PHP_VERSION, $c->getPhpVersion());
        $this->assertSame(Kernel::VERSION, $c->getSymfonyVersion());
        $this->assertNull($c->getToken());

        // if else clause because we don't know it
        if (extension_loaded('xdebug')) {
            $this->assertTrue($c->hasXDebug());
        } else {
            $this->assertFalse($c->hasXDebug());
        }

        // if else clause because we don't know it
        if (((extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
                ||
                (extension_loaded('apc') && ini_get('apc.enabled'))
                ||
                (extension_loaded('Zend OPcache') && ini_get('opcache.enable'))
                ||
                (extension_loaded('xcache') && ini_get('xcache.cacher'))
                ||
                (extension_loaded('wincache') && ini_get('wincache.ocenabled')))) {
            $this->assertTrue($c->hasAccelerator());
        } else {
            $this->assertFalse($c->hasAccelerator());
        }
    }

    /**
     * @dataProvider getBundleData()
     */
    public function testBundleData($installedPackages, $enabledBundles, $expectedResult)
    {
        $collector = $this->getMockBuilder('Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector')
            ->setMethods(array('getInstalledPackages', 'getEnabledBundles'))
            ->getMock()
        ;

        $collector
            ->expects($this->once())
            ->method('getInstalledPackages')
            ->with($this->anything())
            ->will($this->returnValue($installedPackages))
        ;
        $collector
            ->expects($this->once())
            ->method('getEnabledBundles')
            ->with($this->anything())
            ->will($this->returnValue($enabledBundles))
        ;

        $this->assertEquals($expectedResult, $collector->getBundleData());
    }

    public function getBundleData()
    {
        return array(
            array(
                array(
                    "doctrine/doctrine-fixtures-bundle" => "v2.2.0",
                    "sensio/framework-extra-bundle" => "v3.0.10",
                    "symfony/monolog-bundle" => "v2.7.1",
                    "sensio/generator-bundle" => "v2.5.3",
                ),
                array(
                    "SecurityBundle" => "/Users/fabien/project/vendor/symfony/symfony/src/Symfony/Bundle/SecurityBundle",
                    "MonologBundle" => "/Users/fabien/project/vendor/symfony/monolog-bundle",
                    "SensioFrameworkExtraBundle" => "/Users/fabien/project/vendor/sensio/framework-extra-bundle",
                    "DoctrineFixturesBundle" => "/Users/fabien/project/vendor/doctrine/doctrine-fixtures-bundle/Doctrine/Bundle/FixturesBundle",
                    "AppBundle" => "/Users/fabien/project/src/AppBundle",
                    "SensioGeneratorBundle" => "/Users/fabien/project/vendor/sensio/generator-bundle/Sensio/Bundle/GeneratorBundle",
                ),
                array(
                    "AppBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/src/AppBundle",
                        "version" => null,
                    ),
                    "DoctrineFixturesBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/vendor/doctrine/doctrine-fixtures-bundle/Doctrine/Bundle/FixturesBundle",
                        "version" => "v2.2.0",
                    ),
                    "MonologBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/vendor/symfony/monolog-bundle",
                        "version" => "v2.7.1",
                    ),
                    "SecurityBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/vendor/symfony/symfony/src/Symfony/Bundle/SecurityBundle",
                        "version" => "2.8.0-DEV",
                    ),
                    "SensioFrameworkExtraBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/vendor/sensio/framework-extra-bundle",
                        "version" => "v3.0.10",
                    ),
                    "SensioGeneratorBundle" => array(
                        "name" => "AppBundle",
                        "path" => "/Users/fabien/project/vendor/sensio/generator-bundle/Sensio/Bundle/GeneratorBundle",
                        "version" => "v2.5.3",
                    ),
                )
            )
        );
    }
}

class KernelForTest extends Kernel
{
    public function getName()
    {
        return 'testkernel';
    }

    public function registerBundles()
    {
    }

    public function getBundles()
    {
        return array();
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }
}
