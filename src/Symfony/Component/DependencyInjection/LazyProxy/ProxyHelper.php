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
            $name = $type->getName();

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
