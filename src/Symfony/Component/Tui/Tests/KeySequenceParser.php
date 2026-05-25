<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests;

/**
 * Handles key sequence parsing for TUI tests.
 *
 * This centralizes the key name to escape sequence mapping used by
 * both the fixture recorder and regression tests.
 */
final class KeySequenceParser
{
    /**
     * Map of human-readable key names to terminal escape sequences.
     *
     * @var array<string, string>
     */
    private const KEY_MAP = [
        '<Enter>' => "\r",
        '<Return>' => "\r",
        '<Escape>' => "\x1b",
        '<Esc>' => "\x1b",
        '<Tab>' => "\t",
        '<Shift+Tab>' => "\x1b[Z",
        '<F6>' => "\x1b[17~",
        '<Shift+F6>' => "\x1b[17;2~",
        '<Backspace>' => "\x7f",
        '<Up>' => "\x1b[A",
        '<Down>' => "\x1b[B",
        '<Right>' => "\x1b[C",
        '<Left>' => "\x1b[D",
        '<Home>' => "\x1b[H",
        '<End>' => "\x1b[F",
        '<PageUp>' => "\x1b[5~",
        '<PageDown>' => "\x1b[6~",
        '<Delete>' => "\x1b[3~",
        '<Ctrl+A>' => "\x01",
        '<Ctrl+C>' => "\x03",
        '<Ctrl+E>' => "\x05",
        '<Ctrl+K>' => "\x0b",
        '<Ctrl+U>' => "\x15",
        '<Ctrl+W>' => "\x17",
        '<Ctrl+Y>' => "\x19",
        '<Ctrl+->' => "\x1f",
        '<Alt+Left>' => "\x1bb",
        '<Alt+Right>' => "\x1bf",
        '<Alt+Backspace>' => "\x1b\x7f",
        '<Alt+D>' => "\x1bd",
        '<Shift+Enter>' => "\x1b[27;2;13~",
    ];

    /**
     * Convert human-readable key notation to terminal escape sequences.
     *
     * Examples:
     *   '<Enter>' => "\r"
     *   '<Down><Down>' => "\x1b[B\x1b[B"
     *   'hello' => 'hello'
     *   '<Escape><Down>' => "\x1b\x1b[B"
     */
    public static function parseKeys(string $keys): string
    {
        return str_replace(
            array_keys(self::KEY_MAP),
            array_values(self::KEY_MAP),
            $keys
        );
    }

    /**
     * Get all supported key mappings.
     *
     * @return array<string, string>
     */
    public static function getKeyMap(): array
    {
        return self::KEY_MAP;
    }
}
