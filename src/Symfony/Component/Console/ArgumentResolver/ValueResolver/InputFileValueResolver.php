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
use Symfony\Component\Console\Input\File\InputFile;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class InputFileValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $type = $member->getType();

        if (!$type instanceof \ReflectionNamedType || InputFile::class !== $type->getName()) {
            return [];
        }

        if ($argument = Argument::tryFrom($member->getMember())) {
            return $this->resolveValue($input->getArgument($argument->name), $member);
        }

        if ($option = Option::tryFrom($member->getMember())) {
            return $this->resolveValue($input->getOption($option->name), $member);
        }

        return [];
    }

    private function resolveValue(mixed $value, ReflectionMember $member): iterable
    {
        if (!$value) {
            if ($member->isNullable()) {
                return [null];
            }

            return [];
        }

        if ($value instanceof InputFile) {
            return [$value];
        }

        return [InputFile::fromPath($value)];
    }
}
