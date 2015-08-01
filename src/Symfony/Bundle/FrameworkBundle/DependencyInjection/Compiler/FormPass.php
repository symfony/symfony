<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds all services with the tags "form.type" and "form.type_guesser" as
 * arguments of the "form.extension" service.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        $definition = $container->getDefinition('form.extension');

        // Builds an array with service IDs as keys and tag aliases as values
        $types = array();

        // Remember which names will not be supported in Symfony 3.0 to trigger
        // deprecation errors
        $legacyNames = array();

        foreach ($container->findTaggedServiceIds('form.type') as $serviceId => $tag) {
            // The following if-else block is deprecated and will be removed
            // in Symfony 3.0
            // Deprecation errors are triggered in DependencyInjectionExtension
            if (isset($tag[0]['alias'])) {
                $types[$tag[0]['alias']] = $serviceId;
                $legacyNames[$tag[0]['alias']] = true;
            } else {
                $types[$serviceId] = $serviceId;
                $legacyNames[$serviceId] = true;
            }

            // Support type access by FQCN
            $serviceDefinition = $container->getDefinition($serviceId);
            $types[$serviceDefinition->getClass()] = $serviceId;
        }

        $definition->replaceArgument(1, $types);
        $definition->replaceArgument(4, $legacyNames);

        $typeExtensions = array();

        foreach ($container->findTaggedServiceIds('form.type_extension') as $serviceId => $tag) {
            $alias = isset($tag[0]['alias'])
                ? $tag[0]['alias']
                : $serviceId;

            $typeExtensions[$alias][] = $serviceId;
        }

        $definition->replaceArgument(2, $typeExtensions);

        // Find all services annotated with "form.type_guesser"
        $guessers = array_keys($container->findTaggedServiceIds('form.type_guesser'));

        $definition->replaceArgument(3, $guessers);
    }
}
