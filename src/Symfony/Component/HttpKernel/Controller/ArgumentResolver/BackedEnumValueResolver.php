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

use Symfony\Component\ArgumentResolver\Exception\InvalidRawValueException;
use Symfony\Component\ArgumentResolver\ValueResolver\Traits\BackedEnumValueResolverTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attempt to resolve backed enum cases from request attributes, for a route path parameter,
 * leading to a 404 Not Found if the attribute value isn't a valid backing value for the enum type.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @deprecated
 */
final class BackedEnumValueResolver implements ValueResolverInterface
{
    use BackedEnumValueResolverTrait;

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // do not support if no value can be resolved at all
        // letting the \Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver be used
        // or \Symfony\Component\HttpKernel\Controller\ArgumentResolver fail with a meaningful error.
        if (!$request->attributes->has($argument->getName())) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());

        try {
            return $this->doResolve($argument, [$value]);
        } catch (InvalidRawValueException $e) {
            throw new NotFoundHttpException(\sprintf('Could not resolve the "%s $%s" controller argument: ', $argument->getType(), $argument->getName()).$e->getMessage(), $e);
        }
    }
}
