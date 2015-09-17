<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpKernel\MicroKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class MicroKernelForTest extends MicroKernel
{
    private $configureServicesCalled = false;

    private $configureExtensionsCalled = false;

    private $configureServicesArgs = array();

    public function registerBundles()
    {
        return array();
    }

    public function getContainerLoaderExternally(ContainerInterface $container)
    {
        return $this->getContainerLoader($container);
    }

    protected function configureExtensions(ContainerBuilder $c, LoaderInterface $loader)
    {
        $this->configureExtensionsCalled = true;
    }

    protected function configureServices(ContainerBuilder $c, LoaderInterface $loader)
    {
        $this->configureServicesArgs = array($c, $loader);

        $this->configureServicesCalled = true;
    }

    public function wasConfigureServicesCalled()
    {
        return $this->configureServicesCalled;
    }

    public function wasConfigureExtensionsCalled()
    {
        return $this->configureExtensionsCalled;
    }

    public function getConfigureServicesArguments()
    {
        return $this->configureServicesArgs;
    }
}
