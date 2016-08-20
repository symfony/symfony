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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Randomizes private service identifiers, effectively making them unaccessable from outside.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class RandomizePrivateServiceIdentifiers implements CompilerPassInterface
{
    private $idMap;

    public function process(ContainerBuilder $container)
    {
        // build id map
        $this->idMap = array();
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->isPublic()) {
                $this->idMap[$id] = md5(uniqid($id));
            }
        }

        // update private definitions + alias map
        $aliases = $container->getAliases();
        foreach ($this->idMap as $id => $randId) {
            $definition = $container->getDefinition($id);
            Definition::markAsPrivateOrigin($id, $definition);
            $container->setDefinition($randId, $definition);
            $container->removeDefinition($id);
            foreach ($aliases as $aliasId => $aliasedId) {
                if ((string) $aliasedId === $id) {
                    $aliases[$aliasId] = new Alias($randId, $aliasedId->isPublic());
                }
            }
        }
        $container->setAliases($aliases);

        // update referencing definitions
        foreach ($container->getDefinitions() as $id => $definition) {
            $definition->setArguments($this->processArguments($definition->getArguments()));
            $definition->setMethodCalls($this->processArguments($definition->getMethodCalls()));
            $definition->setProperties($this->processArguments($definition->getProperties()));
            $definition->setFactory($this->processFactory($definition->getFactory()));
            if (null !== ($decorated = $definition->getDecoratedService()) && isset($this->idMap[$decorated[0]])) {
                $definition->setDecoratedService($this->idMap[$decorated[0]], $decorated[1], $decorated[2]);
            }
        }
    }

    private function processArguments(array $arguments)
    {
        foreach ($arguments as $k => $argument) {
            if (is_array($argument)) {
                $arguments[$k] = $this->processArguments($argument);
            } elseif ($argument instanceof Reference && isset($this->idMap[$id = (string) $argument])) {
                $arguments[$k] = new Reference($this->idMap[$id], $argument->getInvalidBehavior());
            }
        }

        return $arguments;
    }

    private function processFactory($factory)
    {
        if (null === $factory || !is_array($factory) || !$factory[0] instanceof Reference) {
            return $factory;
        }
        if (isset($this->idMap[$id = (string) $factory[0]])) {
            $factory[0] = new Reference($this->idMap[$id], $factory[0]->getInvalidBehavior());
        }

        return $factory;
    }
}
