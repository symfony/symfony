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

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\AbstractString;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\UnicodeString;

use function Symfony\Component\String\b;
use function Symfony\Component\String\s;
use function Symfony\Component\String\u;

final class FunctionsTest extends TestCase
{
    /**
     * @dataProvider provideSStrings
     */
    public function testS(AbstractString $expected, ?string $input)
    {
        self::assertEquals($expected, s($input));
    }

    public function provideSStrings(): array
    {
        return [
            [new UnicodeString(''), ''],
            [new UnicodeString(''), null],
            [new UnicodeString('foo'), 'foo'],
            [new UnicodeString('अनुच्छेद'), 'अनुच्छेद'],
            [new ByteString("b\x80ar"), "b\x80ar"],
            [new ByteString("\xfe\xff"), "\xfe\xff"],
        ];
    }

    /**
     * @dataProvider provideUStrings
     */
    public function testU(UnicodeString $expected, ?string $input)
    {
        self::assertEquals($expected, u($input));
    }

    public function provideUStrings(): array
    {
        return [
            [new UnicodeString(''), ''],
            [new UnicodeString(''), null],
            [new UnicodeString('foo'), 'foo'],
            [new UnicodeString('अनुच्छेद'), 'अनुच्छेद'],
        ];
    }

    /**
     * @dataProvider provideBStrings
     */
    public function testB(ByteString $expected, ?string $input)
    {
        self::assertEquals($expected, b($input));
    }

    public function provideBStrings(): array
    {
        return [
            [new ByteString(''), ''],
            [new ByteString(''), null],
            [new ByteString("b\x80ar"), "b\x80ar"],
            [new ByteString("\xfe\xff"), "\xfe\xff"],
        ];
    }
}
