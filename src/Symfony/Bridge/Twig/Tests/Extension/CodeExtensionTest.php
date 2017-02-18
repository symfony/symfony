<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\CodeExtension;
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;

class CodeExtensionTest extends TestCase
{
    protected $helper;

    public function testFormatFile()
    {
        $expected = sprintf('<a href="proto://foobar%s#&amp;line=25" title="Click to open this file" class="file_link">%s at line 25</a>', substr(__FILE__, 5), __FILE__);
        $this->assertEquals($expected, $this->getExtension()->formatFile(__FILE__, 25));
    }

    /**
     * @dataProvider getClassNameProvider
     */
    public function testGettingClassAbbreviation($class, $abbr)
    {
        $this->assertEquals($this->getExtension()->abbrClass($class), $abbr);
    }

    /**
     * @dataProvider getMethodNameProvider
     */
    public function testGettingMethodAbbreviation($method, $abbr)
    {
        $this->assertEquals($this->getExtension()->abbrMethod($method), $abbr);
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
            array('Method', '<abbr title="Method">Method</abbr>()'),
        );
    }

    public function testGetName()
    {
        $this->assertEquals('code', $this->getExtension()->getName());
    }

    protected function getExtension()
    {
        return new CodeExtension(new FileLinkFormatter('proto://%f#&line=%l&'.substr(__FILE__, 0, 5).'>foobar'), '/root', 'UTF-8');
    }
}
