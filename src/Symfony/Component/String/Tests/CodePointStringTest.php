<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use Symfony\Component\String\AbstractString;
use Symfony\Component\String\CodePointString;

class CodePointStringTest extends AbstractUnicodeTestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        return new CodePointString($string);
    }

    public static function provideLength(): array
    {
        return array_merge(
            parent::provideLength(),
            [
                // 8 instead of 5 if it were processed as a grapheme cluster
                [8, 'अनुच्छेद'],
            ]
        );
    }

    public static function provideBytesAt(): array
    {
        return array_merge(
            parent::provideBytesAt(),
            [
                [[0x61], "Spa\u{0308}ßchen", 2],
                [[0xCC, 0x88], "Spa\u{0308}ßchen", 3],
                [[0xE0, 0xA5, 0x8D], 'नमस्ते', 3],
            ]
        );
    }

    public static function provideCodePointsAt(): array
    {
        return array_merge(
            parent::provideCodePointsAt(),
            [
                [[0x61], "Spa\u{0308}ßchen", 2],
                [[0x0308], "Spa\u{0308}ßchen", 3],
                [[0x094D], 'नमस्ते', 3],
            ]
        );
    }
}
