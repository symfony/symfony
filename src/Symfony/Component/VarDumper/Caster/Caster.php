<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Helper for filtering out properties in casters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Caster
{
    const EXCLUDE_VERBOSE = 1;
    const EXCLUDE_VIRTUAL = 2;
    const EXCLUDE_DYNAMIC = 4;
    const EXCLUDE_PUBLIC = 8;
    const EXCLUDE_PROTECTED = 16;
    const EXCLUDE_PRIVATE = 32;
    const EXCLUDE_NULL = 64;
    const EXCLUDE_EMPTY = 128;
    const EXCLUDE_NOT_IMPORTANT = 256;
    const EXCLUDE_STRICT = 512;

    const PREFIX_VIRTUAL = "\0~\0";
    const PREFIX_DYNAMIC = "\0+\0";
    const PREFIX_PROTECTED = "\0*\0";

    /**
     * Casts objects to arrays and adds the dynamic property prefix.
     *
     * @param object           $obj       The object to cast
     * @param \ReflectionClass $reflector The class reflector to use for inspecting the object definition
     *
     * @return array The array-cast of the object, with prefixed dynamic properties
     */
    public static function castObject($obj, \ReflectionClass $reflector)
    {
        if ($reflector->hasMethod('__debugInfo')) {
            $a = $obj->__debugInfo();
        } elseif ($obj instanceof \Closure) {
            $a = array();
        } else {
            $a = (array) $obj;
        }

        if ($a) {
            $p = array_keys($a);
            foreach ($p as $i => $k) {
                if (isset($k[0]) && "\0" !== $k[0] && !$reflector->hasProperty($k)) {
                    $p[$i] = self::PREFIX_DYNAMIC.$k;
                } elseif (isset($k[16]) && "\0" === $k[16] && 0 === strpos($k, "\0class@anonymous\0")) {
                    $p[$i] = "\0".$reflector->getParentClass().'@anonymous'.strrchr($k, "\0");
                }
            }
            $a = array_combine($p, $a);
        }

        return $a;
    }

    /**
     * Filters out the specified properties.
     *
     * By default, a single match in the $filter bit field filters properties out, following an "or" logic.
     * When EXCLUDE_STRICT is set, an "and" logic is applied: all bits must match for a property to be removed.
     *
     * @param array    $a                The array containing the properties to filter
     * @param int      $filter           A bit field of Caster::EXCLUDE_* constants specifying which properties to filter out
     * @param string[] $listedProperties List of properties to exclude when Caster::EXCLUDE_VERBOSE is set, and to preserve when Caster::EXCLUDE_NOT_IMPORTANT is set
     *
     * @return array The filtered array
     */
    public static function filter(array $a, $filter, array $listedProperties = array())
    {
        foreach ($a as $k => $v) {
            $type = self::EXCLUDE_STRICT & $filter;

            if (null === $v) {
                $type |= self::EXCLUDE_NULL & $filter;
            }
            if (empty($v)) {
                $type |= self::EXCLUDE_EMPTY & $filter;
            }
            if ((self::EXCLUDE_NOT_IMPORTANT & $filter) && !in_array($k, $listedProperties, true)) {
                $type |= self::EXCLUDE_NOT_IMPORTANT;
            }
            if ((self::EXCLUDE_VERBOSE & $filter) && in_array($k, $listedProperties, true)) {
                $type |= self::EXCLUDE_VERBOSE;
            }

            if (!isset($k[1]) || "\0" !== $k[0]) {
                $type |= self::EXCLUDE_PUBLIC & $filter;
            } elseif ('~' === $k[1]) {
                $type |= self::EXCLUDE_VIRTUAL & $filter;
            } elseif ('+' === $k[1]) {
                $type |= self::EXCLUDE_DYNAMIC & $filter;
            } elseif ('*' === $k[1]) {
                $type |= self::EXCLUDE_PROTECTED & $filter;
            } else {
                $type |= self::EXCLUDE_PRIVATE & $filter;
            }

            if ((self::EXCLUDE_STRICT & $filter) ? $type === $filter : $type) {
                unset($a[$k]);
            }
        }

        return $a;
    }
}
