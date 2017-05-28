<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Util;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

/**
 * Help finding services corresponding to a type.
 * Be aware that the map is constructed once, at the first call to {@link getOfType()}.
 *
 * @author Guilhem N <egetick@gmail.com>
 */
class ServiceTypeHelper
{
    private static $resolvedTypes = array();
    private $container;
    private $typeMap;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves services implementing a type.
     *
     * @param string $class
     */
    public function getOfType($class)
    {
        if (null === $this->typeMap) {
            $this->populateAvailableTypes();
        }

        if (!isset($this->typeMap[$class])) {
            return array();
        }

        return $this->typeMap[$class];
    }

    /**
     * Resets the type map.
     */
    public function reset()
    {
        $this->typeMap = null;
    }

    /**
     * Populates the list of available types.
     */
    private function populateAvailableTypes()
    {
        $this->typeMap = array();
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $this->populateAvailableType($id, $definition);
        }
    }

    /**
     * Populates the list of available types for a given definition.
     *
     * @param string     $id
     * @param Definition $definition
     */
    private function populateAvailableType($id, Definition $definition)
    {
        // Never use abstract services
        if ($definition->isAbstract()) {
            return;
        }

        $class = $this->container->getParameterBag()->resolveValue($definition->getClass());
        if (!$class) {
            return;
        }

        if (isset(self::$resolvedTypes[$class])) {
            $types = self::$resolvedTypes[$class];
        } else {
            $types = array();
            if ($interfaces = class_implements($class)) {
                $types = $interfaces;
            }

            do {
                $types[] = $class;
            } while ($class = get_parent_class($class));

            self::$resolvedTypes[$class] = $types;
        }

        foreach ($types as $type) {
            if (!isset($this->typeMap[$type])) {
                $this->typeMap[$type] = array();
            }

            $this->typeMap[$type][] = $id;
        }
    }
}
