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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpKernel\Kernel;

class ConfigDataCollectorTest extends TestCase
{
    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $c = new ConfigDataCollector();
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
        if (\extension_loaded('xdebug')) {
            $this->assertTrue($c->hasXDebug());
        } else {
            $this->assertFalse($c->hasXDebug());
        }

        // if else clause because we don't know it
        if (((\extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
                ||
                (\extension_loaded('apc') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN))
                ||
                (\extension_loaded('Zend OPcache') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN))
                ||
                (\extension_loaded('xcache') && filter_var(ini_get('xcache.cacher'), FILTER_VALIDATE_BOOLEAN))
                ||
                (\extension_loaded('wincache') && filter_var(ini_get('wincache.ocenabled'), FILTER_VALIDATE_BOOLEAN)))) {
            $this->assertTrue($c->hasAccelerator());
        } else {
            $this->assertFalse($c->hasAccelerator());
        }
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
