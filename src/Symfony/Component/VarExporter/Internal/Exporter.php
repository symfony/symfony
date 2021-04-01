<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

use Symfony\Component\VarExporter\Exception\NotInstantiableTypeException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Exporter
{
    /**
     * Prepares an array of values for VarExporter.
     *
     * For performance this method is public and has no type-hints.
     *
     * @param array             &$values
     * @param \SplObjectStorage $objectsPool
     * @param array             &$refsPool
     * @param int               &$objectsCount
     * @param bool              &$valuesAreStatic
     *
     * @throws NotInstantiableTypeException When a value cannot be serialized
     */
    public static function prepare($values, $objectsPool, &$refsPool, &$objectsCount, &$valuesAreStatic): array
    {
        $refs = $values;
        foreach ($values as $k => $value) {
            if (\is_resource($value)) {
                throw new NotInstantiableTypeException(get_resource_type($value).' resource');
            }
            $refs[$k] = $objectsPool;

            if ($isRef = !$valueIsStatic = $values[$k] !== $objectsPool) {
                $values[$k] = &$value; // Break hard references to make $values completely
                unset($value);         // independent from the original structure
                $refs[$k] = $value = $values[$k];
                if ($value instanceof Reference && 0 > $value->id) {
                    $valuesAreStatic = false;
                    ++$value->count;
                    continue;
                }
                $refsPool[] = [&$refs[$k], $value, &$value];
                $refs[$k] = $values[$k] = new Reference(-\count($refsPool), $value);
            }

            if (\is_array($value)) {
                if ($value) {
                    $value = self::prepare($value, $objectsPool, $refsPool, $objectsCount, $valueIsStatic);
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
            $reflector = Registry::$reflectors[$class] ?? Registry::getClassReflector($class);

            if ($reflector->hasMethod('__serialize')) {
                if (!$reflector->getMethod('__serialize')->isPublic()) {
                    throw new \Error(sprintf('Call to %s method "%s::__serialize()".', $reflector->getMethod('__serialize')->isProtected() ? 'protected' : 'private', $class));
                }

                if (!\is_array($properties = $value->__serialize())) {
                    throw new \TypeError($class.'::__serialize() must return an array');
                }

                goto prepare_value;
            }

            $properties = [];
            $sleep = null;
            $arrayValue = (array) $value;
            $proto = Registry::$prototypes[$class];

            if (($value instanceof \ArrayIterator || $value instanceof \ArrayObject) && null !== $proto) {
                // ArrayIterator and ArrayObject need special care because their "flags"
                // option changes the behavior of the (array) casting operator.
                $properties = self::getArrayObjectProperties($value, $arrayValue, $proto);

                // populates Registry::$prototypes[$class] with a new instance
                Registry::getClassReflector($class, Registry::$instantiableWithoutConstructor[$class], Registry::$cloneable[$class]);
            } elseif ($value instanceof \SplObjectStorage && Registry::$cloneable[$class] && null !== $proto) {
                // By implementing Serializable, SplObjectStorage breaks
                // internal references; let's deal with it on our own.
                foreach (clone $value as $v) {
                    $properties[] = $v;
                    $properties[] = $value[$v];
                }
                $properties = ['SplObjectStorage' => ["\0" => $properties]];
            } elseif ($value instanceof \Serializable || $value instanceof \__PHP_Incomplete_Class) {
                ++$objectsCount;
                $objectsPool[$value] = [$id = \count($objectsPool), serialize($value), [], 0];
                $value = new Reference($id);
                goto handle_value;
            }

            if (method_exists($class, '__sleep')) {
                if (!\is_array($sleep = $value->__sleep())) {
                    trigger_error('serialize(): __sleep should return an array only containing the names of instance-variables to serialize', \E_USER_NOTICE);
                    $value = null;
                    goto handle_value;
                }
                foreach ($sleep as $name) {
                    if (property_exists($value, $name) && !$reflector->hasProperty($name)) {
                        $arrayValue[$name] = $value->$name;
                    }
                }
                $sleep = array_flip($sleep);
            }

            $proto = (array) $proto;

            foreach ($arrayValue as $name => $v) {
                $i = 0;
                $n = (string) $name;
                if ('' === $n || "\0" !== $n[0]) {
                    $c = 'stdClass';
                } elseif ('*' === $n[1]) {
                    $n = substr($n, 3);
                    $c = $reflector->getProperty($n)->class;
                    if ('Error' === $c) {
                        $c = 'TypeError';
                    } elseif ('Exception' === $c) {
                        $c = 'ErrorException';
                    }
                } else {
                    $i = strpos($n, "\0", 2);
                    $c = substr($n, 1, $i - 1);
                    $n = substr($n, 1 + $i);
                }
                if (null !== $sleep) {
                    if (!isset($sleep[$n]) || ($i && $c !== $class)) {
                        continue;
                    }
                    $sleep[$n] = false;
                }
                if (!\array_key_exists($name, $proto) || $proto[$name] !== $v || "\x00Error\x00trace" === $name || "\x00Exception\x00trace" === $name) {
                    $properties[$c][$n] = $v;
                }
            }
            if ($sleep) {
                foreach ($sleep as $n => $v) {
                    if (false !== $v) {
                        trigger_error(sprintf('serialize(): "%s" returned as member variable from __sleep() but does not exist', $n), \E_USER_NOTICE);
                    }
                }
            }

            prepare_value:
            $objectsPool[$value] = [$id = \count($objectsPool)];
            $properties = self::prepare($properties, $objectsPool, $refsPool, $objectsCount, $valueIsStatic);
            ++$objectsCount;
            $objectsPool[$value] = [$id, $class, $properties, method_exists($class, '__unserialize') ? -$objectsCount : (method_exists($class, '__wakeup') ? $objectsCount : 0)];

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

    public static function export($value, string $indent = '')
    {
        switch (true) {
            case \is_int($value) || \is_float($value): return var_export($value, true);
            case [] === $value: return '[]';
            case false === $value: return 'false';
            case true === $value: return 'true';
            case null === $value: return 'null';
            case '' === $value: return "''";
        }

        if ($value instanceof Reference) {
            if (0 <= $value->id) {
                return '$o['.$value->id.']';
            }
            if (!$value->count) {
                return self::export($value->value, $indent);
            }
            $value = -$value->id;

            return '&$r['.$value.']';
        }
        $subIndent = $indent.'    ';

        if (\is_string($value)) {
            $code = sprintf("'%s'", addcslashes($value, "'\\"));

            $code = preg_replace_callback('/([\0\r\n]++)(.)/', function ($m) use ($subIndent) {
                $m[1] = sprintf('\'."%s".\'', str_replace(
                    ["\0", "\r", "\n", '\n\\'],
                    ['\0', '\r', '\n', '\n"'."\n".$subIndent.'."\\'],
                    $m[1]
                ));

                if ("'" === $m[2]) {
                    return substr($m[1], 0, -2);
                }

                if ('n".\'' === substr($m[1], -4)) {
                    return substr_replace($m[1], "\n".$subIndent.".'".$m[2], -2);
                }

                return $m[1].$m[2];
            }, $code, -1, $count);

            if ($count && 0 === strpos($code, "''.")) {
                $code = substr($code, 3);
            }

            return $code;
        }

        if (\is_array($value)) {
            $j = -1;
            $code = '';
            foreach ($value as $k => $v) {
                $code .= $subIndent;
                if (!\is_int($k) || 1 !== $k - $j) {
                    $code .= self::export($k, $subIndent).' => ';
                }
                if (\is_int($k) && $k > $j) {
                    $j = $k;
                }
                $code .= self::export($v, $subIndent).",\n";
            }

            return "[\n".$code.$indent.']';
        }

        if ($value instanceof Values) {
            $code = $subIndent."\$r = [],\n";
            foreach ($value->values as $k => $v) {
                $code .= $subIndent.'$r['.$k.'] = '.self::export($v, $subIndent).",\n";
            }

            return "[\n".$code.$indent.']';
        }

        if ($value instanceof Registry) {
            return self::exportRegistry($value, $indent, $subIndent);
        }

        if ($value instanceof Hydrator) {
            return self::exportHydrator($value, $indent, $subIndent);
        }

        throw new \UnexpectedValueException(sprintf('Cannot export value of type "%s".', get_debug_type($value)));
    }

    private static function exportRegistry(Registry $value, string $indent, string $subIndent): string
    {
        $code = '';
        $serializables = [];
        $seen = [];
        $prototypesAccess = 0;
        $factoriesAccess = 0;
        $r = '\\'.Registry::class;
        $j = -1;

        foreach ($value as $k => $class) {
            if (':' === ($class[1] ?? null)) {
                $serializables[$k] = $class;
                continue;
            }
            if (!Registry::$instantiableWithoutConstructor[$class]) {
                if (is_subclass_of($class, 'Serializable') && !method_exists($class, '__unserialize')) {
                    $serializables[$k] = 'C:'.\strlen($class).':"'.$class.'":0:{}';
                } else {
                    $serializables[$k] = 'O:'.\strlen($class).':"'.$class.'":0:{}';
                }
                if (is_subclass_of($class, 'Throwable')) {
                    $eol = is_subclass_of($class, 'Error') ? "\0Error\0" : "\0Exception\0";
                    $serializables[$k] = substr_replace($serializables[$k], '1:{s:'.(5 + \strlen($eol)).':"'.$eol.'trace";a:0:{}}', -4);
                }
                continue;
            }
            $code .= $subIndent.(1 !== $k - $j ? $k.' => ' : '');
            $j = $k;
            $eol = ",\n";
            $c = '['.self::export($class).']';

            if ($seen[$class] ?? false) {
                if (Registry::$cloneable[$class]) {
                    ++$prototypesAccess;
                    $code .= 'clone $p'.$c;
                } else {
                    ++$factoriesAccess;
                    $code .= '$f'.$c.'()';
                }
            } else {
                $seen[$class] = true;
                if (Registry::$cloneable[$class]) {
                    $code .= 'clone ('.($prototypesAccess++ ? '$p' : '($p = &'.$r.'::$prototypes)').$c.' ?? '.$r.'::p';
                } else {
                    $code .= '('.($factoriesAccess++ ? '$f' : '($f = &'.$r.'::$factories)').$c.' ?? '.$r.'::f';
                    $eol = '()'.$eol;
                }
                $code .= '('.substr($c, 1, -1).'))';
            }
            $code .= $eol;
        }

        if (1 === $prototypesAccess) {
            $code = str_replace('($p = &'.$r.'::$prototypes)', $r.'::$prototypes', $code);
        }
        if (1 === $factoriesAccess) {
            $code = str_replace('($f = &'.$r.'::$factories)', $r.'::$factories', $code);
        }
        if ('' !== $code) {
            $code = "\n".$code.$indent;
        }

        if ($serializables) {
            $code = $r.'::unserialize(['.$code.'], '.self::export($serializables, $indent).')';
        } else {
            $code = '['.$code.']';
        }

        return '$o = '.$code;
    }

    private static function exportHydrator(Hydrator $value, string $indent, string $subIndent): string
    {
        $code = '';
        foreach ($value->properties as $class => $properties) {
            $code .= $subIndent.'    '.self::export($class).' => '.self::export($properties, $subIndent.'    ').",\n";
        }

        $code = [
            self::export($value->registry, $subIndent),
            self::export($value->values, $subIndent),
            '' !== $code ? "[\n".$code.$subIndent.']' : '[]',
            self::export($value->value, $subIndent),
            self::export($value->wakeups, $subIndent),
        ];

        return '\\'.\get_class($value)."::hydrate(\n".$subIndent.implode(",\n".$subIndent, $code)."\n".$indent.')';
    }

    /**
     * @param \ArrayIterator|\ArrayObject $value
     * @param \ArrayIterator|\ArrayObject $proto
     */
    private static function getArrayObjectProperties($value, array &$arrayValue, $proto): array
    {
        $reflector = $value instanceof \ArrayIterator ? 'ArrayIterator' : 'ArrayObject';
        $reflector = Registry::$reflectors[$reflector] ?? Registry::getClassReflector($reflector);

        $properties = [
            $arrayValue,
            $reflector->getMethod('getFlags')->invoke($value),
            $value instanceof \ArrayObject ? $reflector->getMethod('getIteratorClass')->invoke($value) : 'ArrayIterator',
        ];

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

        if ([[], 0, 'ArrayIterator'] === $properties) {
            $properties = [];
        } else {
            if ('ArrayIterator' === $properties[2]) {
                unset($properties[2]);
            }
            $properties = [$reflector->class => ["\0" => $properties]];
        }

        return $properties;
    }
}
