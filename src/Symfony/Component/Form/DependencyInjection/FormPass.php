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

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Adds all services with the tags "form.type" and "form.type_guesser" as
 * arguments of the "form.extension" service.
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

        // Builds an array with fully-qualified type class names as keys and service IDs as values
        $types = array();
        foreach ($container->findTaggedServiceIds($this->formTypeTag) as $serviceId => $tag) {
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must be public as form types are lazy-loaded.', $serviceId));
            }

            // Support type access by FQCN
            $types[$serviceDefinition->getClass()] = $serviceId;
        }

        $definition->replaceArgument(1, $types);

        $typeExtensions = array();

        foreach ($this->findAndSortTaggedServices($this->formTypeExtensionTag, $container) as $reference) {
            $serviceId = (string) $reference;
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must be public as form type extensions are lazy-loaded.', $serviceId));
            }

            $tag = $serviceDefinition->getTag($this->formTypeExtensionTag);
            if (isset($tag[0]['extended_type'])) {
                $extendedType = $tag[0]['extended_type'];
            } else {
                throw new InvalidArgumentException(sprintf('"%s" tagged services must have the extended type configured using the extended_type/extended-type attribute, none was configured for the "%s" service.', $this->formTypeExtensionTag, $serviceId));
            }

            $typeExtensions[$extendedType][] = $serviceId;
        }

        $definition->replaceArgument(2, $typeExtensions);

        $guessers = array_keys($container->findTaggedServiceIds($this->formTypeGuesserTag));
        foreach ($guessers as $serviceId) {
            $serviceDefinition = $container->getDefinition($serviceId);
            if (!$serviceDefinition->isPublic()) {
                throw new InvalidArgumentException(sprintf('The service "%s" must be public as form type guessers are lazy-loaded.', $serviceId));
            }
        }

        $definition->replaceArgument(3, $guessers);
    }
}
