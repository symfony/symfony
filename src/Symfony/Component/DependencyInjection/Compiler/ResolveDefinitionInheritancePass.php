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
use Symfony\Component\DependencyInjection\Definition;

/**
 * Applies tags and instanceof inheritance to definitions.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ResolveDefinitionInheritancePass extends AbstractRecursivePass
{
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }

        $class = $value instanceof ChildDefinition ? $this->resolveDefinition($value) : $value->getClass();

        if (!$class || false !== strpos($class, '%') || !$instanceof = $value->getInstanceofConditionals()) {
            return parent::processValue($value, $isRoot);
        }
        $value->setInstanceofConditionals(array());

        foreach ($instanceof as $interface => $definition) {
            if ($interface !== $class && (!$this->container->getReflectionClass($interface) || !$this->container->getReflectionClass($class))) {
                continue;
            }
            if ($interface === $class || is_subclass_of($class, $interface)) {
                $this->mergeDefinition($value, $definition);
            }
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * Populates the class and tags from parent definitions.
     */
    private function resolveDefinition(ChildDefinition $definition)
    {
        if (!$this->container->has($parent = $definition->getParent())) {
            return;
        }

        $parentDef = $this->container->findDefinition($parent);
        $class = $parentDef instanceof ChildDefinition ? $this->resolveDefinition($parentDef) : $parentDef->getClass();
        $class = $definition->getClass() ?: $class;

        // append parent tags when inheriting is enabled
        if ($definition->getInheritTags()) {
            $definition->setInheritTags(false);

            foreach ($parentDef->getTags() as $k => $v) {
                foreach ($v as $v) {
                    $definition->addTag($k, $v);
                }
            }
        }

        return $class;
    }

    private function mergeDefinition(Definition $def, ChildDefinition $definition)
    {
        $changes = $definition->getChanges();
        if (isset($changes['shared'])) {
            $def->setShared($definition->isShared());
        }
        if (isset($changes['abstract'])) {
            $def->setAbstract($definition->isAbstract());
        }
        if (isset($changes['autowired_calls'])) {
            $autowiredCalls = $def->getAutowiredCalls();
        }

        ResolveDefinitionTemplatesPass::mergeDefinition($def, $definition);

        // merge autowired calls
        if (isset($changes['autowired_calls'])) {
            $def->setAutowiredCalls(array_merge($autowiredCalls, $def->getAutowiredCalls()));
        }

        // merge tags
        foreach ($definition->getTags() as $k => $v) {
            foreach ($v as $v) {
                $def->addTag($k, $v);
            }
        }
    }
}
