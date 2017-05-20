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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class ProxyHelper
{
    /**
     * @return string The signature of the passed function, return type and function/method name included if any
     */
    public static function getSignature(\ReflectionFunctionAbstract $r, &$call = null)
    {
        $signature = array();
        $call = array();

        foreach ($r->getParameters() as $i => $p) {
            $k = '$'.$p->name;
            if ($p->isVariadic()) {
                $k = '...'.$k;
            }
            $call[] = $k;

            if ($p->isPassedByReference()) {
                $k = '&'.$k;
            }
            if ($type = self::getTypeHint($r, $p)) {
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

        if ($type = self::getTypeHint($r)) {
            $type = ': '.($r->getReturnType()->allowsNull() ? '?' : '').$type;
        }

        return ($r->returnsReference() ? '&' : '').($r->isClosure() ? '' : $r->name).'('.implode(', ', $signature).')'.$type;
    }

    /**
     * @return string|null The FQCN or builtin name of the type hint, or null when the type hint references an invalid self|parent context
     */
    public static function getTypeHint(\ReflectionFunctionAbstract $r, \ReflectionParameter $p = null, $noBuiltin = false)
    {
        if ($p instanceof \ReflectionParameter) {
            $type = $p->getType();
        } else {
            $type = $r->getReturnType();
        }
        if (!$type) {
            return;
        }
        if (!is_string($type)) {
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : $type->__toString();

            if ($type->isBuiltin()) {
                return $noBuiltin ? null : $name;
            }
        }
        $lcName = strtolower($name);
        $prefix = $noBuiltin ? '' : '\\';

        if ('self' !== $lcName && 'parent' !== $lcName) {
            return $prefix.$name;
        }
        if (!$r instanceof \ReflectionMethod) {
            return;
        }
        if ('self' === $lcName) {
            return $prefix.$r->getDeclaringClass()->name;
        }
        if ($parent = $r->getDeclaringClass()->getParentClass()) {
            return $prefix.$parent->name;
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
