<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller\PhpMarshaller;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Configurator
{
    public static $configurators = array();

    public function __construct(?Registry $registry, ?Values $values, array $properties, $value, array $wakeups)
    {
        $this->{0} = $registry;
        $this->{1} = $values;
        $this->{2} = $properties;
        $this->{3} = $value;
        $this->{4} = $wakeups;
    }

    public static function __set_state($state)
    {
        $objects = Registry::$objects;
        list(Registry::$objects, Registry::$references) = \array_pop(Registry::$stack);
        list(, , $properties, $value, $wakeups) = $state;

        foreach ($properties as $class => $vars) {
            (self::$configurators[$class] ?? self::getConfigurator($class))($vars, $objects);
        }
        foreach ($wakeups as $i) {
            $objects[$i]->__wakeup();
        }

        return $value;
    }

    public static function getConfigurator($class)
    {
        $classReflector = Registry::$reflectors[$class] ?? Registry::getClassReflector($class);

        if (!$classReflector->isInternal()) {
            return self::$configurators[$class] = \Closure::bind(function ($properties, $objects) {
                foreach ($properties as $name => $values) {
                    foreach ($values as $i => $v) {
                        $objects[$i]->$name = $v;
                    }
                }
            }, null, $class);
        }

        switch ($class) {
            case 'ArrayIterator':
            case 'ArrayObject':
                $constructor = $classReflector->getConstructor();

                return self::$configurators[$class] = static function ($properties, $objects) use ($constructor) {
                    foreach ($properties as $name => $values) {
                        if ("\0" !== $name) {
                            foreach ($values as $i => $v) {
                                $objects[$i]->$name = $v;
                            }
                        }
                    }
                    foreach ($properties["\0"] as $i => $v) {
                        $constructor->invokeArgs($objects[$i], $v);
                    }
                };

            case 'SplObjectStorage':
                return self::$configurators[$class] = static function ($properties, $objects) {
                    foreach ($properties as $name => $values) {
                        if ("\0" === $name) {
                            foreach ($values as $i => $v) {
                                for ($j = 0; $j < \count($v); ++$j) {
                                    $objects[$i]->attach($v[$j], $v[++$j]);
                                }
                            }
                            continue;
                        }
                        foreach ($values as $i => $v) {
                            $objects[$i]->$name = $v;
                        }
                    }
                };
        }

        $propertyReflectors = array();
        foreach ($classReflector->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE) as $propertyReflector) {
            if (!$propertyReflector->isStatic()) {
                $propertyReflector->setAccessible(true);
                $propertyReflectors[$propertyReflector->name] = $propertyReflector;
            }
        }

        return self::$configurators[$class] = static function ($properties, $objects) use ($propertyReflectors) {
            foreach ($properties as $name => $values) {
                if (isset($propertyReflectors[$name])) {
                    foreach ($values as $i => $v) {
                        $propertyReflectors[$name]->setValue($objects[$i], $v);
                    }
                } else {
                    foreach ($values as $i => $v) {
                        $objects[$i]->$name = $v;
                    }
                }
            }
        };
    }
}
