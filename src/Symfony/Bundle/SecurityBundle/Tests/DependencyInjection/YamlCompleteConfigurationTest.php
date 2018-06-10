<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

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
