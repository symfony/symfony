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
}
