<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all services with the tags "form.type" and "form.type_guesser" as
 * arguments of the "form.extension" service
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FormPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        // Builds an array with service IDs as keys and tag aliases as values
        $types = array();

        foreach ($container->findTaggedServiceIds('form.type') as $serviceId => $tag) {
            $alias = isset($tag[0]['alias'])
                ? $tag[0]['alias']
                : $serviceId;

            // Flip, because we want tag aliases (= type identifiers) as keys
            $types[$alias] = $serviceId;
        }

        $container->getDefinition('form.extension')->replaceArgument(1, $types);

        $typeExtensions = array();

        foreach ($container->findTaggedServiceIds('form.type_extension') as $serviceId => $tag) {
            $alias = isset($tag[0]['alias'])
                ? $tag[0]['alias']
                : $serviceId;

            $typeExtensions[$alias][] = $serviceId;
        }

        $container->getDefinition('form.extension')->replaceArgument(2, $typeExtensions);

        // Find all services annotated with "form.type_guesser"
        $guessers = array_keys($container->findTaggedServiceIds('form.type_guesser'));

        $container->getDefinition('form.extension')->replaceArgument(3, $guessers);
    }
}
