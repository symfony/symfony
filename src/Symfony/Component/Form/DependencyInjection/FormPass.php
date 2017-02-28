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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
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

    private $formExtensionService;
    private $formTypeTag;
    private $formTypeExtensionTag;
    private $formTypeGuesserTag;

    public function __construct($formExtensionService = 'form.extension', $formTypeTag = 'form.type', $formTypeExtensionTag = 'form.type_extension', $formTypeGuesserTag = 'form.type_guesser')
    {
        $this->formExtensionService = $formExtensionService;
        $this->formTypeTag = $formTypeTag;
        $this->formTypeExtensionTag = $formTypeExtensionTag;
        $this->formTypeGuesserTag = $formTypeGuesserTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->formExtensionService)) {
            return;
        }

        $definition = $container->getDefinition($this->formExtensionService);
        $definition->replaceArgument(0, $this->processFormTypes($container, $definition));
        $definition->replaceArgument(1, $this->processFormTypeExtensions($container));
        $definition->replaceArgument(2, $this->processFormTypeGuessers($container));
    }

    private function processFormTypes(ContainerBuilder $container, Definition $definition)
    {
        // Get service locator argument
        $servicesMap = array();
        $locator = $definition->getArgument(0);
        if ($locator instanceof ServiceLocatorArgument) {
            $servicesMap = $locator->getValues();
        }

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->findTaggedServiceIds($this->formTypeTag) as $serviceId => $tag) {
            $serviceDefinition = $container->getDefinition($serviceId);

            // Add form type service to the service locator
            $servicesMap[$serviceDefinition->getClass()] = new Reference($serviceId);
        }

        return new ServiceLocatorArgument($servicesMap);
    }

    private function processFormTypeExtensions(ContainerBuilder $container)
    {
        $typeExtensions = array();
        foreach ($this->findAndSortTaggedServices($this->formTypeExtensionTag, $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);

            $tag = $serviceDefinition->getTag($this->formTypeExtensionTag);
            if (isset($tag[0]['extended_type'])) {
                $extendedType = $tag[0]['extended_type'];
            } else {
                throw new InvalidArgumentException(sprintf('"%s" tagged services must have the extended type configured using the extended_type/extended-type attribute, none was configured for the "%s" service.', $this->formTypeExtensionTag, $serviceId));
            }

            $typeExtensions[$extendedType][] = new Reference($serviceId);
        }

        foreach ($typeExtensions as $extendedType => $extensions) {
            $typeExtensions[$extendedType] = new IteratorArgument($extensions);
        }

        return $typeExtensions;
    }

    private function processFormTypeGuessers(ContainerBuilder $container)
    {
        $guessers = array();
        foreach ($container->findTaggedServiceIds($this->formTypeGuesserTag) as $serviceId => $tags) {
            $guessers[] = new Reference($serviceId);
        }

        return new IteratorArgument($guessers);
    }
}
