<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Util\StringUtils;

class StringUtilsTest extends TestCase
{
    // --- hasControlChars ---

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hasControlCharsProvider(): iterable
    {
        yield 'null byte' => ["\x00", true];
        yield 'escape' => ["\x1b", true];
        yield 'del' => ["\x7f", true];
        yield 'mixed (control in text)' => ["hello\x00world", true];
        yield 'printable ASCII' => ['hello', false];
        yield 'latin accents' => ['café', false];
        yield 'emoji' => ['👋', false];
        yield 'emoji mixed with text' => ['hello 🎉 world', false];
        yield 'CJK' => ['中文', false];
    }

    #[DataProvider('hasControlCharsProvider')]
    public function testHasControlChars(string $input, bool $expected)
    {
        $this->assertSame($expected, StringUtils::hasControlChars($input));
    }

    // --- sanitizeUtf8 ---

    #[DataProvider('sanitizeUtf8PassThroughProvider')]
    public function testSanitizeUtf8PassThrough(string $input)
    {
        $this->assertSame($input, StringUtils::sanitizeUtf8($input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sanitizeUtf8PassThroughProvider(): iterable
    {
        yield 'ASCII' => ['hello'];
        yield 'multibyte' => ['café'];
        yield 'empty' => [''];
    }

    #[DataProvider('sanitizeUtf8InvalidBytesProvider')]
    public function testSanitizeUtf8InvalidBytes(string $input, string $expected)
    {
        $result = StringUtils::sanitizeUtf8($input);
        $this->assertSame($expected, $result);
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function sanitizeUtf8InvalidBytesProvider(): iterable
    {
        yield 'invalid bytes' => ["hello\xFF\xFEworld", 'helloworld'];
        // "crème café" in ISO-8859-1
        yield 'ending with an invalid byte' => ["cr\xE8me caf\xE9", 'crme caf'];
        yield 'ending with a truncated sequence' => ["caf\xC3", 'caf'];
        yield 'truncated sequence before a valid one' => ["\xF0\x9F\x98caf\xC3\xA9", 'café'];
        yield 'surrogate' => ["a\xED\xA0\x80b", 'ab'];
        yield 'overlong encoding' => ["a\xC0\xAFb", 'ab'];
        yield 'beyond U+10FFFF' => ["a\xF4\x90\x80\x80b", 'ab'];
    }

    #[DataProvider('ctrlLetterKeysProvider')]
    public function testDecodeCtrlLetterKeys(string $input, string $expected)
    {
        $this->assertSame($expected, StringUtils::decodeCtrlLetterKeys($input));
    }

    public static function ctrlLetterKeysProvider(): iterable
    {
        yield 'no escape sequence' => ['plain text', 'plain text'];
        yield 'ctrl+j' => ["a\x1b[106;5ub", "a\nb"];
        yield 'ctrl+J' => ["a\x1b[74;5ub", "a\nb"];
        yield 'ctrl+i' => ["a\x1b[105;5ub", "a\tb"];
        yield 'ctrl+digit is kept' => ["\x1b[49;5u", "\x1b[49;5u"];
        yield 'other modifiers are kept' => ["\x1b[106;3u", "\x1b[106;3u"];
        yield 'ctrl+j in the xterm format' => ["a\x1b[27;5;106~b", "a\nb"];
        yield 'other modifiers in the xterm format are kept' => ["\x1b[27;6;106~", "\x1b[27;6;106~"];
    }
}
