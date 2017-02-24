<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\LazyProxy;

use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class InheritanceProxyHelper
{
    public static function getGetterReflector(\ReflectionClass $class, $name, $id)
    {
        if (!$class->hasMethod($name)) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" does not exist.', $id, $class->name, $name));
        }
        $r = $class->getMethod($name);
        if ($r->isPrivate()) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" must be public or protected.', $id, $class->name, $r->name));
        }
        if ($r->isStatic()) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" cannot be static.', $id, $class->name, $r->name));
        }
        if ($r->isFinal()) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" cannot be marked as final.', $id, $class->name, $r->name));
        }
        if ($r->returnsReference()) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" cannot return by reference.', $id, $class->name, $r->name));
        }
        if (0 < $r->getNumberOfParameters()) {
            throw new RuntimeException(sprintf('Unable to configure getter injection for service "%s": method "%s::%s" cannot have any arguments.', $id, $class->name, $r->name));
        }

        return $r;
    }

    /**
     * @return string The signature of the passed function, return type and function/method name included if any
     */
    public static function getSignature(\ReflectionFunctionAbstract $r, &$call = null)
    {
        $signature = array();
        $call = array();

        foreach ($r->getParameters() as $i => $p) {
            $k = '$'.$p->name;
            if (method_exists($p, 'isVariadic') && $p->isVariadic()) {
                $k = '...'.$k;
            }
            $call[] = $k;

            if ($p->isPassedByReference()) {
                $k = '&'.$k;
            }
            if (method_exists($p, 'getType')) {
                $type = $p->getType();
            } elseif (preg_match('/^(?:[^ ]++ ){4}([a-zA-Z_\x7F-\xFF][^ ]++)/', $p, $type)) {
                $type = $type[1];
            }
            if ($type && $type = self::getTypeHint($type, $r)) {
                $k = $type.' '.$k;
            }
            if ($type && $p->allowsNull()) {
                $k = '?'.$k;
            }

            try {
                $k .= ' = '.self::export($p->getDefaultValue());
                if ($type && $p->allowsNull() && null === $p->getDefaultValue()) {
                    $k = substr($k, 1);
                }
            } catch (\ReflectionException $e) {
                if ($type && $p->allowsNull() && !class_exists('ReflectionNamedType', false)) {
                    $k .= ' = null';
                    $k = substr($k, 1);
                }
            }

            $signature[] = $k;
        }
        $call = ($r->isClosure() ? '' : $r->name).'('.implode(', ', $call).')';

        if ($type = method_exists($r, 'getReturnType') ? $r->getReturnType() : null) {
            $type = ': '.($type->allowsNull() ? '?' : '').self::getTypeHint($type, $r);
        }

        return ($r->returnsReference() ? '&' : '').($r->isClosure() ? '' : $r->name).'('.implode(', ', $signature).')'.$type;
    }

    /**
     * @param $type \ReflectionType|string $type As returned by ReflectionParameter::getType() - or string on PHP 5
     *
     * @return string|null The FQCN or builtin name of the type hint, or null when the type hint references an invalid self|parent context
     */
    public static function getTypeHint($type, \ReflectionFunctionAbstract $r)
    {
        if (is_string($type)) {
            $name = $type;

            if ('callable' === $name || 'array' === $name) {
                return $name;
            }
        } else {
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : $type->__toString();

            if ($type->isBuiltin()) {
                return $name;
            }
        }
        $lcName = strtolower($name);

        if ('self' !== $lcName && 'parent' !== $lcName) {
            return '\\'.$name;
        }
        if (!$r instanceof \ReflectionMethod) {
            return;
        }
        if ('self' === $lcName) {
            return '\\'.$r->getDeclaringClass()->name;
        }
        if ($parent = $r->getDeclaringClass()->getParentClass()) {
            return '\\'.$parent->name;
        }
    }

    private static function export($value)
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }
        $code = array();
        foreach ($value as $k => $v) {
            $code[] = sprintf('%s => %s', var_export($k, true), self::export($v));
        }

        return sprintf('array(%s)', implode(', ', $code));
    }
}
