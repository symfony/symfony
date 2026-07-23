<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\KeyParser;

class KeyParserTest extends TestCase
{
    private KeyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new KeyParser();
    }

    #[DataProvider('parseKeyProvider')]
    public function testParseKey(string $input, string $expectedKey)
    {
        $result = $this->parser->parse($input);
        $this->assertSame($expectedKey, $result['key']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function parseKeyProvider(): iterable
    {
        yield 'enter (CR)' => ["\r", Key::ENTER];
        yield 'enter (LF)' => ["\n", Key::ENTER];
        yield 'escape' => ["\x1b", Key::ESCAPE];
        yield 'tab' => ["\t", Key::TAB];
        yield 'backspace' => ["\x7f", Key::BACKSPACE];
        yield 'ctrl+c' => ["\x03", 'ctrl+c'];
        yield 'ctrl+a' => ["\x01", 'ctrl+a'];
        yield 'alt+x' => ["\x1bx", 'alt+x'];
        yield 'printable a' => ['a', 'a'];
        yield 'printable Z' => ['Z', 'Z'];
        yield 'digit 0' => ['0', '0'];
        yield 'digit 1' => ['1', '1'];
        yield 'digit 9' => ['9', '9'];
        yield 'alt+1 legacy' => ["\x1b1", 'alt+1'];
    }

    #[DataProvider('matchesKeyProvider')]
    public function testMatchesKey(string $input, string $expectedKey)
    {
        $this->assertTrue($this->parser->matches($input, $expectedKey));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function matchesKeyProvider(): iterable
    {
        // Arrow keys (CSI)
        yield 'up (CSI)' => ["\x1b[A", Key::UP];
        yield 'down (CSI)' => ["\x1b[B", Key::DOWN];
        yield 'right (CSI)' => ["\x1b[C", Key::RIGHT];
        yield 'left (CSI)' => ["\x1b[D", Key::LEFT];
        // Arrow keys (SS3)
        yield 'up (SS3)' => ["\x1bOA", Key::UP];
        yield 'down (SS3)' => ["\x1bOB", Key::DOWN];
        yield 'right (SS3)' => ["\x1bOC", Key::RIGHT];
        yield 'left (SS3)' => ["\x1bOD", Key::LEFT];
        // Home/End
        yield 'home (CSI H)' => ["\x1b[H", Key::HOME];
        yield 'end (CSI F)' => ["\x1b[F", Key::END];
        yield 'home (CSI 1~)' => ["\x1b[1~", Key::HOME];
        yield 'end (CSI 4~)' => ["\x1b[4~", Key::END];
        // Page Up/Down
        yield 'page up' => ["\x1b[5~", Key::PAGE_UP];
        yield 'page down' => ["\x1b[6~", Key::PAGE_DOWN];
        // Delete
        yield 'delete' => ["\x1b[3~", Key::DELETE];
        // Function keys
        yield 'F1' => ["\x1bOP", Key::F1];
        yield 'F2' => ["\x1bOQ", Key::F2];
        yield 'F3' => ["\x1bOR", Key::F3];
        yield 'F4' => ["\x1bOS", Key::F4];
        // Modified arrows
        yield 'ctrl+right' => ["\x1b[1;5C", Key::ctrl('right')];
        yield 'ctrl+left' => ["\x1b[1;5D", Key::ctrl('left')];
        yield 'alt+right' => ["\x1b[1;3C", Key::alt('right')];
        yield 'alt+left' => ["\x1b[1;3D", Key::alt('left')];
        // Digit keys
        yield 'digit 0' => ['0', '0'];
        yield 'digit 1' => ['1', '1'];
        yield 'digit 5' => ['5', '5'];
        yield 'digit 9' => ['9', '9'];
        yield 'alt+1 legacy' => ["\x1b1", 'alt+1'];
        yield 'alt+9 legacy' => ["\x1b9", 'alt+9'];
        // ModifyOtherKeys
        yield 'shift+enter' => ["\x1b[27;2;13~", 'shift+enter'];
    }

    #[DataProvider('kittyProtocolProvider')]
    public function testParseKittyProtocol(string $input, string $expectedKey)
    {
        $this->parser->setKittyProtocolActive(true);

        $result = $this->parser->parse($input);
        $this->assertSame($expectedKey, $result['key']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function kittyProtocolProvider(): iterable
    {
        yield 'plain key (a)' => ["\x1b[97u", 'a'];
        yield 'ctrl+a' => ["\x1b[97;5u", 'ctrl+a'];
        yield 'digit 1' => ["\x1b[49u", '1'];
        yield 'ctrl+1' => ["\x1b[49;5u", 'ctrl+1'];
        yield 'newline as shift+enter' => ["\n", 'shift+enter'];
    }

    public function testMatchesNormalizesModifierOrder()
    {
        $result = $this->parser->parse("\x1b[97;5u"); // Ctrl+A in Kitty
        $this->parser->setKittyProtocolActive(true);

        // Both orders should match
        $this->assertTrue($this->parser->matches("\x1b[97;5u", 'ctrl+a'));
    }

    public function testMatchesHandlesAliases()
    {
        $this->assertTrue($this->parser->matches("\x1b", 'escape'));
        $this->assertTrue($this->parser->matches("\x1b", 'esc'));
        $this->assertTrue($this->parser->matches("\r", 'enter'));
        $this->assertTrue($this->parser->matches("\r", 'return'));
    }

    public function testParseEmptyString()
    {
        $result = $this->parser->parse('');
        $this->assertNull($result);
    }

    public function testAltBackspaceVariants()
    {
        // Legacy: ESC + DEL (0x7F)
        $this->assertTrue($this->parser->matches("\x1b\x7f", 'alt+backspace'));
        $this->assertSame('alt+backspace', $this->parser->parse("\x1b\x7f")['key']);

        // Legacy: ESC + BS (0x08)
        $this->assertTrue($this->parser->matches("\x1b\x08", 'alt+backspace'));
        $this->assertSame('alt+backspace', $this->parser->parse("\x1b\x08")['key']);

        // Kitty: codepoint 127, modifier 3 (alt)
        $this->parser->setKittyProtocolActive(true);
        $this->assertTrue($this->parser->matches("\x1b[127;3u", 'alt+backspace'));

        // Plain backspace should NOT match alt+backspace
        $this->assertFalse($this->parser->matches("\x7f", 'alt+backspace'));
        $this->assertFalse($this->parser->matches("\x08", 'alt+backspace'));
    }

    public function testIsKeyRelease()
    {
        $this->parser->setKittyProtocolActive(true);

        // Event type 3 = release
        $this->assertTrue($this->parser->isKeyRelease("\x1b[97;1:3u"));
    }

    public function testIsKeyRepeat()
    {
        $this->parser->setKittyProtocolActive(true);

        // Event type 2 = repeat
        $this->assertTrue($this->parser->isKeyRepeat("\x1b[97;1:2u"));
    }

    public function testDigitKeysDoNotCrossMatch()
    {
        $this->assertFalse($this->parser->matches('1', '2'));
        $this->assertFalse($this->parser->matches('1', 'a'));
        $this->assertFalse($this->parser->matches('a', '1'));
    }

    /**
     * On non-US layouts (e.g. AZERTY), the Kitty protocol reports both the
     * logical key (codepoint) and the US QWERTY physical position
     * (baseLayoutKey). Keybindings must resolve using the logical key so
     * that Ctrl+W triggers "delete word backward", not "suspend" (Ctrl+Z).
     */
    public function testKittyBaseLayoutKeyIsIgnoredForMatching()
    {
        $this->parser->setKittyProtocolActive(true);

        // AZERTY Ctrl+W: codepoint=119 ('w'), baseLayoutKey=122 ('z'), modifier=ctrl
        // Kitty sequence: \x1b[119::122;5u
        $ctrlW = "\x1b[119::122;5u";

        // Must match ctrl+w (the logical key)
        $this->assertTrue($this->parser->matches($ctrlW, 'ctrl+w'));

        // Must NOT match ctrl+z (the physical US QWERTY position)
        $this->assertFalse($this->parser->matches($ctrlW, 'ctrl+z'));
    }

    /**
     * On non-US layouts, parse() must return the logical key name, not the
     * US QWERTY physical position.
     */
    public function testKittyBaseLayoutKeyIsIgnoredForParsing()
    {
        $this->parser->setKittyProtocolActive(true);

        // AZERTY Ctrl+W: codepoint=119 ('w'), baseLayoutKey=122 ('z')
        $result = $this->parser->parse("\x1b[119::122;5u");
        $this->assertSame('ctrl+w', $result['key']);

        // AZERTY Ctrl+Z: codepoint=122 ('z'), baseLayoutKey=119 ('w')
        $result = $this->parser->parse("\x1b[122::119;5u");
        $this->assertSame('ctrl+z', $result['key']);
    }
}
