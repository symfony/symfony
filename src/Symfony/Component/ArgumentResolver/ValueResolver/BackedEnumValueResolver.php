<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver;

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\InvalidSourceValueException;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\ArgumentResolver\ValueAccessor\RawValueAccessorInterface;

/**
 * Attempt to resolve backed enum cases from request attributes, for a route path parameter,
 * leading to a 404 Not Found if the attribute value isn't a valid backing value for the enum type.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
final readonly class BackedEnumValueResolver implements ValueResolverInterface
{
    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        if (!is_subclass_of($argument->getType(), \BackedEnum::class)) {
            return [];
        }

        $value = $value->get();

        if ($argument->isVariadic() || SourceValue::NOT_FOUND === $value) {
            // not supported
            return [];
        }

        if (null === $value) {
            return [null];
        }

        if ($value instanceof \BackedEnum) {
            return [$value];
        }

        if (!\is_int($value) && !\is_string($value)) {
            throw new \LogicException(\sprintf('Could not resolve the "%s $%s" argument: expecting an int or string, got "%s".', $argument->getType(), $argument->getName(), get_debug_type($value)));
        }

        /** @var class-string<\BackedEnum> $enumType */
        $enumType = $argument->getType();

        try {
            return [$enumType::from($value)];
        } catch (\ValueError|\TypeError $e) {
            throw new InvalidSourceValueException(\sprintf('Could not resolve the "%s $%s" argument: ', $argument->getType(), $argument->getName()).$e->getMessage(), $e->getCode(), $e);
        }
    }
}
