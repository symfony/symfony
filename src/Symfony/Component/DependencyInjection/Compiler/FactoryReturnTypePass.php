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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Guilhem N. <egetick@gmail.com>
 */
class FactoryReturnTypePass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // works only since php 7.0 and hhvm 3.11
        if (!method_exists(\ReflectionMethod::class, 'getReturnType')) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            $this->updateDefinition($container, $id, $definition);
        }
    }

    private function updateDefinition(ContainerBuilder $container, $id, Definition $definition, array $previous = array())
    {
        // circular reference
        if (isset($previous[$id])) {
            return;
        }

        $factory = $definition->getFactory();
        if (null === $factory || null !== $definition->getClass()) {
            return;
        }

        $class = null;
        if (is_string($factory)) {
            try {
                $m = new \ReflectionFunction($factory);
            } catch (\ReflectionException $e) {
                return;
            }
        } else {
            if ($factory[0] instanceof Reference) {
                $previous[$id] = true;
                $factoryDefinition = $container->findDefinition((string) $factory[0]);
                $this->updateDefinition($container, (string) $factory[0], $factoryDefinition, $previous);
                $class = $factoryDefinition->getClass();
            } else {
                $class = $factory[0];
            }

            try {
                $m = new \ReflectionMethod($class, $factory[1]);
            } catch (\ReflectionException $e) {
                return;
            }
        }

        $returnType = $m->getReturnType();
        if (null !== $returnType && !$returnType->isBuiltin()) {
            $returnType = (string) $returnType;
            if (null !== $class) {
                $declaringClass = $m->getDeclaringClass()->getName();
                if ('self' === $returnType) {
                    $returnType = $declaringClass;
                } elseif ('parent' === $returnType) {
                    $returnType = get_parent_class($declaringClass) ?: null;
                }
            }

            $definition->setClass($returnType);
        }
    }
}
