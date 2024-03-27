<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\CacheWarmer\DefinitionAndValidator;
use Symfony\Component\Workflow\Validator\ConfiguredDefinitionValidatorInterface;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class DefinitionValidatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('workflow.cache_warmer.definition_validator')) {
            return;
        }

        $definitions = [];
        foreach ($container->findTaggedServiceIds('workflow.definition') as $id => $attributes) {
            $name = $attributes[0]['name'] ?? throw new InvalidArgumentException(sprintf('The "name" attribute is mandatory for the "workflow.definition" tag. Check the tag for service "%s".', $id));
            $definitions[$name] = new Reference($id);
        }

        $definitionAndValidators = [];
        foreach ($container->findTaggedServiceIds('workflow.definition_validator') as $id => $attributes) {
            $def = $container->getDefinition($id);

            $class = $def->getClass();

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }
            if (!$r->isSubclassOf(ConfiguredDefinitionValidatorInterface::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, ConfiguredDefinitionValidatorInterface::class));
            }

            foreach ($class::getSupportedWorkflows() as $name) {
                if ('*' === $name) {
                    foreach ($definitions as $definitionName => $definition) {
                        $definitionAndValidators[] = new Definition(
                            DefinitionAndValidator::class,
                            [
                                new Reference($id),
                                $definition,
                                $definitionName,
                            ]
                        );
                    }
                } elseif (isset($definitions[$name])) {
                    $definitionAndValidators[] = new Definition(
                        DefinitionAndValidator::class,
                        [
                            new Reference($id),
                            $definitions[$name],
                            $name,
                        ]
                    );
                } else {
                    throw new InvalidArgumentException(sprintf('The workflow "%s" does not exist. Check the "getConfiguration()" method of the service "%s".', $name, $id));
                }
            }
        }

        $container
            ->getDefinition('workflow.cache_warmer.definition_validator')
            ->replaceArgument(0, $definitionAndValidators)
        ;
    }
}
