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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class PhpCompleteConfigurationTest extends CompleteConfigurationTestCase
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
