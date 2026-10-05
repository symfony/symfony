<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver\ValueResolver;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\DocBlockTypeResolver;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves a BackedEnum instance from a Command argument or option.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class BackedEnumValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if ($argument = Argument::tryFrom($member->getMember())) {
            if (null === $class = self::getBackedEnumClass($typeName = $argument->typeName, $member)) {
                return [];
            }

            $value = $input->getArgument($argument->name);
            $resolve = fn ($value) => $this->resolveArgument($argument, $class, $value);
        } elseif ($option = Option::tryFrom($member->getMember())) {
            if (null === $class = self::getBackedEnumClass($typeName = $option->typeName, $member)) {
                return [];
            }

            $value = $input->getOption($option->name);
            $resolve = fn ($value) => $this->resolveOption($option, $class, $value);

            if ('array' === $typeName && $option->allowNull && [] === $value) {
                return [null];
            }
        } else {
            return [];
        }

        if ($member->isVariadic()) {
            return array_map($resolve, (array) $value);
        }

        if ('array' === $typeName) {
            return [null === $value ? null : array_map($resolve, (array) $value)];
        }

        return [$resolve($value)];
    }

    /**
     * Returns the backed enum a member is typed with, or the one its PHPDoc narrows an "array" member to.
     *
     * @return class-string<\BackedEnum>|null
     */
    private static function getBackedEnumClass(string $typeName, ReflectionMember $member): ?string
    {
        if ('array' === $typeName) {
            $typeName = DocBlockTypeResolver::resolveArrayItemClass($member->getMember());
        }

        return null !== $typeName && is_subclass_of($typeName, \BackedEnum::class) ? $typeName : null;
    }

    private function resolveArgument(Argument $argument, string $class, mixed $value): ?\BackedEnum
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof $class) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidArgumentException::fromEnumValue($argument->name, get_debug_type($value), $argument->suggestedValues);
        }

        return $class::tryFrom($value)
            ?? throw InvalidArgumentException::fromEnumValue($argument->name, $value, $argument->suggestedValues);
    }

    private function resolveOption(Option $option, string $class, mixed $value): ?\BackedEnum
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof $class) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidOptionException::fromEnumValue($option->name, get_debug_type($value), $option->suggestedValues);
        }

        return $class::tryFrom($value)
            ?? throw InvalidOptionException::fromEnumValue($option->name, $value, $option->suggestedValues);
    }
}
