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

/**
 * Adds all services with the tag "form.type" as argument
 * to the "form.type.loader" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class AddFormTypesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.type.loader')) {
            return;
        }

        // Builds an array with service IDs as keys and tag aliases as values
        $types = array();
        $tags = $container->findTaggedServiceIds('form.type');

        foreach ($tags as $serviceId => $arguments) {
            $alias = isset($arguments[0]['alias'])
                ? $arguments[0]['alias']
                : $serviceId;

            // Flip, because we want tag aliases (= type identifiers) as keys
            $types[$alias] = $serviceId;
        }

        $container->getDefinition('form.type.loader')->setArgument(1, $types);
    }
}