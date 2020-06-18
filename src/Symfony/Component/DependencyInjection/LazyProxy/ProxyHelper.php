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
            if (method_exists($p, 'getType')) {
                $type = $p->getType();
            } elseif (preg_match('/^(?:[^ ]++ ){4}([a-zA-Z_\x7F-\xFF][^ ]++)/', $p, $type)) {
                $name = $type = $type[1];

                if ('callable' === $name || 'array' === $name) {
                    return $noBuiltin ? null : $name;
                }
            }
        } else {
            $type = method_exists($r, 'getReturnType') ? $r->getReturnType() : null;
        }
        if (!$type) {
            return null;
        }

        $types = [];

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $type) {
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

            if (!\is_string($type) && $type->isBuiltin()) {
                if (!$noBuiltin) {
                    $types[] = $name;
                }
                continue;
            }

            $lcName = strtolower($name);
            $prefix = $noBuiltin ? '' : '\\';

            if ('self' !== $lcName && 'parent' !== $lcName) {
                $types[] = '' !== $prefix ? $prefix.$name : $name;
                continue;
            }
            if (!$r instanceof \ReflectionMethod) {
                continue;
            }
            if ('self' === $lcName) {
                $types[] = $prefix.$r->getDeclaringClass()->name;
            } else {
                $types[] = ($parent = $r->getDeclaringClass()->getParentClass()) ? $prefix.$parent->name : null;
            }
        }

        return $types ? implode('|', $types) : null;
    }
}
