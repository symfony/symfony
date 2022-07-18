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

        return self::getTypeHintForType($type, $r, $noBuiltin);
    }

    private static function getTypeHintForType(\ReflectionType $type, \ReflectionFunctionAbstract $r, bool $noBuiltin): ?string
    {
        $types = [];
        $glue = '|';
        if ($type instanceof \ReflectionUnionType) {
            $reflectionTypes = $type->getTypes();
        } elseif ($type instanceof \ReflectionIntersectionType) {
            $reflectionTypes = $type->getTypes();
            $glue = '&';
        } elseif ($type instanceof \ReflectionNamedType) {
            $reflectionTypes = [$type];
        } else {
            return null;
        }

        foreach ($reflectionTypes as $type) {
            if ($type instanceof \ReflectionIntersectionType) {
                $typeHint = self::getTypeHintForType($type, $r, $noBuiltin);
                if (null === $typeHint) {
                    return null;
                }

                $types[] = sprintf('(%s)', $typeHint);

                continue;
            }

            if ($type->isBuiltin()) {
                if (!$noBuiltin) {
                    $types[] = $type->getName();
                }
                continue;
            }

            $lcName = strtolower($type->getName());
            $prefix = $noBuiltin ? '' : '\\';

            if ('self' !== $lcName && 'parent' !== $lcName) {
                $types[] = $prefix.$type->getName();
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

        return $types ? implode($glue, $types) : null;
    }
}
