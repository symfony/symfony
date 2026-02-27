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
use Symfony\Component\Uid\AbstractUid;

/**
 * Resolves an AbstractUid instance from a Command argument or option.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class UidValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if ($argument = Argument::tryFrom($member->getMember())) {
            if (!is_subclass_of($argument->typeName, AbstractUid::class)) {
                return [];
            }

            return [$this->resolveArgument($argument, $input)];
        }

        if ($option = Option::tryFrom($member->getMember())) {
            if (!is_subclass_of($option->typeName, AbstractUid::class)) {
                return [];
            }

            return [$this->resolveOption($option, $input)];
        }

        return [];
    }

    private function resolveArgument(Argument $argument, InputInterface $input): ?AbstractUid
    {
        $value = $input->getArgument($argument->name);

        if (null === $value) {
            return null;
        }

        if ($value instanceof $argument->typeName) {
            return $value;
        }

        if (!\is_string($value) || !$argument->typeName::isValid($value)) {
            throw new InvalidArgumentException(\sprintf('The uid for the "%s" argument is invalid.', $argument->name));
        }

        return $argument->typeName::fromString($value);
    }

    private function resolveOption(Option $option, InputInterface $input): ?AbstractUid
    {
        $value = $input->getOption($option->name);

        if (null === $value) {
            return null;
        }

        if ($value instanceof $option->typeName) {
            return $value;
        }

        if (!\is_string($value) || !$option->typeName::isValid($value)) {
            throw new InvalidOptionException(\sprintf('The uid for the "--%s" option is invalid.', $option->name));
        }

        return $option->typeName::fromString($value);
    }
}
