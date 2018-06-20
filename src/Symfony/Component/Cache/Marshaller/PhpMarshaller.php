<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Marshaller\PhpMarshaller\Configurator;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Reference;
use Symfony\Component\Cache\Marshaller\PhpMarshaller\Registry;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * PhpMarshaller allows serializing PHP data structures using var_export()
 * while preserving all the semantics associated to serialize().
 *
 * By leveraging OPcache, the generated PHP code is faster than doing the same with unserialize().
 *
 * @internal
 */
class PhpMarshaller
{
    public static function marshall($value, int &$objectsCount)
    {
        if (!\is_object($value) && !\is_array($value)) {
            return $value;
        }
        $objectsPool = new \SplObjectStorage();
        $value = array($value);
        $objectsCount = self::doMarshall($value, $objectsPool);

        $classes = array();
        $values = array();
        $wakeups = array();
        foreach ($objectsPool as $i => $v) {
            list(, $classes[], $values[], $wakeup) = $objectsPool[$v];
            if ($wakeup) {
                $wakeups[$wakeup] = $i;
            }
        }
        ksort($wakeups);
        $properties = array();
        foreach ($values as $i => $vars) {
            foreach ($vars as $class => $values) {
                foreach ($values as $name => $v) {
                    $properties[$class][$name][$i] = $v;
                }
            }
        }
        if (!$classes) {
            return $value[0];
        }

        return new Configurator(new Registry($classes), $properties, $value[0], $wakeups);
    }

    public static function optimize(string $exportedValue)
    {
        return preg_replace(sprintf("{%s::__set_state\(array\(\s++'0' => (\d+),\s++\)\)}", preg_quote(Reference::class)), Registry::class.'::$objects[$1]', $exportedValue);
    }

    private static function doMarshall(array &$array, \SplObjectStorage $objectsPool): int
    {
        $objectsCount = 0;

        foreach ($array as &$value) {
            if (\is_array($value) && $value) {
                $objectsCount += self::doMarshall($value, $objectsPool);
            }
            if (!\is_object($value)) {
                continue;
            }
            if (isset($objectsPool[$value])) {
                ++$objectsCount;
                $value = new Reference($objectsPool[$value][0]);
                continue;
            }
            $class = \get_class($value);
            $properties = array();
            $sleep = null;
            $arrayValue = (array) $value;
            $proto = (Registry::$reflectors[$class] ?? Registry::getClassReflector($class))->newInstanceWithoutConstructor();

            if ($value instanceof \ArrayIterator || $value instanceof \ArrayObject) {
                // ArrayIterator and ArrayObject need special care because their "flags"
                // option changes the behavior of the (array) casting operator.
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
            } elseif ($value instanceof \SplObjectStorage) {
                foreach (clone $value as $v) {
                    $properties[] = $v;
                    $properties[] = $value[$v];
                }
                $properties = array('SplObjectStorage' => array("\0" => $properties));
            } elseif ($value instanceof \Serializable) {
                ++$objectsCount;
                $objectsPool[$value] = array($id = \count($objectsPool), serialize($value), array(), 0);
                $value = new Reference($id);
                continue;
            }

            if (\method_exists($class, '__sleep')) {
                if (!\is_array($sleep = $value->__sleep())) {
                    trigger_error('serialize(): __sleep should return an array only containing the names of instance-variables to serialize', E_USER_NOTICE);
                    $value = null;
                    continue;
                }
                $sleep = array_flip($sleep);
            }

            $proto = (array) $proto;

            foreach ($arrayValue as $name => $v) {
                $k = (string) $name;
                if ('' === $k || "\0" !== $k[0]) {
                    $c = $class;
                } elseif ('*' === $k[1]) {
                    $c = $class;
                    $k = substr($k, 3);
                } else {
                    $i = strpos($k, "\0", 2);
                    $c = substr($k, 1, $i - 1);
                    $k = substr($k, 1 + $i);
                }
                if (null === $sleep) {
                    $properties[$c][$k] = $v;
                } elseif (isset($sleep[$k]) && $c === $class) {
                    $properties[$c][$k] = $v;
                    unset($sleep[$k]);
                }
                if (\array_key_exists($name, $proto) && $proto[$name] === $v) {
                    unset($properties[$c][$k]);
                }
            }
            if ($sleep) {
                foreach ($sleep as $k => $v) {
                    trigger_error(sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $k), E_USER_NOTICE);
                }
            }

            $objectsPool[$value] = array($id = \count($objectsPool));
            $objectsCount += 1 + self::doMarshall($properties, $objectsPool);
            $objectsPool[$value] = array($id, $class, $properties, \method_exists($class, '__wakeup') ? $objectsCount : 0);

            $value = new Reference($id);
        }

        return $objectsCount;
    }
}
