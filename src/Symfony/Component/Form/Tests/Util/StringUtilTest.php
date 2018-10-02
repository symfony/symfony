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
    public function testTrim()
    {
        $data = ' Foo! ';

        $this->assertEquals('Foo!', StringUtil::trim($data));
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

    public function spaceProvider()
    {
        return array(
            // separators
            array('0020'),
            array('00A0'),
            array('1680'),
//            array('180E'),
            array('2000'),
            array('2001'),
            array('2002'),
            array('2003'),
            array('2004'),
            array('2005'),
            array('2006'),
            array('2007'),
            array('2008'),
            array('2009'),
            array('200A'),
            array('2028'),
            array('2029'),
            array('202F'),
            array('205F'),
            array('3000'),
            // controls
            array('0009'),
            array('000A'),
            array('000B'),
            array('000C'),
            array('000D'),
            array('0085'),
            // zero width space
//            array('200B'),
        );
    }

    /**
     * @dataProvider fqcnToBlockPrefixProvider
     */
    public function testFqcnToBlockPrefix($fqcn, $expectedBlockPrefix)
    {
        $blockPrefix = StringUtil::fqcnToBlockPrefix($fqcn);

        $this->assertSame($expectedBlockPrefix, $blockPrefix);
    }

    public function fqcnToBlockPrefixProvider()
    {
        return array(
            array('TYPE', 'type'),
            array('\Type', 'type'),
            array('\UserType', 'user'),
            array('UserType', 'user'),
            array('Vendor\Name\Space\Type', 'type'),
            array('Vendor\Name\Space\UserForm', 'user_form'),
            array('Vendor\Name\Space\UserType', 'user'),
            array('Vendor\Name\Space\usertype', 'user'),
            array('Symfony\Component\Form\Form', 'form'),
            array('Vendor\Name\Space\BarTypeBazType', 'bar_type_baz'),
            array('FooBarBazType', 'foo_bar_baz'),
        );
    }
}
