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
 * Yields a variadic argument's values from the input.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class VariadicValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if (!$member->isVariadic()) {
            return [];
        }

        if ($argument = Argument::tryFrom($member->getMember())) {
            $values = $input->getArgument($argument->name);

            if (!\is_array($values)) {
                throw new \InvalidArgumentException(\sprintf('The action argument "...$%1$s" is required to be an array, the input argument "%1$s" contains a type of "%2$s" instead.', $argument->name, get_debug_type($values)));
            }

            return $values;
        }

        if ($option = Option::tryFrom($member->getMember())) {
            $values = $input->getOption($option->name);

            if (!\is_array($values)) {
                throw new \InvalidArgumentException(\sprintf('The action argument "...$%1$s" is required to be an array, the input option "--%1$s" contains a type of "%2$s" instead.', $option->name, get_debug_type($values)));
            }

            return $values;
        }

        return [];
    }
}
