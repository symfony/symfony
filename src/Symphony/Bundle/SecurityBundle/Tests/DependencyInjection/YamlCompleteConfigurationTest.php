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
use Symphony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symphony\Component\Config\FileLocator;

class YamlCompleteConfigurationTest extends CompleteConfigurationTest
{
    protected function getLoader(ContainerBuilder $container)
    {
        return new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/yml'));
    }

    protected function getFileExtension()
    {
        return 'yml';
    }
}
