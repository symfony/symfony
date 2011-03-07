<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass as BaseMergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Handles automatically loading each bundle's default extension.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class MergeExtensionConfigurationPass extends BaseMergeExtensionConfigurationPass
{
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getParameter('kernel.bundles') as $bundleName => $bundleClass) {
            $bundleRefl = new \ReflectionClass($bundleClass);
            $extClass = $bundleRefl->getNamespaceName().'\\DependencyInjection\\'.substr($bundleName, 0, -6).'Extension';

            if (class_exists($extClass)) {
                $ext = new $extClass();
                $alias = $ext->getAlias();

                // ensure all "main" extensions are loaded
                if (!count($container->getExtensionConfig($alias))) {
                    $container->loadFromExtension($alias, array());
                }
            }
        }

        parent::process($container);
    }
}
