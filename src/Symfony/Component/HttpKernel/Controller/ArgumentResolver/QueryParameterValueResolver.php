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
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolve arguments of type: array, string, int, float, bool, \BackedEnum from query parameters.
 *
 * @author Ruud Kamphuis <ruud@ticketswap.com>
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Mateusz Anders <anders_mateusz@outlook.com>
 */
final class QueryParameterValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!$attribute = $argument->getAttributesOfType(MapQueryParameter::class)[0] ?? null) {
            return [];
        }

        $name = $attribute->name ?? $argument->getName();
        if (!$request->query->has($name)) {
            if ($argument->isNullable() || $argument->hasDefaultValue()) {
                return [];
            }

            throw new NotFoundHttpException(sprintf('Missing query parameter "%s".', $name));
        }

        $value = $request->query->all()[$name];
        $type = $argument->getType();

        if (null === $attribute->filter && 'array' === $type) {
            if (!$argument->isVariadic()) {
                return [(array) $value];
            }

            $filtered = array_values(array_filter((array) $value, \is_array(...)));

            if ($filtered !== $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
                throw new NotFoundHttpException(sprintf('Invalid query parameter "%s".', $name));
            }

            return $filtered;
        }

        $options = [
            'flags' => $attribute->flags | \FILTER_NULL_ON_FAILURE,
            'options' => $attribute->options,
        ];

        if ('array' === $type || $argument->isVariadic()) {
            $value = (array) $value;
            $options['flags'] |= \FILTER_REQUIRE_ARRAY;
        } else {
            $options['flags'] |= \FILTER_REQUIRE_SCALAR;
        }

        $enumType = null;
        $filter = match ($type) {
            'array' => \FILTER_DEFAULT,
            'string' => \FILTER_DEFAULT,
            'int' => \FILTER_VALIDATE_INT,
            'float' => \FILTER_VALIDATE_FLOAT,
            'bool' => \FILTER_VALIDATE_BOOL,
            default => match ($enumType = is_subclass_of($type, \BackedEnum::class) ? (new \ReflectionEnum($type))->getBackingType()->getName() : null) {
                'int' => \FILTER_VALIDATE_INT,
                'string' => \FILTER_DEFAULT,
                default => throw new \LogicException(sprintf('#[MapQueryParameter] cannot be used on controller argument "%s$%s" of type "%s"; one of array, string, int, float, bool or \BackedEnum should be used.', $argument->isVariadic() ? '...' : '', $argument->getName(), $type ?? 'mixed')),
            }
        };

        $value = filter_var($value, $attribute->filter ?? $filter, $options);

        if (null !== $enumType && null !== $value) {
            $enumFrom = static function ($value) use ($type) {
                if (!\is_string($value) && !\is_int($value)) {
                    return null;
                }

                try {
                    return $type::from($value);
                } catch (\ValueError) {
                    return null;
                }
            };

            $value = \is_array($value) ? array_map($enumFrom, $value) : $enumFrom($value);
        }

        if (null === $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
            throw new NotFoundHttpException(sprintf('Invalid query parameter "%s".', $name));
        }

        if (!\is_array($value)) {
            return [$value];
        }

        $filtered = array_filter($value, static fn ($v) => null !== $v);

        if ($argument->isVariadic()) {
            $filtered = array_values($filtered);
        }

        if ($filtered !== $value && !($attribute->flags & \FILTER_NULL_ON_FAILURE)) {
            throw new NotFoundHttpException(sprintf('Invalid query parameter "%s".', $name));
        }

        return $argument->isVariadic() ? $filtered : [$filtered];
    }
}
