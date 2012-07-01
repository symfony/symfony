<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Symfony\Component\Translation\Gettext;

/**
 * Description of GettextText
 *
 * @author clemens
 */
class GettextTest extends \PHPUnit_Framework_TestCase
{
    public function testHeaderToString()
    {
        $actual = Gettext::headerToString(array());
        $this->assertEquals(NULL, $actual, 'No header.');
        $header = array("A" => "B", "C" => "D");
        $actual = Gettext::headerToString($header);
        $expected = implode("\n", array('msgid ""', 'msgstr ""', '"A: B\n"','"C: D\n"'));
        $this->assertEquals($expected, $actual, 'Header string ok');
    }

    public function testValidHeader()
    {
        $header = Gettext::emptyHeader();
        $this->assertEquals(Gettext::headerKeys(), array_keys($header));
        $this->assertEquals("", implode('', $header));
    }

    public function testIdentityHeader()
    {
        // Make sure header keeps the same
        $header = Gettext::emptyHeader();
        $resource = __DIR__.'/fixtures/empty-header.po';
        $this->assertEquals(file_get_contents($resource), Gettext::headerToString($header), 'Header from file maps to internal version');
    }

    public function testNoHeaderExists()
    {
        $messages = array();
        $header = Gettext::getHeader($messages);
        $this->assertEquals(array(), $header, "Empty header is empty array");
    }

}
