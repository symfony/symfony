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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Applies instanceof conditionals to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveInstanceofConditionalsPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition instanceof ChildDefinition) {
                // don't apply "instanceof" to children: it will be applied to their parent
                continue;
            }
            $container->setDefinition($id, $this->processDefinition($container, $id, $definition));
        }
    }

    private function processDefinition(ContainerBuilder $container, $id, Definition $definition)
    {
        if (!$instanceofConditionals = $definition->getInstanceofConditionals()) {
            return $definition;
        }
        if (!$class = $container->getParameterBag()->resolveValue($definition->getClass())) {
            return $definition;
        }

        $definition->setInstanceofConditionals(array());
        $parent = $shared = null;
        $instanceofTags = array();

        foreach ($instanceofConditionals as $interface => $instanceofDef) {
            if ($interface !== $class && (!$container->getReflectionClass($interface) || !$container->getReflectionClass($class))) {
                continue;
            }
            if ($interface === $class || is_subclass_of($class, $interface)) {
                $instanceofDef = clone $instanceofDef;
                $instanceofDef->setAbstract(true)->setInheritTags(false)->setParent($parent ?: 'abstract.instanceof.'.$id);
                $parent = 'instanceof.'.$interface.'.'.$id;
                $container->setDefinition($parent, $instanceofDef);
                $instanceofTags[] = $instanceofDef->getTags();
                $instanceofDef->setTags(array());

                if (isset($instanceofDef->getChanges()['shared'])) {
                    $shared = $instanceofDef->isShared();
                }
            }
        }

        if ($parent) {
            $abstract = $container->setDefinition('abstract.instanceof.'.$id, $definition);

            // cast Definition to ChildDefinition
            $definition = serialize($definition);
            $definition = substr_replace($definition, '53', 2, 2);
            $definition = substr_replace($definition, 'Child', 44, 0);
            $definition = unserialize($definition);
            $definition->setParent($parent);

            if (null !== $shared && !isset($definition->getChanges()['shared'])) {
                $definition->setShared($shared);
            }

            $i = count($instanceofTags);
            while (0 <= --$i) {
                foreach ($instanceofTags[$i] as $k => $v) {
                    foreach ($v as $v) {
                        $definition->addTag($k, $v);
                    }
                }
            }

            // reset fields with "merge" behavior
            $abstract
                ->setArguments(array())
                ->setMethodCalls(array())
                ->setTags(array())
                ->setAbstract(true);
        }

        return $definition;
    }
}
