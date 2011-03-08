<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Sets the class map for the autoloader.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddClassesToAutoloadMapPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        $classes = array();
        foreach ($container->getExtensions() as $extension) {
            if ($extension instanceof Extension) {
                $classes = array_merge($classes, $extension->getAutoloadClassMap());
            }
        }

        $container->setParameter('kernel.autoload_classes', array_unique($classes));
    }
}
