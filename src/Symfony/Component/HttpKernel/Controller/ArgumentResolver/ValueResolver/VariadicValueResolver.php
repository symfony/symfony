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

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidArgumentException;
use Symfony\Component\ArgumentResolver\ValueResolver\Traits\VariadicValueResolverTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Yields a variadic argument's values from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class VariadicValueResolver implements ControllerValueResolverInterface
{
    use VariadicValueResolverTrait;

    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        if (!$request) {
            throw new InvalidArgumentException(sprintf('The "$request" argument must be a "%s" instance, null given.', Request::class));
        }

        if (!$request->attributes->has($argument->getName())) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());

        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('Argument "...$%1$s" is required to be an array, the request attribute "%1$s" contains a type of "%2$s" instead.', $argument->getName(), get_debug_type($value)));
        }

        return $this->doResolve($argument, $value);
    }
}
