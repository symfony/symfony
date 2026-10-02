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

    public function testShiftedDigitsAndSymbolsDoNotMatchTheUnshiftedKey()
    {
        $this->assertFalse($this->parser->matches('1', 'shift+1'));
        $this->assertFalse($this->parser->matches('-', 'shift+-'));
        $this->assertFalse($this->parser->matches('/', 'shift+/'));
    }

    public function testShiftedLettersStillMatchTheUppercaseByte()
    {
        $this->assertTrue($this->parser->matches('A', 'shift+a'));
        $this->assertFalse($this->parser->matches('a', 'shift+a'));
    }

    public function testUnnameableModifierIsNotReportedAsAnUnmodifiedKey()
    {
        $this->parser->setKittyProtocolActive(true);

        // Modifier value 257 sets bit 256, which the protocol does not define.
        $this->assertNull($this->parser->parse("\x1b[97;257u"));
        $this->assertFalse($this->parser->matches("\x1b[97;257u", 'a'));
    }

    #[DataProvider('csiOnlyModifierProvider')]
    public function testSuperHyperAndMetaAreParsed(string $input, string $expectedKey, array $expectedModifiers)
    {
        $this->parser->setKittyProtocolActive(true);

        $result = $this->parser->parse($input);
        $this->assertSame($expectedKey, $result['key']);
        $this->assertSame($expectedModifiers, $result['modifiers']);
        $this->assertTrue($this->parser->matches($input, $expectedKey));
    }

    public static function csiOnlyModifierProvider(): iterable
    {
        yield 'super+c' => ["\x1b[99;9u", 'super+c', ['super']];
        yield 'super+c with caps lock' => ["\x1b[99;73u", 'super+c', ['super']];
        yield 'shift+super+z' => ["\x1b[122;10u", 'shift+super+z', ['shift', 'super']];
        yield 'ctrl+alt+super+a' => ["\x1b[97;15u", 'ctrl+alt+super+a', ['ctrl', 'alt', 'super']];
        yield 'hyper+a' => ["\x1b[97;17u", 'hyper+a', ['hyper']];
        yield 'meta+a' => ["\x1b[97;33u", 'meta+a', ['meta']];
        yield 'super+left' => ["\x1b[1;9D", 'super+left', ['super']];
        yield 'super+enter' => ["\x1b[13;9u", 'super+enter', ['super']];
        yield 'super+backspace' => ["\x1b[127;9u", 'super+backspace', ['super']];
        yield 'super+delete' => ["\x1b[3;9~", 'super+delete', ['super']];
        yield 'super+/' => ["\x1b[47;9u", 'super+/', ['super']];
        yield 'super++' => ["\x1b[43;9u", 'super++', ['super']];
        // As Ghostty sends them on an AZERTY layout, with the base layout key
        yield 'super+z with a base layout key' => ["\x1b[122::119;9u", 'super+z', ['super']];
        yield 'shift+super+z with shifted and base layout keys' => ["\x1b[122:90:119;10u", 'shift+super+z', ['shift', 'super']];
    }

    public function testSuperKeyIdDoesNotMatchTheBareKey()
    {
        $this->parser->setKittyProtocolActive(true);

        $this->assertFalse($this->parser->matches('c', 'super+c'));
        $this->assertFalse($this->parser->matches("\x1b[99u", 'super+c'));
        $this->assertFalse($this->parser->matches("\x03", 'super+c'));
        $this->assertFalse($this->parser->matches("\x1b[99;5u", 'super+c'));
        $this->assertFalse($this->parser->matches("\x1b[99;9u", 'c'));
        $this->assertFalse($this->parser->matches("\x1b[99;9u", 'ctrl+c'));
        $this->assertFalse($this->parser->matches("\x1b[1;3D", 'alt+super+left'));
    }

    public function testSuperMatchesTheKeypadEnterAndModifiedFunctionKeys()
    {
        $this->assertTrue($this->parser->matches("\x1b[57414;9u", 'super+enter'));
        $this->assertTrue($this->parser->matches("\x1b[1;9P", 'super+f1'));
        $this->assertTrue($this->parser->matches("\x1b[15;9~", 'super+f5'));
        $this->assertFalse($this->parser->matches("\x1b[15~", 'super+f5'));
    }

    public function testSuperFunctionKeyIsParsedAndMatchesRepeatsAndLockModifiers()
    {
        $this->assertSame('super+f1', $this->parser->parse("\x1b[1;9P")['key'] ?? null);
        $this->assertSame('super+f5', $this->parser->parse("\x1b[15;9~")['key'] ?? null);
        // Repeat event
        $this->assertTrue($this->parser->matches("\x1b[1;9:2P", 'super+f1'));
        $this->assertTrue($this->parser->matches("\x1b[15;9:2~", 'super+f5'));
        // Caps Lock (64) and Num Lock (128) are ignored
        $this->assertTrue($this->parser->matches("\x1b[1;73P", 'super+f1'));
        $this->assertTrue($this->parser->matches("\x1b[15;137~", 'super+f5'));
    }

    public function testUnknownModifierInAKeyIdMatchesNothing()
    {
        $this->assertFalse($this->parser->matches('c', 'cmd+c'));
        $this->assertFalse($this->parser->matches("\x1b[99;9u", 'cmd+c'));
        $this->assertFalse($this->parser->matches('c', 'foo+c'));
    }

    public function testSuperKeyIdNamingAKeyTheParserDoesNotNameMatchesNothing()
    {
        $this->parser->setKittyProtocolActive(true);

        $this->assertFalse($this->parser->matches("\x1b[57414;9u", 'super+kp_enter'));
        $this->assertFalse($this->parser->matches("\x1b[32;9u", 'super+ '));
        $this->assertFalse($this->parser->matches("\x1b[34;9u", 'super+"'));
    }

    public function testLockModifiersAreStillIgnored()
    {
        $this->parser->setKittyProtocolActive(true);

        // Caps Lock (64) and Num Lock (128) are reported but carry no key id.
        $result = $this->parser->parse("\x1b[97;65u");
        $this->assertSame('a', $result['key']);
        $this->assertSame([], $result['modifiers']);
    }

    public function testPlusKeyIsReportedWithoutModifiers()
    {
        $result = $this->parser->parse('+');

        $this->assertSame('+', $result['key']);
        $this->assertSame([], $result['modifiers']);
    }

    public function testModifiedPlusKeyKeepsThePlusAsTheKey()
    {
        $this->parser->setKittyProtocolActive(true);

        $result = $this->parser->parse("\x1b[43;5u");
        $this->assertSame('ctrl++', $result['key']);
        $this->assertSame(['ctrl'], $result['modifiers']);

        $result = $this->parser->parse("\x1b[43;4u");
        $this->assertSame('shift+alt++', $result['key']);
        $this->assertSame(['shift', 'alt'], $result['modifiers']);
    }

    public function testModifiedPlusKeyCanBeBound()
    {
        $this->parser->setKittyProtocolActive(true);

        $this->assertTrue($this->parser->matches("\x1b[43;5u", 'ctrl++'));
        $this->assertFalse($this->parser->matches("\x1b[43;5u", 'ctrl+a'));
        $this->assertFalse($this->parser->matches("\x1b[97;5u", 'ctrl++'));
    }

    public function testCtrlAltLetterMatchesBothEncodingsWithoutTheKittyFlag()
    {
        // A CSI u sequence is parsed whether or not the flag is set, so both
        // encodings have to match, as they do for alt and ctrl on their own.
        $this->assertSame('ctrl+alt+a', $this->parser->parse("\x1b[97;7u")['key']);

        $this->assertTrue($this->parser->matches("\x1b\x01", 'ctrl+alt+a'));
        $this->assertTrue($this->parser->matches("\x1b[97;7u", 'ctrl+alt+a'));
        $this->assertFalse($this->parser->matches("\x1b[98;7u", 'ctrl+alt+a'));
        $this->assertFalse($this->parser->matches("\x1b[97;5u", 'ctrl+alt+a'));
    }

    /**
     * xterm's modifyOtherKeys form reports every modifier through the same
     * `CSI 27 ; mods ; keycode ~` shape, not just shift and alt.
     */
    #[DataProvider('modifyOtherKeysEnterProvider')]
    public function testModifyOtherKeysEnter(string $sequence, string $keyId)
    {
        $this->assertTrue($this->parser->matches($sequence, $keyId));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function modifyOtherKeysEnterProvider(): iterable
    {
        yield 'enter' => ["\x1b[27;1;13~", 'enter'];
        yield 'shift+enter' => ["\x1b[27;2;13~", 'shift+enter'];
        yield 'alt+enter' => ["\x1b[27;3;13~", 'alt+enter'];
        yield 'ctrl+enter' => ["\x1b[27;5;13~", 'ctrl+enter'];
        yield 'ctrl+shift+enter' => ["\x1b[27;6;13~", 'ctrl+shift+enter'];
        yield 'ctrl+alt+enter' => ["\x1b[27;7;13~", 'ctrl+alt+enter'];
    }

    public function testModifyOtherKeysEnterDoesNotMatchAnotherModifier()
    {
        $this->assertFalse($this->parser->matches("\x1b[27;5;13~", 'shift+enter'));
        $this->assertFalse($this->parser->matches("\x1b[27;5;9~", 'ctrl+enter'));
    }

    #[DataProvider('modifiedFunctionKeyProvider')]
    public function testParseModifiedFunctionKey(string $sequence, string $keyId)
    {
        $this->assertSame($keyId, $this->parser->parse($sequence)['key']);
        $this->assertTrue($this->parser->matches($sequence, $keyId));

        $this->parser->setKittyProtocolActive(true);

        $this->assertSame($keyId, $this->parser->parse($sequence)['key']);
        $this->assertTrue($this->parser->matches($sequence, $keyId));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function modifiedFunctionKeyProvider(): iterable
    {
        yield 'ctrl+f1' => ["\x1b[1;5P", 'ctrl+f1'];
        yield 'shift+f2' => ["\x1b[1;2Q", 'shift+f2'];
        yield 'alt+f3' => ["\x1b[1;3R", 'alt+f3'];
        yield 'shift+ctrl+f4' => ["\x1b[1;6S", 'shift+ctrl+f4'];
        yield 'ctrl+f3 (CSI ~)' => ["\x1b[13;5~", 'ctrl+f3'];
        yield 'ctrl+f5' => ["\x1b[15;5~", 'ctrl+f5'];
        yield 'shift+f6' => ["\x1b[17;2~", 'shift+f6'];
        yield 'alt+f12' => ["\x1b[24;3~", 'alt+f12'];
        yield 'ctrl+left' => ["\x1b[1;5D", 'ctrl+left'];
        yield 'shift+home' => ["\x1b[1;2H", 'shift+home'];
        yield 'ctrl+delete' => ["\x1b[3;5~", 'ctrl+delete'];
        yield 'ctrl+f1 repeat' => ["\x1b[1;5:2P", 'ctrl+f1'];
        yield 'f5 repeat' => ["\x1b[15;1:2~", 'f5'];
        yield 'ctrl+f1 with caps lock' => ["\x1b[1;69P", 'ctrl+f1'];
        yield 'f5 with num lock' => ["\x1b[15;129~", 'f5'];
    }

    public function testParseUnmodifiedFunctionKey()
    {
        $this->assertSame('f1', $this->parser->parse("\x1b[11~")['key']);
        $this->assertSame('f12', $this->parser->parse("\x1b[24~")['key']);

        $this->parser->setKittyProtocolActive(true);

        $this->assertSame('f1', $this->parser->parse("\x1b[11~")['key']);
        $this->assertSame('f12', $this->parser->parse("\x1b[24~")['key']);
    }

    public function testModifiedFunctionKeyRelease()
    {
        $this->parser->setKittyProtocolActive(true);

        $this->assertTrue($this->parser->isKeyRelease("\x1b[1;5:3P"));
        $this->assertTrue($this->parser->isKeyRepeat("\x1b[1;5:2P"));
        $this->assertSame(3, $this->parser->parse("\x1b[1;5:3P")['event_type']);
        $this->assertSame(3, $this->parser->parse("\x1b[15;5:3~")['event_type']);
        $this->assertFalse($this->parser->matches("\x1b[1;5:3P", 'ctrl+f1'));
        $this->assertFalse($this->parser->isKeyRelease('foo:3P'));
        $this->assertFalse($this->parser->isKeyRepeat('foo:2S'));
    }

    public function testFunctionKeyWithUnnameableModifierIsNotReported()
    {
        // Modifier value 257 means bit 256 is set, which has no key id.
        $this->assertNull($this->parser->parse("\x1b[1;257P"));
        $this->assertNull($this->parser->parse("\x1b[15;257~"));
    }

    public function testModifyOtherKeysIsNotParsedAsAFunctionKey()
    {
        $this->assertNull($this->parser->parse("\x1b[27;5;13~"));
    }
}
