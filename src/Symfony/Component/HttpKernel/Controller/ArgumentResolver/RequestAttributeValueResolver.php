<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Yields a non-variadic argument's value from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RequestAttributeValueResolver implements ArgumentValueResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if ($argument->isVariadic()) {
            return false;
        }

        if (!$request->attributes->has($argument->getName())) {
            return false;
        }

        $type = $argument->getType();
        // for union types or no type we assume it is supported to keep things simple
        if (null === $type || str_contains($type, '|')) {
            return true;
        }

        // at this point we have a typehint which is either a scalar, a class or an intersection type (which must be a class too)
        // if the type is not a scalar type and the value is not an object we should skip here and let other value resolvers do their job
        if (!in_array($type, ['string', 'int', 'float', 'bool'], true) && gettype($request->attributes->get($argument->getName())) !== 'object') {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $request->attributes->get($argument->getName());
    }
}
