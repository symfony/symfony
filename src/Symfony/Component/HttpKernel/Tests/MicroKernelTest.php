<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\DependencyInjection\Loader\ContainerBuilderAwareLoader;
use Symfony\Component\HttpKernel\Tests\Fixtures\MicroKernelForTest;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;

class MicroKernelTest extends \PHPUnit_Framework_TestCase
{
    public function testGetContainerLoader()
    {
        $containerBuilder = new ContainerBuilder();
        $kernel = new MicroKernelForTest('test', false);

        $loader = $kernel->getContainerLoaderExternally($containerBuilder);
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\Loader\ContainerBuilderAwareLoader', $loader);
        $this->assertSame($containerBuilder, $loader->getContainerBuilder());
    }

    /**
     * @expectedException \LogicException
     */
    public function testGetContainerLoaderFailsUnlessBuilder()
    {
        $containerBuilder = new Container();
        $kernel = new MicroKernelForTest('test', false);

        $kernel->getContainerLoaderExternally($containerBuilder);
    }

    /**
     * @expectedException \LogicException
     */
    public function testRegisterContainerConfigurationOnlyAcceptsContainerAwareBuilderLoader()
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $kernel = new MicroKernelForTest('test', false);
        $kernel->registerContainerConfiguration($loader);
    }

    public function testRegisterContainerConfiguration()
    {
        $loader = $this->getContainerBuilderAwareLoader();
        $kernel = new MicroKernelForTest('test', false);
        $kernel->registerContainerConfiguration($loader);

        $this->assertTrue($kernel->wasConfigureServicesCalled());
        $configureServicesArgs = $kernel->getConfigureServicesArguments();
        $this->assertSame($loader->getResourceLoader(), $configureServicesArgs[1], 'The original loader is sent to configureServices');
    }

    private function getContainerBuilderAwareLoader()
    {
        $loader = $this->getMock('Symfony\Component\Config\Loader\LoaderInterface');
        $builder = $this->getMock('Symfony\Component\DependencyInjection\ContainerBuilder');

        return new ContainerBuilderAwareLoader($builder, $loader);
    }
}
