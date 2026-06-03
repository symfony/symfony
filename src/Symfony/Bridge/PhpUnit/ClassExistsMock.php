<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ClassExistsMock
{
    private static $classes = [];

    private static $enums = [];

    /**
     * Configures the classes to be checked upon existence.
     *
     * @param array $classes Mocked class names as keys (case-sensitive, without leading root namespace slash) and booleans as values
     */
    public static function withMockedClasses(array $classes): void
    {
        self::$classes = $classes;
    }

    /**
     * Configures the enums to be checked upon existence.
     *
     * @param array $enums Mocked enums names as keys (case-sensitive, without leading root namespace slash) and booleans as values
     */
    public static function withMockedEnums(array $enums): void
    {
        self::$enums = $enums;
        self::$classes += $enums;
    }

    public static function class_exists($name, $autoload = true): bool
    {
        $name = ltrim($name, '\\');

        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \class_exists($name, $autoload);
    }

    public static function interface_exists($name, $autoload = true): bool
    {
        $name = ltrim($name, '\\');

        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \interface_exists($name, $autoload);
    }

    public static function trait_exists($name, $autoload = true): bool
    {
        $name = ltrim($name, '\\');

        return isset(self::$classes[$name]) ? (bool) self::$classes[$name] : \trait_exists($name, $autoload);
    }

    public static function enum_exists($name, $autoload = true): bool
    {
        $name = ltrim($name, '\\');

        return isset(self::$enums[$name]) ? (bool) self::$enums[$name] : \enum_exists($name, $autoload);
    }

    public static function register($class): void
    {
        $self = static::class;

        $mockedNs = [substr($class, 0, strrpos($class, '\\'))];
        if (0 < strpos($class, '\\Tests\\')) {
            $ns = str_replace('\\Tests\\', '\\', $class);
            $mockedNs[] = substr($ns, 0, strrpos($ns, '\\'));
        } elseif (str_starts_with($class, 'Tests\\')) {
            $mockedNs[] = substr($class, 6, strrpos($class, '\\') - 6);
        }
        foreach ($mockedNs as $ns) {
            foreach (['class', 'interface', 'trait', 'enum'] as $type) {
                if (\function_exists($ns.'\\'.$type.'_exists')) {
                    continue;
                }
                eval(<<<EOPHP
namespace $ns;

function {$type}_exists(\$name, \$autoload = true)
{
    return \\$self::{$type}_exists(\$name, \$autoload);
}

EOPHP
                );
            }
        }
    }
}
