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
use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves values from #[Argument] or #[Option] attributes for built-in PHP types.
 *
 * Handles: string, bool, int, float, array
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class BuiltinTypeValueResolver implements ValueResolverInterface
{
    private const BUILTIN_TYPES = ['string', 'bool', 'int', 'float', 'array'];

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if ($member->isVariadic()) {
            return [];
        }

        if ($argument = Argument::tryFrom($member->getMember())) {
            if (!\in_array($argument->typeName, self::BUILTIN_TYPES, true)) {
                return [];
            }

            return [$input->getArgument($argument->name)];
        }

        if ($option = Option::tryFrom($member->getMember())) {
            if (!\in_array($option->typeName, self::BUILTIN_TYPES, true) && !\in_array($option->typeName, Option::ALLOWED_UNION_TYPES, true)) {
                return [];
            }

            return [$this->resolveOption($option, $input)];
        }

        return [];
    }

    private function resolveOption(Option $option, InputInterface $input): mixed
    {
        $value = $input->getOption($option->name);

        if (null === $value && \in_array($option->typeName, Option::ALLOWED_UNION_TYPES, true)) {
            return true;
        }

        if ('array' === $option->typeName && $option->allowNull && [] === $value) {
            return null;
        }

        if ('bool' === $option->typeName) {
            if ($option->allowNull && null === $value) {
                return null;
            }

            return $value ?? $option->default;
        }

        return $value;
    }
}
