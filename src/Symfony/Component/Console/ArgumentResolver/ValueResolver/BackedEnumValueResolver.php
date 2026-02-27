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
            if (!is_subclass_of($argument->typeName, \BackedEnum::class)) {
                return [];
            }

            return [$this->resolveArgument($argument, $input)];
        }

        if ($option = Option::tryFrom($member->getMember())) {
            if (!is_subclass_of($option->typeName, \BackedEnum::class)) {
                return [];
            }

            return [$this->resolveOption($option, $input)];
        }

        return [];
    }

    private function resolveArgument(Argument $argument, InputInterface $input): ?\BackedEnum
    {
        $value = $input->getArgument($argument->name);

        if (null === $value) {
            return null;
        }

        if ($value instanceof $argument->typeName) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidArgumentException::fromEnumValue($argument->name, get_debug_type($value), $argument->suggestedValues);
        }

        return $argument->typeName::tryFrom($value)
            ?? throw InvalidArgumentException::fromEnumValue($argument->name, $value, $argument->suggestedValues);
    }

    private function resolveOption(Option $option, InputInterface $input): ?\BackedEnum
    {
        $value = $input->getOption($option->name);

        if (null === $value) {
            return null;
        }

        if ($value instanceof $option->typeName) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidOptionException::fromEnumValue($option->name, get_debug_type($value), $option->suggestedValues);
        }

        return $option->typeName::tryFrom($value)
            ?? throw InvalidOptionException::fromEnumValue($option->name, $value, $option->suggestedValues);
    }
}
