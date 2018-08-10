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
class Marshaller
{
    /**
     * Prepares an array of values for PhpMarshaller.
     *
     * For performance this method is public and has no type-hints.
     *
     * @param array             &$values
     * @param \SplObjectStorage $objectsPool
     * @param array             &$refsPool
     * @param int               &$objectsCount
     * @param bool              &$valuesAreStatic
     *
     * @return int
     *
     * @throws \Exception When a value cannot be serialized
     *
     * @internal
     */
    public static function marshall($values, $objectsPool, &$refsPool, &$objectsCount, &$valuesAreStatic)
    {
        $refs = $values;
        foreach ($values as $k => $value) {
            if (\is_resource($value)) {
                throw new \Exception(sprintf("Serialization of '%s' resource is not allowed", \get_resource_type($value)));
            }
            $refs[$k] = $objectsPool;

            if ($isRef = !$valueIsStatic = $values[$k] !== $objectsPool) {
                $values[$k] = &$value; // Break hard references to make $values completely
                unset($value);         // independent from the original structure
                $refs[$k] = $value = $values[$k];
                if ($value instanceof Reference && 0 > $value->id) {
                    $valuesAreStatic = false;
                    continue;
                }
                $refsPool[] = array(&$refs[$k], $value, &$value);
                $refs[$k] = $values[$k] = new Reference(-\count($refsPool));
            }

            if (\is_array($value)) {
                if ($value) {
                    $value = self::marshall($value, $objectsPool, $refsPool, $objectsCount, $valueIsStatic);
                }
                goto handle_value;
            } elseif (!\is_object($value) && !$value instanceof \__PHP_Incomplete_Class) {
                goto handle_value;
            }

            $valueIsStatic = false;
            if (isset($objectsPool[$value])) {
                ++$objectsCount;
                $value = new Reference($objectsPool[$value][0]);
                goto handle_value;
            }

            $class = \get_class($value);
            $properties = array();
            $sleep = null;
            $arrayValue = (array) $value;

            if (!isset(Registry::$prototypes[$class])) {
                // Might throw Exception("Serialization of '...' is not allowed")
                Registry::getClassReflector($class);
                serialize(Registry::$prototypes[$class]);
            }
            $proto = Registry::$prototypes[$class];

            if ($value instanceof \ArrayIterator || $value instanceof \ArrayObject) {
                // ArrayIterator and ArrayObject need special care because their "flags"
                // option changes the behavior of the (array) casting operator.
                $proto = Registry::$cloneable[$class] ? clone Registry::$prototypes[$class] : Registry::$reflectors[$class]->newInstanceWithoutConstructor();
                $properties = self::getArrayObjectProperties($value, $arrayValue, $proto);
            } elseif ($value instanceof \SplObjectStorage) {
                // By implementing Serializable, SplObjectStorage breaks internal references,
                // let's deal with it on our own.
                foreach (clone $value as $v) {
                    $properties[] = $v;
                    $properties[] = $value[$v];
                }
                $properties = array('SplObjectStorage' => array("\0" => $properties));
            } elseif ($value instanceof \Serializable || $value instanceof \__PHP_Incomplete_Class) {
                ++$objectsCount;
                $objectsPool[$value] = array($id = \count($objectsPool), serialize($value), array(), 0);
                $value = new Reference($id);
                goto handle_value;
            }

            if (\method_exists($class, '__sleep')) {
                if (!\is_array($sleep = $value->__sleep())) {
                    trigger_error('serialize(): __sleep should return an array only containing the names of instance-variables to serialize', E_USER_NOTICE);
                    $value = null;
                    goto handle_value;
                }
                $sleep = array_flip($sleep);
            }

            $proto = (array) $proto;

            foreach ($arrayValue as $name => $v) {
                $n = (string) $name;
                if ('' === $n || "\0" !== $n[0]) {
                    $c = $class;
                } elseif ('*' === $n[1]) {
                    $c = $class;
                    $n = substr($n, 3);
                } else {
                    $i = strpos($n, "\0", 2);
                    $c = substr($n, 1, $i - 1);
                    $n = substr($n, 1 + $i);
                }
                if (null === $sleep) {
                    $properties[$c][$n] = $v;
                } elseif (isset($sleep[$n]) && $c === $class) {
                    $properties[$c][$n] = $v;
                    unset($sleep[$n]);
                }
                if (\array_key_exists($name, $proto) && $proto[$name] === $v) {
                    unset($properties[$c][$n]);
                }
            }
            if ($sleep) {
                foreach ($sleep as $n => $v) {
                    trigger_error(sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $n), E_USER_NOTICE);
                }
            }

            $objectsPool[$value] = array($id = \count($objectsPool));
            $properties = self::marshall($properties, $objectsPool, $refsPool, $objectsCount, $valueIsStatic);
            ++$objectsCount;
            $objectsPool[$value] = array($id, $class, $properties, \method_exists($class, '__wakeup') ? $objectsCount : 0);

            $value = new Reference($id);

            handle_value:
            if ($isRef) {
                unset($value); // Break the hard reference created above
            } elseif (!$valueIsStatic) {
                $values[$k] = $value;
            }
            $valuesAreStatic = $valueIsStatic && $valuesAreStatic;
        }

        return $values;
    }

    /**
     * Extracts the state of an ArrayIterator or ArrayObject instance.
     *
     * For performance this method is public and has no type-hints.
     *
     * @param \ArrayIterator|\ArrayObject $value
     * @param array                       &$arrayValue
     * @param object                      $proto
     *
     * @return array
     *
     * @internal
     */
    public static function getArrayObjectProperties($value, &$arrayValue, $proto)
    {
        $reflector = $value instanceof \ArrayIterator ? 'ArrayIterator' : 'ArrayObject';
        $reflector = Registry::$reflectors[$reflector] ?? Registry::getClassReflector($reflector);

        $properties = array(
            $arrayValue,
            $reflector->getMethod('getFlags')->invoke($value),
            $value instanceof \ArrayObject ? $reflector->getMethod('getIteratorClass')->invoke($value) : 'ArrayIterator',
        );

        $reflector = $reflector->getMethod('setFlags');
        $reflector->invoke($proto, \ArrayObject::STD_PROP_LIST);

        if ($properties[1] & \ArrayObject::STD_PROP_LIST) {
            $reflector->invoke($value, 0);
            $properties[0] = (array) $value;
        } else {
            $reflector->invoke($value, \ArrayObject::STD_PROP_LIST);
            $arrayValue = (array) $value;
        }
        $reflector->invoke($value, $properties[1]);

        if (array(array(), 0, 'ArrayIterator') === $properties) {
            $properties = array();
        } else {
            if ('ArrayIterator' === $properties[2]) {
                unset($properties[2]);
            }
            $properties = array($reflector->class => array("\0" => $properties));
        }

        return $properties;
    }
}
