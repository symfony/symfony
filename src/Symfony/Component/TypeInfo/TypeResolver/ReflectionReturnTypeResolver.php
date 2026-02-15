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

use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Resolves return type for a given function reflection.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class ReflectionReturnTypeResolver implements TypeResolverInterface
{
    public function __construct(
        private readonly ReflectionTypeResolver $reflectionTypeResolver,
        private readonly TypeContextFactory $typeContextFactory,
    ) {
    }

    public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type
    {
        if (!$subject instanceof \ReflectionFunctionAbstract) {
            throw new UnsupportedException(\sprintf('Expected subject to be a "ReflectionFunctionAbstract", "%s" given.', get_debug_type($subject)), $subject);
        }

        $typeContext ??= $this->typeContextFactory->createFromReflection($subject);

        try {
            return $this->reflectionTypeResolver->resolve($subject->getReturnType(), $typeContext);
        } catch (UnsupportedException $e) {
            $path = null !== $typeContext
                ? \sprintf('%s::%s()', $typeContext->calledClassName, $subject->getName())
                : \sprintf('%s()', $subject->getName());

            throw new UnsupportedException(\sprintf('Cannot resolve type for "%s".', $path), $subject, previous: $e);
        }
    }
}
