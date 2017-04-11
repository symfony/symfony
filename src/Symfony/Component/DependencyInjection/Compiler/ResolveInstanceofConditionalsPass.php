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
        $didProcess = false;
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition instanceof ChildDefinition) {
                // don't apply "instanceof" to children: it will be applied to their parent
                continue;
            }
            if ($definition !== $processedDefinition = $this->processDefinition($container, $id, $definition)) {
                $didProcess = true;
                $container->setDefinition($id, $processedDefinition);
            }
        }
        if ($didProcess) {
            $container->register('abstract.'.__CLASS__, '')->setAbstract(true);
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
        $instanceofParent = null;
        $parent = 'abstract.'.__CLASS__;
        $shared = null;

        foreach ($instanceofConditionals as $interface => $instanceofDef) {
            if ($interface !== $class && (!$container->getReflectionClass($interface) || !$container->getReflectionClass($class))) {
                continue;
            }
            if ($interface === $class || is_subclass_of($class, $interface)) {
                $instanceofParent = clone $instanceofDef;
                $instanceofParent->setAbstract(true)->setInheritTags(true)->setParent($parent);
                $parent = 'instanceof.'.$interface.'.'.$id;
                $container->setDefinition($parent, $instanceofParent);

                if (isset($instanceofParent->getChanges()['shared'])) {
                    $shared = $instanceofParent->isShared();
                }
            }
        }

        if ($instanceofParent) {
            // cast Definition to ChildDefinition
            $definition = serialize($definition);
            $definition = substr_replace($definition, '53', 2, 2);
            $definition = substr_replace($definition, 'Child', 44, 0);
            $definition = unserialize($definition);
            $definition->setInheritTags(true)->setParent($parent);

            if (null !== $shared && !isset($definition->getChanges()['shared'])) {
                $definition->setShared($shared);
            }
        }

        return $definition;
    }
}
