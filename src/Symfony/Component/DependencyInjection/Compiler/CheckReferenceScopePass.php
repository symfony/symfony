<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Checks the scope of references
 *
 * Especially, we disallow services of wider scope to have references to
 * services of a narrower scope by default since it is generally a sign for a
 * wrong implementation.
 *
 * If someone specifically wants to allow this, then he can set the reference
 * to strict=false.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckReferenceScopePass implements CompilerPassInterface
{
    protected $container;
    protected $currentId;
    protected $currentScope;
    protected $currentScopeAncestors;
    protected $currentScopeChildren;

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
            $this->currentId = $id;
            $this->currentScope = $scope = $definition->getScope();

            if (ContainerInterface::SCOPE_PROTOTYPE === $scope) {
                continue;
            }

            if (ContainerInterface::SCOPE_CONTAINER === $scope) {
                $this->currentScopeChildren = array_keys($scopes);
                $this->currentScopeAncestors = array();
            } else {
                $this->currentScopeChildren = $children[$scope];
                $this->currentScopeAncestors = $ancestors[$scope];
            }

            $this->validateReferences($definition->getArguments());
            $this->validateReferences($definition->getMethodCalls());
        }
    }

    protected function validateReferences(array $arguments)
    {
        foreach ($arguments as $argument) {
            if (is_array($argument)) {
                $this->validateReferences($argument);
            } else if ($argument instanceof Reference) {
                if (!$argument->isStrict()) {
                    continue;
                }

                if (null === $definition = $this->getDefinition($id = (string) $argument)) {
                    continue;
                }

                if ($this->currentScope === $scope = $definition->getScope()) {
                    continue;
                }

                if (in_array($scope, $this->currentScopeChildren, true)) {
                    throw new \RuntimeException(sprintf(
                        'Scope Widening Injection detected: The definition "%s" references the service "%s" which belongs to a narrower scope. '
                       .'Generally, it is safer to either move "%s" to scope "%s" or alternatively rely on the provider pattern by injecting the container itself, and requesting the service "%s" each time it is needed. '
                       .'In rare, special cases however that might not be necessary, then you can set the reference to strict=false to get rid of this warning.',
                       $this->currentId,
                       $id,
                       $this->currentId,
                       $scope,
                       $id
                    ));
                }

                if (!in_array($scope, $this->currentScopeAncestors, true)) {
                    throw new \RuntimeException(sprintf(
                        'Cross-Scope Injection detected: The definition "%s" references the service "%s" which belongs to another scope hierarchy. '
                       .'This service might not be available consistently. Generally, it is safer to either move the definition "%s" to scope "%s", or '
                       .'declare "%s" as a child scope of "%s". If you can be sure that the other scope is always active, you can set the reference to strict=false to get rid of this warning.',
                       $this->currentId,
                       $id,
                       $this->currentId,
                       $scope,
                       $this->currentScope,
                       $scope
                    ));
                }
            }
        }
    }

    protected function getDefinition($id)
    {
        if (!$this->container->hasDefinition($id)) {
            return null;
        }

        return $this->container->getDefinition($id);
    }
}