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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for KeySequenceParser which converts human-readable key notation
 * to terminal escape sequences.
 */
class KeySequenceParserTest extends TestCase
{
    #[DataProvider('parseKeysProvider')]
    public function testParseKeys(string $input, string $expected)
    {
        $this->assertSame($expected, KeySequenceParser::parseKeys($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function parseKeysProvider(): array
    {
        return [
            'enter' => ['<Enter>', "\r"],
            'return' => ['<Return>', "\r"],
            'escape' => ['<Escape>', "\x1b"],
            'esc' => ['<Esc>', "\x1b"],
            'tab' => ['<Tab>', "\t"],
            'shift+tab' => ['<Shift+Tab>', "\x1b[Z"],
            'backspace' => ['<Backspace>', "\x7f"],
            'up arrow' => ['<Up>', "\x1b[A"],
            'down arrow' => ['<Down>', "\x1b[B"],
            'right arrow' => ['<Right>', "\x1b[C"],
            'left arrow' => ['<Left>', "\x1b[D"],
            'home' => ['<Home>', "\x1b[H"],
            'end' => ['<End>', "\x1b[F"],
            'page up' => ['<PageUp>', "\x1b[5~"],
            'page down' => ['<PageDown>', "\x1b[6~"],
            'delete' => ['<Delete>', "\x1b[3~"],
            'ctrl+a' => ['<Ctrl+A>', "\x01"],
            'ctrl+c' => ['<Ctrl+C>', "\x03"],
            'ctrl+e' => ['<Ctrl+E>', "\x05"],
            'ctrl+k' => ['<Ctrl+K>', "\x0b"],
            'ctrl+u' => ['<Ctrl+U>', "\x15"],
            'ctrl+w' => ['<Ctrl+W>', "\x17"],
            'ctrl+y' => ['<Ctrl+Y>', "\x19"],
            'ctrl+-' => ['<Ctrl+->', "\x1f"],
            'alt+left' => ['<Alt+Left>', "\x1bb"],
            'alt+right' => ['<Alt+Right>', "\x1bf"],
            'alt+backspace' => ['<Alt+Backspace>', "\x1b\x7f"],
            'alt+d' => ['<Alt+D>', "\x1bd"],
            'shift+enter' => ['<Shift+Enter>', "\x1b[27;2;13~"],
            'multiple keys' => ['<Down><Down>', "\x1b[B\x1b[B"],
            'escape then down' => ['<Escape><Down>', "\x1b\x1b[B"],
            'mixed text and keys' => ['hello<Enter>', "hello\r"],
            'plain text' => ['hello world', 'hello world'],
            'backspace sequence' => ['<Backspace><Backspace>', "\x7f\x7f"],
            'complex sequence' => ['<Ctrl+A>hello<Enter>', "\x01hello\r"],
        ];
    }

    public function testGetKeyMap()
    {
        $keyMap = KeySequenceParser::getKeyMap();

        $this->assertSame("\r", $keyMap['<Enter>']);
        $this->assertSame("\x1b", $keyMap['<Escape>']);
        $this->assertSame("\x1b[B", $keyMap['<Down>']);
    }
}
