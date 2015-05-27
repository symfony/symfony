<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\DataCollector;

use Symfony\Component\Profiler\DataCollector\ConfigDataCollector;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;

class ConfigDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        $c = new ConfigDataCollector($requestStack);
        $c->setKernel($kernel);
        $profileData = $c->collect();
        $this->assertInstanceOf('Symfony\Component\Profiler\ProfileData\ConfigData',$profileData);

        $this->assertSame('test', $profileData->getEnv());
        $this->assertTrue($profileData->isDebug());
        $this->assertSame('config', $profileData->getName());
        $this->assertSame('testkernel', $profileData->getAppName());
        $this->assertSame(PHP_VERSION, $profileData->getPhpVersion());
        $this->assertSame(Kernel::VERSION, $profileData->getSymfonyVersion());
        $this->assertNull($profileData->getToken());

        // if else clause because we don't know it
        if (extension_loaded('xdebug')) {
            $this->assertTrue($profileData->hasXdebug());
        } else {
            $this->assertFalse($profileData->hasXdebug());
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
            $this->assertTrue($profileData->hasAccelerator());
        } else {
            $this->assertFalse($profileData->hasAccelerator());
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
