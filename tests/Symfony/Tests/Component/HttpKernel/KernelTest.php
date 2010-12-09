<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Loader\LoaderInterface;

class KernelTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSafeName()
    {
        $kernel = new KernelForTest('dev', true, '-foo-');

        $this->assertEquals('foo', $kernel->getSafeName());
    }

    public function testHandleSetsTheRequest()
    {
        $masterRequest = Request::create('/');
        $subRequest = Request::create('/');

        $httpKernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernel')
            ->disableOriginalConstructor()
            ->setMethods(array('handle'))
            ->getMock();

        $httpKernel->expects($this->at(0))
            ->method('handle')
            ->with($masterRequest);

        $httpKernel->expects($this->at(1))
            ->method('handle')
            ->with($subRequest);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\Container')
            ->disableOriginalConstructor()
            ->setMethods(array('get'))
            ->getMock();

        $container->expects($this->exactly(2))
            ->method('get')
            ->with('http_kernel')
            ->will($this->returnValue($httpKernel));

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
            ->setConstructorArgs(array('dev', true, '-foo-'))
            ->setMethods(array('boot'))
            ->getMock();

        $kernel->setContainer($container);

        $testCase = $this;
        $bootCallback = function() use ($masterRequest, $kernel, $testCase) {
            $kernel->setBooted(true);
            $testCase->assertSame($masterRequest, $kernel->getRequest(), '->handle() sets the Request before booting');
        };

        $kernel->expects($this->once())
            ->method('boot')
            ->will($this->returnCallback($bootCallback));

        $kernel->handle($masterRequest);
        $kernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

        $this->assertSame($masterRequest, $kernel->getRequest(), '->handle() restores the master Request after handling a sub-request');
    }
}

class KernelForTest extends Kernel
{
    public function __construct($environment, $debug, $name)
    {
        parent::__construct($environment, $debug);

        $this->name = $name;
    }

    public function registerRootDir()
    {
    }

    public function registerBundles()
    {
    }

    public function registerBundleDirs()
    {
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function setBooted($booted)
    {
        $this->booted = $booted;
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }
}