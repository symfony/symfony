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

use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Yields the default value defined in the command signature when no input value has been explicitly passed.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class DefaultValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if ($member->hasDefaultValue()) {
            return [$member->getDefaultValue()];
        }

        if ($member->isNullable() && !$member->isVariadic()) {
            return [null];
        }

        return [];
    }
}
