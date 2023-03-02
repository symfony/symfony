<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Util\StringUtil;

class StringUtilTest extends TestCase
{
    public static function trimProvider()
    {
        return [
            [' Foo! ', 'Foo!'],
            ["\u{1F92E}", "\u{1F92E}"], // unassigned character in PCRE versions of <PHP 7.3
        ];
    }

    /**
     * @dataProvider trimProvider
     */
    public function testTrim($data, $expectedData)
    {
        $this->assertSame($expectedData, StringUtil::trim($data));
    }

    /**
     * @dataProvider spaceProvider
     */
    public function testTrimUtf8Separators($hex)
    {
        // Convert hexadecimal representation into binary
        // H: hex string, high nibble first (UCS-2BE)
        // *: repeat until end of string
        $binary = pack('H*', $hex);

        // Convert UCS-2BE to UTF-8
        $symbol = mb_convert_encoding($binary, 'UTF-8', 'UCS-2BE');
        $symbol .= "ab\ncd".$symbol;

        $this->assertSame("ab\ncd", StringUtil::trim($symbol));
    }

    public static function spaceProvider()
    {
        return [
            // separators
            ['0020'],
            ['00A0'],
            ['1680'],
//            ['180E'],
            ['2000'],
            ['2001'],
            ['2002'],
            ['2003'],
            ['2004'],
            ['2005'],
            ['2006'],
            ['2007'],
            ['2008'],
            ['2009'],
            ['200A'],
            ['2028'],
            ['2029'],
            ['202F'],
            ['205F'],
            ['3000'],
            // controls
            ['0009'],
            ['000A'],
            ['000B'],
            ['000C'],
            ['000D'],
            ['0085'],
            // zero width space
            ['200B'],
            // soft hyphen
            ['00AD'],
        ];
    }

    /**
     * @dataProvider fqcnToBlockPrefixProvider
     */
    public function testFqcnToBlockPrefix($fqcn, $expectedBlockPrefix)
    {
        $blockPrefix = StringUtil::fqcnToBlockPrefix($fqcn);

        $this->assertSame($expectedBlockPrefix, $blockPrefix);
    }

    public static function fqcnToBlockPrefixProvider()
    {
        return [
            ['TYPE', 'type'],
            ['\Type', 'type'],
            ['\UserType', 'user'],
            ['UserType', 'user'],
            ['Vendor\Name\Space\Type', 'type'],
            ['Vendor\Name\Space\UserForm', 'user_form'],
            ['Vendor\Name\Space\UserType', 'user'],
            ['Vendor\Name\Space\usertype', 'user'],
            ['Symfony\Component\Form\Form', 'form'],
            ['Vendor\Name\Space\BarTypeBazType', 'bar_type_baz'],
            ['FooBarBazType', 'foo_bar_baz'],
        ];
    }
}
