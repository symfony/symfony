<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds all services with the tags "form.type", "form.type_extension" and
 * "form.type_guesser" as arguments of the "form.extension" service.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('form.extension')) {
            return;
        }

        $definition = $container->getDefinition('form.extension');
        $definition->replaceArgument(0, $this->processFormTypes($container));
        $definition->replaceArgument(1, $this->processFormTypeExtensions($container));
        $definition->replaceArgument(2, $this->processFormTypeGuessers($container));
    }

    private function processFormTypes(ContainerBuilder $container): Reference
    {
        // Get service locator argument
        $servicesMap = [];
        $namespaces = ['Symfony\Component\Form\Extension\Core\Type' => true];

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->findTaggedServiceIds('form.type', true) as $serviceId => $tag) {
            // Add form type service to the service locator
            $serviceDefinition = $container->getDefinition($serviceId);
            $servicesMap[$formType = $serviceDefinition->getClass()] = new Reference($serviceId);
            $namespaces[substr($formType, 0, strrpos($formType, '\\'))] = true;
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(1, array_keys($namespaces));
            $commandDefinition->setArgument(2, array_keys($servicesMap));
        }

        return ServiceLocatorTagPass::register($container, $servicesMap);
    }

    private function processFormTypeExtensions(ContainerBuilder $container): array
    {
        $typeExtensions = [];
        $typeExtensionsClasses = [];
        foreach ($this->findAndSortTaggedServices('form.type_extension', $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);

            $tag = $serviceDefinition->getTag('form.type_extension');
            $typeExtensionClass = $container->getParameterBag()->resolveValue($serviceDefinition->getClass());

            if (isset($tag[0]['extended_type'])) {
                $typeExtensions[$tag[0]['extended_type']][] = new Reference($serviceId);
                $typeExtensionsClasses[] = $typeExtensionClass;
            } else {
                $extendsTypes = false;

                $typeExtensionsClasses[] = $typeExtensionClass;
                foreach ($typeExtensionClass::getExtendedTypes() as $extendedType) {
                    $typeExtensions[$extendedType][] = new Reference($serviceId);
                    $extendsTypes = true;
                }

                if (!$extendsTypes) {
                    throw new InvalidArgumentException(\sprintf('The getExtendedTypes() method for service "%s" does not return any extended types.', $serviceId));
                }
            }
        }

        foreach ($typeExtensions as $extendedType => $extensions) {
            $typeExtensions[$extendedType] = new IteratorArgument($extensions);
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(3, $typeExtensionsClasses);
        }

        return $typeExtensions;
    }

    private function processFormTypeGuessers(ContainerBuilder $container): ArgumentInterface
    {
        $guessers = [];
        $guessersClasses = [];
        foreach ($container->findTaggedServiceIds('form.type_guesser', true) as $serviceId => $tags) {
            $guessers[] = new Reference($serviceId);

            $serviceDefinition = $container->getDefinition($serviceId);
            $guessersClasses[] = $serviceDefinition->getClass();
        }

        if ($container->hasDefinition('console.command.form_debug')) {
            $commandDefinition = $container->getDefinition('console.command.form_debug');
            $commandDefinition->setArgument(4, $guessersClasses);
        }

        return new IteratorArgument($guessers);
    }
}
