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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\BadServiceClassException;

/**
 * Checks your service exists by checking its definition "class" and "factory_class" keys
 *
 * @author Leszek "l3l0" Prabucki <leszek.prabucki@gmail.com>
 */
class CheckServiceClassPass implements CompilerPassInterface
{
    /**
     * Checks if ContainerBuilder services exists
     *
     * @param ContainerBuilder $container The ContainerBuilder instances
     */
    public function process(ContainerBuilder $container)
    {
        $parameterBag = $container->getParameterBag();
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->allowsToCheckClassExistenceForClass($definition) && !class_exists($parameterBag->resolveValue($definition->getClass()))) {
                throw new BadServiceClassException($id, $definition->getClass(), 'class');
            }
            if ($this->allowsToCheckClassExistenceForFactoryClass($definition) && !class_exists($parameterBag->resolveValue($definition->getFactoryClass()))) {
                throw new BadServiceClassException($id, $definition->getFactoryClass(), 'factory_class');
            }
        }
    }

    private function allowsToCheckClassExistenceForClass(Definition $definition)
    {
        return $definition->getClass() && !$this->isFactoryDefinition($definition) && !$definition->isSynthetic();
    }

    private function allowsToCheckClassExistenceForFactoryClass(Definition $definition)
    {
        return $definition->getFactoryClass() && !$definition->isSynthetic();
    }

    private function isFactoryDefinition(Definition $definition)
    {
       return $definition->getFactoryClass() || $definition->getFactoryService();
    }
}
