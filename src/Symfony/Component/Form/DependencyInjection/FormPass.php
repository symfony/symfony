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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormTypeExtensionInterface;

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
    private $formDebugCommandService;

    public function __construct(string $formExtensionService = 'form.extension', string $formTypeTag = 'form.type', string $formTypeExtensionTag = 'form.type_extension', string $formTypeGuesserTag = 'form.type_guesser', string $formDebugCommandService = 'console.command.form_debug')
    {
        $this->formExtensionService = $formExtensionService;
        $this->formTypeTag = $formTypeTag;
        $this->formTypeExtensionTag = $formTypeExtensionTag;
        $this->formTypeGuesserTag = $formTypeGuesserTag;
        $this->formDebugCommandService = $formDebugCommandService;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->formExtensionService)) {
            return;
        }

        $definition = $container->getDefinition($this->formExtensionService);
        if (new IteratorArgument([]) != $definition->getArgument(2)) {
            return;
        }
        $definition->replaceArgument(0, $this->processFormTypes($container));
        $definition->replaceArgument(1, $this->processFormTypeExtensions($container));
        $definition->replaceArgument(2, $this->processFormTypeGuessers($container));
    }

    private function processFormTypes(ContainerBuilder $container)
    {
        // Get service locator argument
        $servicesMap = [];
        $namespaces = ['Symfony\Component\Form\Extension\Core\Type' => true];

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        foreach ($container->findTaggedServiceIds($this->formTypeTag, true) as $serviceId => $tag) {
            // Add form type service to the service locator
            $serviceDefinition = $container->getDefinition($serviceId);
            $servicesMap[$formType = $serviceDefinition->getClass()] = new Reference($serviceId);
            $namespaces[substr($formType, 0, strrpos($formType, '\\'))] = true;
        }

        if ($container->hasDefinition($this->formDebugCommandService)) {
            $commandDefinition = $container->getDefinition($this->formDebugCommandService);
            $commandDefinition->setArgument(1, array_keys($namespaces));
            $commandDefinition->setArgument(2, array_keys($servicesMap));
        }

        return ServiceLocatorTagPass::register($container, $servicesMap);
    }

    private function processFormTypeExtensions(ContainerBuilder $container)
    {
        $typeExtensions = [];
        $typeExtensionsClasses = [];
        foreach ($this->findAndSortTaggedServices($this->formTypeExtensionTag, $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);

            $tag = $serviceDefinition->getTag($this->formTypeExtensionTag);
            if (isset($tag[0]['extended_type'])) {
                if (!method_exists($serviceDefinition->getClass(), 'getExtendedTypes')) {
                    @trigger_error(sprintf('Not implementing the static getExtendedTypes() method in %s when implementing the %s is deprecated since Symfony 4.2. The method will be added to the interface in 5.0.', $serviceDefinition->getClass(), FormTypeExtensionInterface::class), E_USER_DEPRECATED);
                }

                $typeExtensions[$tag[0]['extended_type']][] = new Reference($serviceId);
                $typeExtensionsClasses[] = $serviceDefinition->getClass();
            } elseif (method_exists($serviceDefinition->getClass(), 'getExtendedTypes')) {
                $extendsTypes = false;

                foreach ($serviceDefinition->getClass()::getExtendedTypes() as $extendedType) {
                    $typeExtensions[$extendedType][] = new Reference($serviceId);
                    $typeExtensionsClasses[] = $serviceDefinition->getClass();
                    $extendsTypes = true;
                }

                if (!$extendsTypes) {
                    throw new InvalidArgumentException(sprintf('The getExtendedTypes() method for service "%s" does not return any extended types.', $serviceId));
                }
            } else {
                throw new InvalidArgumentException(sprintf('"%s" tagged services have to implement the static getExtendedTypes() method. The class for service "%s" does not implement it.', $this->formTypeExtensionTag, $serviceId));
            }
        }

        foreach ($typeExtensions as $extendedType => $extensions) {
            $typeExtensions[$extendedType] = new IteratorArgument($extensions);
        }

        if ($container->hasDefinition($this->formDebugCommandService)) {
            $commandDefinition = $container->getDefinition($this->formDebugCommandService);
            $commandDefinition->setArgument(3, $typeExtensionsClasses);
        }

        return $typeExtensions;
    }

    private function processFormTypeGuessers(ContainerBuilder $container)
    {
        $guessers = [];
        $guessersClasses = [];
        foreach ($container->findTaggedServiceIds($this->formTypeGuesserTag, true) as $serviceId => $tags) {
            $guessers[] = new Reference($serviceId);

            $serviceDefinition = $container->getDefinition($serviceId);
            $guessersClasses[] = $serviceDefinition->getClass();
        }

        if ($container->hasDefinition($this->formDebugCommandService)) {
            $commandDefinition = $container->getDefinition($this->formDebugCommandService);
            $commandDefinition->setArgument(4, $guessersClasses);
        }

        return new IteratorArgument($guessers);
    }
}
