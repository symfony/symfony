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
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Yields a non-variadic argument's value from the request attributes.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RequestAttributeValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if ($argument->isVariadic()) {
            return [];
        }

        $name = $argument->getName();
        if (!$request->attributes->has($name)) {
            return [];
        }

        $value = $request->attributes->get($name);

        if (null === $value && $argument->isNullable()) {
            return [null];
        }

        $type = $argument->getType();

        // Skip when no type declaration or complex types; fall back to other resolvers/defaults
        if (null === $type || str_contains($type, '|') || str_contains($type, '&')) {
            return [$value];
        }

        if ('string' === $type) {
            if (!\is_scalar($value) && !$value instanceof \Stringable) {
                throw new NotFoundHttpException(\sprintf('The value for the "%s" route parameter is invalid.', $name));
            }

            $value = (string) $value;
        } elseif ('bool' === $type) {
            if (null === $value = filter_var($value, \FILTER_VALIDATE_BOOL, ['flags' => \FILTER_NULL_ON_FAILURE | \FILTER_REQUIRE_SCALAR])) {
                throw new NotFoundHttpException(\sprintf('The value for the "%s" route parameter is invalid.', $name));
            }
        } elseif (null !== $cast = match ($type) {
            'int' => static fn (int $v): int => $v,
            'float' => static fn (float $v): float => $v,
            default => null,
        }) {
            // Coerce the value as PHP would when passing it to the typed controller
            // argument, so a zero-padded "06" still resolves to 6 while a value that
            // would raise a TypeError (overflow, "abc") becomes a 404.
            try {
                $value = $cast($value);
            } catch (\TypeError) {
                throw new NotFoundHttpException(\sprintf('The value for the "%s" route parameter is invalid.', $name));
            }
        }

        return [$value];
    }
}
