<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;

/**
 * Yields a non-variadic argument's value from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RequestAttributeValueResolver implements ControllerValueResolverInterface
{
    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        return !$argument->isVariadic() && $request->attributes->has($argument->getName()) ? [$request->attributes->get($argument->getName())] : [];
    }
}
