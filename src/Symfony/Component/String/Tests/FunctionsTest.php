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
use function Symfony\Component\String\s;
use Symfony\Component\String\UnicodeString;

final class FunctionsTest extends TestCase
{
    /**
     * @dataProvider provideStrings
     */
    public function testS(AbstractString $expected, string $input)
    {
        $this->assertEquals($expected, s($input));
    }

    public function provideStrings(): array
    {
        return [
            [new UnicodeString('foo'), 'foo'],
            [new UnicodeString('अनुच्छेद'), 'अनुच्छेद'],
            [new ByteString("b\x80ar"), "b\x80ar"],
            [new ByteString("\xfe\xff"), "\xfe\xff"],
        ];
    }
}
