<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use Symfony\Component\DependencyInjection\StringUtil;

class StringUtilTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataForTestCamelize
     */
    public function testCamelize($id, $expected)
    {
        $this->assertEquals($expected, StringUtil::camelize($id), sprintf('Container::camelize("%s")', $id));
    }

    public function dataForTestCamelize()
    {
        return array(
            array('foo_bar', 'FooBar'),
            array('foo.bar', 'Foo_Bar'),
            array('foo.bar_baz', 'Foo_BarBaz'),
            array('foo._bar', 'Foo_Bar'),
            array('foo_.bar', 'Foo_Bar'),
            array('_foo', 'Foo'),
            array('.foo', '_Foo'),
            array('foo_', 'Foo'),
            array('foo.', 'Foo_'),
            array('foo\bar', 'Foo_Bar'),
        );
    }

    /**
     * @dataProvider dataForTestUnderscore
     */
    public function testUnderscore($id, $expected)
    {
        $this->assertEquals($expected, StringUtil::underscore($id), sprintf('Container::underscore("%s")', $id));
    }

    public function dataForTestUnderscore()
    {
        return array(
            array('FooBar', 'foo_bar'),
            array('Foo_Bar', 'foo.bar'),
            array('Foo_BarBaz', 'foo.bar_baz'),
            array('FooBar_BazQux', 'foo_bar.baz_qux'),
            array('_Foo', '.foo'),
            array('Foo_', 'foo.'),
        );
    }
}
