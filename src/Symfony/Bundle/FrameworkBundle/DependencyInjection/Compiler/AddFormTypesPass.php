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
        $types = array_map(function ($arguments) {
            if (!isset($arguments[0]['alias'])) {
                // TODO throw exception
            }

            return $arguments[0]['alias'];
        }, $container->findTaggedServiceIds('form.type'));

        // Flip, because we want tag aliases (= type identifiers) as keys
        $types = array_flip($types);

        $container->getDefinition('form.type.loader')->setArgument(1, $types);
    }
}