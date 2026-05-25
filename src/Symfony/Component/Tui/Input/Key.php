<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Input;

/**
 * Helper class for creating key identifiers.
 *
 * Provides constants and factory methods for keyboard input matching.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Key
{
    // Special keys
    public const ESCAPE = 'escape';
    public const ENTER = 'enter';
    public const TAB = 'tab';
    public const SPACE = 'space';
    public const BACKSPACE = 'backspace';
    public const DELETE = 'delete';
    public const INSERT = 'insert';
    public const HOME = 'home';
    public const END = 'end';
    public const PAGE_UP = 'page_up';
    public const PAGE_DOWN = 'page_down';

    // Arrow keys
    public const UP = 'up';
    public const DOWN = 'down';
    public const LEFT = 'left';
    public const RIGHT = 'right';

    // Function keys
    public const F1 = 'f1';
    public const F2 = 'f2';
    public const F3 = 'f3';
    public const F4 = 'f4';
    public const F5 = 'f5';
    public const F6 = 'f6';
    public const F7 = 'f7';
    public const F8 = 'f8';
    public const F9 = 'f9';
    public const F10 = 'f10';
    public const F11 = 'f11';
    public const F12 = 'f12';

    public static function ctrl(string $key): string
    {
        return 'ctrl+'.strtolower($key);
    }

    public static function shift(string $key): string
    {
        return 'shift+'.strtolower($key);
    }

    public static function alt(string $key): string
    {
        return 'alt+'.strtolower($key);
    }

    public static function ctrlShift(string $key): string
    {
        return 'ctrl+shift+'.strtolower($key);
    }

    public static function ctrlAlt(string $key): string
    {
        return 'ctrl+alt+'.strtolower($key);
    }

    public static function shiftAlt(string $key): string
    {
        return 'shift+alt+'.strtolower($key);
    }

    public static function ctrlShiftAlt(string $key): string
    {
        return 'ctrl+shift+alt+'.strtolower($key);
    }
}
