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

        $class = $value->getClass();

        if (!$class || false !== strpos($class, '%') || !$instanceof = $value->getInstanceofConditionals()) {
            return parent::processValue($value, $isRoot);
        }
        $value->setInstanceofConditionals(array());

        foreach ($instanceof as $interface => $definition) {
            if ($interface !== $class && (!$this->container->getReflectionClass($interface) || !$this->container->getReflectionClass($class))) {
                continue;
            }
            if ($interface === $class || is_subclass_of($class, $interface)) {
                $this->mergeInstanceofDefinition($value, $definition);
            }
        }

        return parent::processValue($value, $isRoot);
    }

    private function mergeInstanceofDefinition(Definition $def, ChildDefinition $instanceofDefinition)
    {
        $configured = $def->getChanges();
        $changes = $instanceofDefinition->getChanges();
        if (!isset($configured['shared']) && isset($changes['shared'])) {
            $def->setShared($instanceofDefinition->isShared());
        }
        if (!isset($configured['configurator']) && isset($changes['configurator'])) {
            $def->setConfigurator($instanceofDefinition->getConfigurator());
        }
        if (!isset($configured['public']) && isset($changes['public'])) {
            $def->setPublic($instanceofDefinition->isPublic());
        }
        if (!isset($configured['lazy']) && isset($changes['lazy'])) {
            $def->setLazy($instanceofDefinition->isLazy());
        }
        if (!isset($configured['autowired']) && isset($changes['autowired'])) {
            $def->setAutowired($instanceofDefinition->getAutowired());
        }
        // merge properties
        $properties = $def->getProperties();
        foreach ($instanceofDefinition->getProperties() as $k => $v) {
            // don't override properties set explicitly on the service
            if (!isset($properties[$k])) {
                $def->setProperty($k, $v);
            }
        }
        // append method calls
        if ($calls = $instanceofDefinition->getMethodCalls()) {
            $def->setMethodCalls(array_merge($def->getMethodCalls(), $calls));
        }
        // merge tags
        $tags = $def->getTags();
        foreach ($instanceofDefinition->getTags() as $k => $v) {
            // don't add a tag if one by that name was already added
            if (!isset($tags[$k])) {
                // loop over the tag attributes arrays, add each
                foreach ($v as $v) {
                    $def->addTag($k, $v);
                }
            }
        }
    }
}
