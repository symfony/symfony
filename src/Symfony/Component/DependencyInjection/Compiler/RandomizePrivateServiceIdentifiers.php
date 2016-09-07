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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Randomizes private service identifiers, effectively making them unaccessable from outside.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class RandomizePrivateServiceIdentifiers implements CompilerPassInterface
{
    private $idMap;
    private $randomizer;

    public function process(ContainerBuilder $container)
    {
        // update private definitions + build id map
        $this->idMap = array();
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->isPublic()) {
                $this->idMap[$id] = $this->randomizer ? (string) call_user_func($this->randomizer, $id) : hash('sha256', mt_rand().$id);
                $definition->setPublic(true);
            }
        }

        // rename definitions
        $aliases = $container->getAliases();
        foreach ($this->idMap as $oldId => $newId) {
            $container->setDefinition($newId, $container->getDefinition($oldId));
            $container->removeDefinition($oldId);
        }

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

        // update alias map
        $aliases = $container->getAliases();
        foreach ($container->getAliases() as $oldId => $alias) {
            if(isset($this->idMap[$oldId]) && $oldId === (string) $alias) {
                $container->setAlias(new Alias($this->idMap[$oldId], $alias->isPublic()), $this->idMap[$oldId]);
            }
        }

        // for BC
        $reflectionProperty = new \ReflectionProperty(ContainerBuilder::class, 'privates');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($container, $this->idMap);
    }

    public function setRandomizer(callable $randomizer = null)
    {
        $this->randomizer = $randomizer;
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
