<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\ScopeWideningInjectionException;
use Symfony\Component\DependencyInjection\Exception\ScopeCrossingInjectionException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the validity of references
 *
 * The following checks are performed by this pass:
 * - target definitions are not abstract
 * - target definitions are of equal or wider scope
 * - target definitions are in the same scope hierarchy
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckReferenceValidityPass implements CompilerPassInterface
{
    private $container;
    private $currentId;
    private $currentDefinition;
    private $currentScope;
    private $currentScopeAncestors;
    private $currentScopeChildren;

    /**
     * Processes the ContainerBuilder to validate References.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $this->container = $container;

        $children = $this->container->getScopeChildren();
        $ancestors = array();

        $scopes = $this->container->getScopes();
        foreach ($scopes as $name => $parent) {
            $ancestors[$name] = array($parent);

            while (isset($scopes[$parent])) {
                $ancestors[$name][] = $parent = $scopes[$parent];
            }
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isSynthetic() || $definition->isAbstract()) {
                continue;
            }

            $this->currentId = $id;
            $this->currentDefinition = $definition;
            $this->currentScope = $scope = $definition->getScope();

            if (ContainerInterface::SCOPE_CONTAINER === $scope) {
                $this->currentScopeChildren = array_keys($scopes);
                $this->currentScopeAncestors = array();
            } else if (ContainerInterface::SCOPE_PROTOTYPE !== $scope) {
                $this->currentScopeChildren = $children[$scope];
                $this->currentScopeAncestors = $ancestors[$scope];
            }

            $this->validateReferences($definition->getArguments());
            $this->validateReferences($definition->getMethodCalls());
            $this->validateReferences($definition->getProperties());
        }
    }

    /**
     * Validates an array of References.
     *
     * @param array $arguments An array of Reference objects
     * @throws \RuntimeException when there is a reference to an abstract definition.
     */
    private function validateReferences(array $arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->validateReferences($argument);
            } elseif ($argument instanceof Reference) {
                $targetDefinition = $this->getDefinition((string) $argument);

                if (null !== $targetDefinition && $targetDefinition->isAbstract()) {
                    throw new \RuntimeException(sprintf(
                        'The definition "%s" has a reference to an abstract definition "%s". '
                       .'Abstract definitions cannot be the target of references.',
                       $this->currentId,
                       $argument
                    ));
                }

                $this->validateScope($argument, $targetDefinition);
            }
        }
    }

    /**
     * Validates the scope of a single Reference.
     *
     * @param Reference $reference
     * @param Definition $definition
     * @throws \RuntimeException when there is an issue with the Reference scope
     */
    private function validateScope(Reference $reference, Definition $definition = null)
    {
        if (ContainerInterface::SCOPE_PROTOTYPE === $this->currentScope) {
            return;
        }

        if (!$reference->isStrict()) {
            return;
        }

        if (null === $definition) {
            return;
        }

        if ($this->currentScope === $scope = $definition->getScope()) {
            return;
        }

        $id = (string) $reference;

        if (in_array($scope, $this->currentScopeChildren, true)) {
            throw new ScopeWideningInjectionException($this->currentId, $this->currentScope, $id, $scope);
        }

        if (!in_array($scope, $this->currentScopeAncestors, true)) {
            throw new ScopeCrossingInjectionException($this->currentId, $this->currentScope, $id, $scope);
        }
    }

    /**
     * Returns the Definition given an id.
     *
     * @param string $id Definition identifier
     * @return Definition
     */
    private function getDefinition($id)
    {
        if (!$this->container->hasDefinition($id)) {
            return null;
        }

        return $this->container->getDefinition($id);
    }
}
