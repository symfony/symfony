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
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    public function testCollect()
    {
        $kernel = new KernelForTest('test', true);
        $c = new ConfigDataCollector();
        $c->setKernel($kernel);
        $c->collect(new Request(), new Response());

        $this->assertSame('test',$c->getEnv());
        $this->assertTrue($c->isDebug());
        $this->assertSame('config',$c->getName());
        $this->assertSame('testkernel',$c->getAppName());
        $this->assertSame(PHP_VERSION,$c->getPhpVersion());
        $this->assertSame(Kernel::VERSION,$c->getSymfonyVersion());
        $this->assertNull($c->getToken());
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

    public function init()
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
