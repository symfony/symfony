<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures;

use Symphony\Component\Config\Loader\LoaderInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\HttpKernel\Kernel;

class KernelWithoutBundles extends Kernel
{
    public function registerBundles()
    {
        return array();
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    protected function build(ContainerBuilder $container)
    {
        $container->setParameter('test_executed', true);
    }
}
