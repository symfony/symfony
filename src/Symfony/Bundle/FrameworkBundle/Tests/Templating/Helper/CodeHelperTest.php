<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\CodeHelper;

class CodeHelperTest extends \PHPUnit_Framework_TestCase
{
    protected static $helper;

    public static function setUpBeforeClass()
    {
        self::$helper = new CodeHelper('format', '/root');
    }

    /**
     * @dataProvider getClassNameProvider
     */
    public function testGettingClassAbbreviation($class, $abbr)
    {
        $this->assertEquals(self::$helper->abbrClass($class), $abbr);
    }

    /**
     * @dataProvider getMethodNameProvider
     */
    public function testGettingMethodAbbreviation($method, $abbr)
    {
        $this->assertEquals(self::$helper->abbrMethod($method), $abbr);
    }

    public function getClassNameProvider()
    {
        return array(
            array('F\Q\N\Foo', '<abbr title="F\Q\N\Foo">Foo</abbr>'),
            array('Bare', '<abbr title="Bare">Bare</abbr>'),
        );
    }

    public function getMethodNameProvider()
    {
        return array(
            array('F\Q\N\Foo::Method', '<abbr title="F\Q\N\Foo">Foo</abbr>::Method()'),
            array('Bare::Method', '<abbr title="Bare">Bare</abbr>::Method()'),
            array('Closure', '<abbr title="Closure">Closure</abbr>'),
            array('Method', '<abbr title="Method">Method</abbr>()')
        );
    }
}
