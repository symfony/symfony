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
use Symfony\Component\String\ByteString;

class ByteStringTest extends AbstractAsciiTestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        return new ByteString($string);
    }

    public static function provideBytesAt(): array
    {
        return array_merge(
            parent::provideBytesAt(),
            [
                [[0xC3], 'Späßchen', 2],
                [[0x61], "Spa\u{0308}ßchen", 2],
                [[0xCC], "Spa\u{0308}ßchen", 3],
                [[0xE0], 'नमस्ते', 6],
            ]
        );
    }

    public static function provideLength(): array
    {
        return array_merge(
            parent::provideLength(),
            [
                [2, 'ä'],
            ]
        );
    }
}
