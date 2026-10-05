<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input\File;

use Symfony\Component\Console\Attribute\Reflection\DocBlockTypeResolver;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;

/**
 * Classifies a command input member (parameter or property) against the InputFile type.
 *
 * The "array of files" form is detected from the member's PHPDoc (`@param InputFile[] $files`,
 * `@var list<InputFile>`, ...). Detection is self-contained: Console does not depend on
 * symfony/type-info, so the item type is read from the doc comment and its short name is
 * resolved against the declaring file's namespace and `use` statements.
 *
 * @author Robin Chalas <robin.chalas@gmail.com>
 *
 * @internal
 */
final class InputFileType
{
    /**
     * Whether the member expects a single file, e.g. `InputFile $file`.
     */
    public static function isInputFile(ReflectionMember $member): bool
    {
        $type = $member->getType();

        return !$member->isVariadic() && $type instanceof \ReflectionNamedType && InputFile::class === $type->getName();
    }

    /**
     * Whether the member expects several files, either as a variadic `InputFile ...$files`
     * or as an array narrowed to InputFile through a PHPDoc (`@param InputFile[] $files`).
     */
    public static function isInputFileCollection(ReflectionMember $member): bool
    {
        $type = $member->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return false;
        }

        if ($member->isVariadic()) {
            return InputFile::class === $type->getName();
        }

        return 'array' === $type->getName() && self::arrayHoldsInputFiles($member);
    }

    private static function arrayHoldsInputFiles(ReflectionMember $member): bool
    {
        $class = DocBlockTypeResolver::resolveArrayItemClass($member->getMember());

        return null !== $class && is_a($class, InputFile::class, true);
    }
}
