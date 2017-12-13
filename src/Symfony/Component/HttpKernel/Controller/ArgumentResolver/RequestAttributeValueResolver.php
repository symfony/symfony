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
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        return !$argument->isVariadic() && $request->attributes->has($argument->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        yield $this->resolveByType($request, $argument);
    }

    private function resolveByType(Request $request, ArgumentMetadata $argument)
    {
        switch ($argument->getType()) {
            case 'float':
                return (float) $request->attributes->get($argument->getName());
            case 'int':
                return $request->attributes->getInt($argument->getName());
            case 'bool':
                return $request->attributes->getBoolean($argument->getName());
            default:
                return $request->attributes->get($argument->getName());
        }
    }
}
