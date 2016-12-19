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
use Symfony\Component\DependencyInjection\Definition;

/**
 * Help finding services corresponding to a type.
 * Be aware that the map is constructed once, at the first call to {@link getOfType()}.
 *
 * @author Guilhem N. <egetick@gmail.com>
 */
final class ServiceTypeHelper
{
    private static $classNames = array();
    private $container;
    private $typeMap;

    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves services implementing a type.
     *
     * @param string $type a class or an interface
     *
     * @return string[] the services implementing the type
     */
    public function getOfType($type)
    {
        if (null === $this->typeMap) {
            $this->populateAvailableTypes();
        }

        if (!isset($this->typeMap[$type])) {
            return array();
        }

        return $this->typeMap[$type];
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
        $throwingAutoloader = function ($class) {
            throw new \ReflectionException(sprintf('Class %s does not exist', $class));
        };
        spl_autoload_register($throwingAutoloader);

        try {
            $this->typeMap = array();
            foreach ($this->container->getDefinitions() as $id => $definition) {
                $this->populateAvailableType($id, $definition);
            }
        } finally {
            spl_autoload_unregister($throwingAutoloader);
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

        if (null === ($class = $this->getClass($definition))) {
            return;
        }

        $types = array();
        if ($interfaces = class_implements($class)) {
            $types = $interfaces;
        }

        do {
            $types[] = $class;
        } while ($class = get_parent_class($class));

        foreach ($types as $type) {
            if (!isset($this->typeMap[$type])) {
                $this->typeMap[$type] = array();
            }

            $this->typeMap[$type][] = $id;
        }
    }

    /**
     * Retrieves the class associated with the given service.
     *
     * @param Definition $definition
     *
     * @return string|null
     */
    private function getClass(Definition $definition)
    {
        // Cannot use reflection if the class isn't set
        if (!$class = $definition->getClass()) {
            return;
        }

        // Normalize the class name (`\Foo` -> `Foo`)
        $class = $this->container->getParameterBag()->resolveValue($class);
        if (array_key_exists($class, self::$classNames)) {
            return self::$classNames[$class];
        }

        try {
            $name = (new \ReflectionClass($class))->name;
        } catch (\ReflectionException $e) {
            $name = null;
        }

        return self::$classNames[$class] = $name;
    }
}
