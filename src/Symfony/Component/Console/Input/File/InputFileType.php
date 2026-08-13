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
     * A PHPDoc type expression: a name (optionally nullable, qualified or prefixed like
     * `non-empty-`) with optional generics and array suffixes, e.g. `InputFile[]`,
     * `?list<InputFile>`, `array<int, InputFile>`, `non-empty-list<InputFile>`, `InputFile[]|null`.
     */
    private const TYPE_PATTERN = '(?:null\s*\|\s*)?\??[\w\\\\-]+(?:<[^>]*>)?(?:\[\])*(?:\s*\|\s*null)?';

    /** @var array<string, array{namespace: string, uses: array<string, string>}> */
    private static array $fileContexts = [];

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
        $reflector = $member->getMember();

        if (null === $type = self::docBlockType($reflector)) {
            return false;
        }

        if (null === $item = self::arrayItemType($type)) {
            return false;
        }

        return is_a(self::resolveClassName($item, $reflector), InputFile::class, true);
    }

    /**
     * Reads the raw PHPDoc type of a member (the `@param`/`@var` tag).
     */
    private static function docBlockType(\ReflectionParameter|\ReflectionProperty $reflector): ?string
    {
        if ($reflector instanceof \ReflectionProperty) {
            if (null !== $type = self::varTag($reflector->getDocComment() ?: '')) {
                return $type;
            }

            // A promoted property may instead be documented on the constructor's @param.
            if ($reflector->isPromoted() && $constructor = $reflector->getDeclaringClass()->getConstructor()) {
                return self::paramTag($constructor->getDocComment() ?: '', $reflector->getName());
            }

            return null;
        }

        return self::paramTag($reflector->getDeclaringFunction()->getDocComment() ?: '', $reflector->getName());
    }

    private static function paramTag(string $docComment, string $name): ?string
    {
        if (preg_match('{@param\s+('.self::TYPE_PATTERN.')\s+(?:&\s*)?(?:\.\.\.)?\$'.preg_quote($name, '{}').'\b}', $docComment, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function varTag(string $docComment): ?string
    {
        if (preg_match('{@var\s+('.self::TYPE_PATTERN.')}', $docComment, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extracts the item type of an array shape (`Foo[]`, `list<Foo>`, `array<int, Foo>`,
     * `non-empty-list<Foo>`, ...). `iterable<Foo>` is intentionally left out: only a member
     * whose PHP type is `array` is treated as a collection (see isInputFileCollection()).
     */
    private static function arrayItemType(string $type): ?string
    {
        // Drop nullability so "?Foo[]", "Foo[]|null" and "null|Foo[]" are all handled.
        $type = trim(preg_replace('{^\?|^null\s*\||\s*\|\s*null$}i', '', $type));

        if (preg_match('{^([\w\\\\]+)\[\]$}', $type, $matches)) {
            return $matches[1];
        }

        if (preg_match('{^(?:non-empty-)?(?:list|array)<[^>]*?([\w\\\\]+)>$}', $type, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Resolves a class name used in a doc comment to its fully-qualified form, using the
     * declaring file's namespace and `use` statements.
     */
    private static function resolveClassName(string $name, \ReflectionParameter|\ReflectionProperty $reflector): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $file = $reflector instanceof \ReflectionProperty
            ? $reflector->getDeclaringClass()->getFileName()
            : $reflector->getDeclaringFunction()->getFileName();

        if (false === $file) {
            return $name;
        }

        ['namespace' => $namespace, 'uses' => $uses] = self::fileContext($file);

        $alias = strtok($name, '\\');

        if (isset($uses[strtolower($alias)])) {
            return $uses[strtolower($alias)].substr($name, \strlen($alias));
        }

        return '' !== $namespace ? $namespace.'\\'.$name : $name;
    }

    /**
     * @return array{namespace: string, uses: array<string, string>}
     */
    private static function fileContext(string $file): array
    {
        if (isset(self::$fileContexts[$file])) {
            return self::$fileContexts[$file];
        }

        $namespace = '';
        $uses = [];

        if (false !== $source = @file_get_contents($file)) {
            $tokens = \PhpToken::tokenize($source);
            $count = \count($tokens);

            for ($i = 0; $i < $count; ++$i) {
                $id = $tokens[$i]->id;

                if (\in_array($id, [\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM], true)) {
                    break; // imports always precede the type declaration
                }

                if (\T_NAMESPACE === $id) {
                    $namespace = ltrim(self::readStatement($tokens, $i, [';', '{']), '\\');
                } elseif (\T_USE === $id) {
                    $statement = self::readStatement($tokens, $i, [';']);

                    if (!str_starts_with($statement, 'function ') && !str_starts_with($statement, 'const ')) {
                        self::collectUses($statement, $uses);
                    }
                }
            }
        }

        return self::$fileContexts[$file] = ['namespace' => $namespace, 'uses' => $uses];
    }

    /**
     * @param list<\PhpToken> $tokens
     */
    private static function readStatement(array $tokens, int &$i, array $stops): string
    {
        $text = '';

        for (++$i; $i < \count($tokens); ++$i) {
            if (\in_array($tokens[$i]->text, $stops, true)) {
                break;
            }

            $text .= $tokens[$i]->text;
        }

        return trim($text);
    }

    /**
     * @param array<string, string> $uses
     */
    private static function collectUses(string $statement, array &$uses): void
    {
        // Grouped imports: "A\B\{C, D as E}".
        if (preg_match('{^(.+?)\\\\\{(.+)\}$}', $statement, $matches)) {
            $prefix = trim($matches[1]).'\\';

            foreach (explode(',', $matches[2]) as $part) {
                self::addUse($prefix.trim($part), $uses);
            }

            return;
        }

        self::addUse($statement, $uses);
    }

    /**
     * @param array<string, string> $uses
     */
    private static function addUse(string $import, array &$uses): void
    {
        if ('' === $import = ltrim(trim($import), '\\')) {
            return;
        }

        if (preg_match('{^(.+?)\s+as\s+(\w+)$}i', $import, $matches)) {
            $class = trim($matches[1]);
            $alias = $matches[2];
        } else {
            $class = $import;
            $alias = str_contains($import, '\\') ? substr($import, strrpos($import, '\\') + 1) : $import;
        }

        $uses[strtolower($alias)] = ltrim($class, '\\');
    }
}
