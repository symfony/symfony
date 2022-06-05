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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Attempt to resolve backed enum cases from request attributes, for a route path parameter,
 * leading to a 404 Not Found if the attribute value isn't a valid backing value for the enum type.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class BackedEnumValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if (!is_subclass_of($argument->getType(), \BackedEnum::class)) {
            return false;
        }

        if ($argument->isVariadic()) {
            // only target route path parameters, which cannot be variadic.
            return false;
        }

        // do not support if no value can be resolved at all
        // letting the \Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver be used
        // or \Symfony\Component\HttpKernel\Controller\ArgumentResolver fail with a meaningful error.
        return $request->attributes->has($argument->getName());
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $value = $request->attributes->get($argument->getName());

        if (null === $value) {
            yield null;

            return;
        }

        if ($value instanceof \BackedEnum) {
            yield $value;

            return;
        }

        if (!\is_int($value) && !\is_string($value)) {
            throw new \LogicException(sprintf('Could not resolve the "%s $%s" controller argument: expecting an int or string, got %s.', $argument->getType(), $argument->getName(), get_debug_type($value)));
        }

        /** @var class-string<\BackedEnum> $enumType */
        $enumType = $argument->getType();

        try {
            yield $enumType::from($value);
        } catch (\ValueError $error) {
            throw new NotFoundHttpException(sprintf('Could not resolve the "%s $%s" controller argument: %s', $argument->getType(), $argument->getName(), $error->getMessage()), $error);
        }
    }
}
