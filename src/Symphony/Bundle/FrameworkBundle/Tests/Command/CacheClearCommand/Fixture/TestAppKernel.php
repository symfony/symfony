<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture;

use Psr\Log\NullLogger;
use Symphony\Bundle\FrameworkBundle\FrameworkBundle;
use Symphony\Component\Config\Loader\LoaderInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\HttpKernel\Kernel;

class TestAppKernel extends Kernel
{
    public function registerBundles()
    {
        return array(
            new FrameworkBundle(),
        );
    }

    public function setRootDir($rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.DIRECTORY_SEPARATOR.'config.yml');
    }

    protected function build(ContainerBuilder $container)
    {
        $container->register('logger', NullLogger::class);
    }
}
