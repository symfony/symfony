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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class PostValueResolver implements ArgumentValueResolverInterface
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return [] !== $argument->getAttributesOfType(ResolvePostValue::class);
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        /**
         * @psalm-ignore-var
         *
         * @var list<ResolvePostValue> $resolveRequestValues
         */
        $resolveRequestValues = $argument->getAttributesOfType(ResolvePostValue::class);
        if ([] === $resolveRequestValues) {
            throw new \LogicException(sprintf('Argument does not have a "%s" attribute.', ResolvePostValue::class));
        }
        $resolveRequestValue = $resolveRequestValues[0];
        $key = $resolveRequestValue->name ?? $argument->getName();
        /** @var mixed $default */
        $default = $resolveRequestValue->default ?? ($argument->hasDefaultValue() ? $argument->getDefaultValue() : null);
        /** @psalm-suppress MixedArgument */
        $value = $request->request->get($key, $default);
        if (null === $value) {
            if ($argument->isNullable()) {
                return [null];
            } else {
                throw new BadRequestHttpException(sprintf('Request param "%s" does not exist.', $key));
            }
        }
        $coercedValue = match ($argument->getType()) {
            'bool' => filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE),
            'int' => filter_var($value, \FILTER_VALIDATE_INT, \FILTER_NULL_ON_FAILURE),
            'float' => filter_var($value, \FILTER_VALIDATE_FLOAT, \FILTER_NULL_ON_FAILURE),
            default => $value,
        };
        if (null === $coercedValue) {
            throw new BadRequestHttpException(sprintf('Request param "%s" could not be coerced to a "%s".', $key, $argument->getType()));
        }

        return [$coercedValue];
    }
}
