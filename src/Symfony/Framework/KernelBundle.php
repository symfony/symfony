<?php

namespace Symfony\Framework;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Framework\ClassCollectionLoader;
use Symfony\Components\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * KernelBundle.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class KernelBundle extends Bundle
{
    /**
     * Boots the Bundle.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function boot(ContainerInterface $container)
    {
        if ($container->has('error_handler')) {
            $container['error_handler'];
        }

        // load core classes
        if ($container->getParameterBag()->has('kernel.include_core_classes') && $container->getParameter('kernel.include_core_classes')) {
            ClassCollectionLoader::load($container->getParameter('kernel.compiled_classes'), $container->getParameter('kernel.cache_dir'), 'classes', $container->getParameter('kernel.debug'));
        }
    }
}
