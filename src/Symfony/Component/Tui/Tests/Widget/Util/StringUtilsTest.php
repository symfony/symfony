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

    public function testSanitizeUtf8InvalidBytes()
    {
        $result = StringUtils::sanitizeUtf8("hello\xFF\xFEworld");
        $this->assertSame('helloworld', $result);
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
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
