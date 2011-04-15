<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Definition;

use Symfony\Component\DependencyInjection\Exception\NonExistentServiceException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks that all references are pointing to a valid service.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckExceptionOnInvalidReferenceBehaviorPass implements CompilerPassInterface
{
    private $container;
    private $sourceId;

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        foreach ($container->getDefinitions() as $id => $definition) {
            $this->sourceId = $id;
            $this->processDefinition($definition);
        }
    }

    private function processDefinition(Definition $definition)
    {
        $this->processReferences($definition->getArguments());
        $this->processReferences($definition->getMethodCalls());
        $this->processReferences($definition->getProperties());
    }

    private function processReferences(array $arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->processReferences($argument);
            } else if ($argument instanceof Definition) {
                $this->processDefinition($argument);
            } else if ($argument instanceof Reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $argument->getInvalidBehavior()) {
                $destId = (string) $argument;

                if (!$this->container->has($destId)) {
                    throw new NonExistentServiceException($destId, $this->sourceId);
                }
            }
        }
    }
}