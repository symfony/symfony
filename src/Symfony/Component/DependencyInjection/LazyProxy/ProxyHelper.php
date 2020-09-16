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
    public static function getTypeHint(\ReflectionFunctionAbstract $r, \ReflectionParameter $p = null, bool $noBuiltin = false): ?string
    {
        if ($p instanceof \ReflectionParameter) {
            $type = $p->getType();
        } else {
            $type = $r->getReturnType();
        }
        if (!$type) {
            return null;
        }

        $types = [];

        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $type) {
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

            if ($type->isBuiltin()) {
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
