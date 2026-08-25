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
use Symfony\Component\Console\Input\File\InputFileType;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class InputFileValueResolver implements ValueResolverInterface
{
    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $isSingle = InputFileType::isInputFile($member);
        $isCollection = !$isSingle && InputFileType::isInputFileCollection($member);

        if (!$isSingle && !$isCollection) {
            return [];
        }

        if ($argument = Argument::tryFrom($member->getMember())) {
            $value = $input->getArgument($argument->name);
        } elseif ($option = Option::tryFrom($member->getMember())) {
            $value = $input->getOption($option->name);
        } else {
            return [];
        }

        if ($isSingle) {
            return $this->resolveValue($value, $member);
        }

        $files = [];
        foreach (\is_array($value) ? $value : (null === $value ? [] : [$value]) as $file) {
            $files[] = $file instanceof InputFile ? $file : InputFile::fromPath($file);
        }

        // A variadic parameter receives one argument per file; an array parameter receives the whole list.
        return $member->isVariadic() ? $files : [$files];
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
