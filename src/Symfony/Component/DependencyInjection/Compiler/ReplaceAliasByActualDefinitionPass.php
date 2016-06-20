<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Replaces aliases with actual service definitions, effectively removing these
 * aliases.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ReplaceAliasByActualDefinitionPass implements CompilerPassInterface
{
    private $compiler;
    private $formatter;

    /**
     * Process the Container to replace aliases with service definitions.
     *
     * @param ContainerBuilder $container
     *
     * @throws InvalidArgumentException if the service definition does not exist
     */
    public function process(ContainerBuilder $container)
    {
        // Setup
        $this->compiler = $container->getCompiler();
        $this->formatter = $this->compiler->getLoggingFormatter();
        // First collect all alias targets that need to be replaced
        $seenAliasTargets = array();
        $replacements = array();
        foreach ($container->getAliases() as $definitionId => $target) {
            $targetId = (string) $target;
            // Special case: leave this target alone
            if ('service_container' === $targetId) {
                continue;
            }
            // Check if target needs to be replaces
            if (isset($replacements[$targetId])) {
                $container->setAlias($definitionId, $replacements[$targetId]);
            }
            // No neeed to process the same target twice
            if (isset($seenAliasTargets[$targetId])) {
                continue;
            }
            // Process new target
            $seenAliasTargets[$targetId] = true;
            try {
                $definition = $container->getDefinition($targetId);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException(sprintf('Unable to replace alias "%s" with actual definition "%s".', $definitionId, $targetId), null, $e);
            }
            if ($definition->isPublic()) {
                continue;
            }
            // Remove private definition and schedule for replacement
            $definition->setPublic(true);
            $container->setDefinition($definitionId, $definition);
            $container->removeDefinition($targetId);
            $replacements[$targetId] = $definitionId;
        }

        // Now replace target instances in all definitions
        foreach ($container->getDefinitions() as $definitionId => $definition) {
            $definition->setArguments($this->updateArgumentReferences($replacements, $definitionId, $definition->getArguments()));
            $definition->setMethodCalls($this->updateArgumentReferences($replacements, $definitionId, $definition->getMethodCalls()));
            $definition->setProperties($this->updateArgumentReferences($replacements, $definitionId, $definition->getProperties()));
            $definition->setFactoryService($this->updateFactoryReferenceId($replacements, $definition->getFactoryService(false)), false);
            $definition->setFactory($this->updateFactoryReference($replacements, $definition->getFactory()));
        }
    }

    /**
     * Recursively updates references in an array.
     *
     * @param array  $replacements Table of aliases to replace
     * @param string $definitionId Identifier of this definition
     * @param array  $arguments    Where to replace the aliases
     *
     * @return array
     */
    private function updateArgumentReferences(array $replacements, $definitionId, array $arguments)
    {
        foreach ($arguments as $k => $argument) {
            // Handle recursion step
            if (is_array($argument)) {
                $arguments[$k] = $this->updateArgumentReferences($replacements, $definitionId, $argument);
                continue;
            }
            // Skip arguments that don't need replacement
            if (!$argument instanceof Reference) {
                continue;
            }
            $referenceId = (string) $argument;
            if (!isset($replacements[$referenceId])) {
                continue;
            }
            // Perform the replacement
            $newId = $replacements[$referenceId];
            $arguments[$k] = new Reference($newId, $argument->getInvalidBehavior());
            $this->compiler->addLogMessage($this->formatter->formatUpdateReference($this, $definitionId, $referenceId, $newId));
        }

        return $arguments;
    }

    /**
     * Returns the updated reference for the factory service.
     *
     * @param array       $replacements Table of aliases to replace
     * @param string|null $referenceId  Factory service reference identifier
     *
     * @return string|null
     */
    private function updateFactoryReferenceId(array $replacements, $referenceId)
    {
        if (null === $referenceId) {
            return;
        }

        return isset($replacements[$referenceId]) ? $replacements[$referenceId] : $referenceId;
    }

    private function updateFactoryReference(array $replacements, $factory)
    {
        if (is_array($factory) && $factory[0] instanceof Reference && isset($replacements[$referenceId = (string) $factory[0]])) {
            $factory[0] = new Reference($replacements[$referenceId], $factory[0]->getInvalidBehavior());
        }

        return $factory;
    }
}
