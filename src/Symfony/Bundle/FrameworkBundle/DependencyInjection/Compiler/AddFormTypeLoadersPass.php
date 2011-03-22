<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds all services with the tag "form.type.loader" as argument
 * to the "form.type.loader.chain" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class AddFormTypeLoadersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.type.loader.chain')) {
            return;
        }

        $definition = $container->getDefinition('form.type.loader.chain');

        foreach ($container->findTaggedServiceIds('form.type.loader') as $serviceId => $tag) {
            $definition->addMethodCall('addLoader', array(new Reference($serviceId)));
        }
    }
}