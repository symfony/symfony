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

@trigger_error(sprintf('The %s class is deprecated since Symfony 3.3 and will be removed in 4.0. Use Symfony\Component\Form\DependencyInjection\FormPass instead.', FormPass::class), E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Adds all services with the tags "form.type" and "form.type_guesser" as
 * arguments of the "form.extension" service.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0. Use FormPass in the Form component instead.
 */
class FormPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        $definition = $container->getDefinition('form.extension');

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        $types = array();

        foreach ($container->findTaggedServiceIds('form.type') as $serviceId => $tag) {
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                $serviceDefinition->setPublic(true);
            }

            // Support type access by FQCN
            $types[$serviceDefinition->getClass()] = $serviceId;
        }

        $definition->replaceArgument(1, $types);

        $typeExtensions = array();

        foreach ($this->findAndSortTaggedServices('form.type_extension', $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                $serviceDefinition->setPublic(true);
            }

            $tag = $serviceDefinition->getTag('form.type_extension');
            if (isset($tag[0]['extended_type'])) {
                $extendedType = $tag[0]['extended_type'];
            } else {
                throw new InvalidArgumentException(sprintf('Tagged form type extension must have the extended type configured using the extended_type/extended-type attribute, none was configured for the "%s" service.', $serviceId));
            }

            $typeExtensions[$extendedType][] = $serviceId;
        }

        $definition->replaceArgument(2, $typeExtensions);

        // Find all services annotated with "form.type_guesser"
        $guessers = array_keys($container->findTaggedServiceIds('form.type_guesser'));
        foreach ($guessers as $serviceId) {
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                $serviceDefinition->setPublic(true);
            }
        }

        $definition->replaceArgument(3, $guessers);
    }
}
