<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\TypeResolver;

use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Resolves type for a given type reflection.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class ReflectionTypeResolver implements TypeResolverInterface
{
    public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type
    {
        if ($subject instanceof \ReflectionUnionType) {
            return Type::union(...array_map(fn (mixed $t): Type => $this->resolve($t, $typeContext), $subject->getTypes()));
        }

        if ($subject instanceof \ReflectionIntersectionType) {
            return Type::intersection(...array_map(fn (mixed $t): Type => $this->resolve($t, $typeContext), $subject->getTypes()));
        }

        if (!$subject instanceof \ReflectionNamedType) {
            throw new UnsupportedException(\sprintf('Expected subject to be a "ReflectionNamedType", a "ReflectionUnionType" or a "ReflectionIntersectionType", "%s" given.', get_debug_type($subject)), $subject);
        }

        $identifier = $subject->getName();
        $nullable = $subject->allowsNull();

        if (TypeIdentifier::ARRAY->value === $identifier) {
            $type = Type::array();

            return $nullable ? Type::nullable($type) : $type;
        }

        if (TypeIdentifier::ITERABLE->value === $identifier) {
            $type = Type::iterable();

            return $nullable ? Type::nullable($type) : $type;
        }

        if (TypeIdentifier::NULL->value === $identifier || TypeIdentifier::MIXED->value === $identifier) {
            return Type::builtin($identifier);
        }

        if ($subject->isBuiltin()) {
            $type = Type::builtin(TypeIdentifier::from($identifier));

            return $nullable ? Type::nullable($type) : $type;
        }

        if (\in_array(strtolower($identifier), ['self', 'static', 'parent'], true) && !$typeContext) {
            throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "%s".', TypeContext::class, strtolower($identifier)));
        }

        /** @var class-string $className */
        $className = match (true) {
            'self' === strtolower($identifier) => $typeContext->getDeclaringClass(),
            'static' === strtolower($identifier) => $typeContext->getCalledClass(),
            'parent' === strtolower($identifier) => $typeContext->getParentClass(),
            default => $identifier,
        };

        if (is_subclass_of($className, \UnitEnum::class)) {
            $type = Type::enum($className);
        } else {
            $type = Type::object($className);
        }

        return $nullable ? Type::nullable($type) : $type;
    }
}
