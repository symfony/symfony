<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\DependencyInjection;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symphony\Component\Config\FileLocator;

class PhpCompleteConfigurationTest extends CompleteConfigurationTest
{
    protected function getLoader(ContainerBuilder $container)
    {
        return new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures/php'));
    }

    protected function getFileExtension()
    {
        return 'php';
    }
}
