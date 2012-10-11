<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\HttpFoundation\AcceptHeaderParser;

class AcceptHeaderParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider splitData
     */
    public function testSplitHttp($acceptHeader, $expected)
    {
        $parser = new AcceptHeaderParser();
        $this->assertEquals($expected, $parser->split($acceptHeader));
    }

    public function splitData()
    {
        return array(
            array(null, array()),
            array('text/html;q=0.8', array('text/html' => 0.8)),
            array('text/html;foo=bar;q=0.8 ', array('text/html;foo=bar' => 0.8)),
            array('text/html;charset=utf-8; q=0.8', array('text/html;charset=utf-8' => 0.8)),
            array('text/html,application/xml;q=0.9,*/*;charset=utf-8; q=0.8', array('text/html' => 1, 'application/xml' => 0.9, '*/*;charset=utf-8' => 0.8)),
            array('text/html,application/xhtml+xml;q=0.9,*/*;q=0.8; foo=bar', array('text/html' => 1, 'application/xhtml+xml' => 0.9, '*/*' => 0.8)),
            array('text/html,application/xhtml+xml;charset=utf-8;q=0.9; foo=bar,*/*', array('text/html' => 1, '*/*' => 1, 'application/xhtml+xml;charset=utf-8' => 0.9)),
            array('text/html,application/xhtml+xml', array('application/xhtml+xml' => 1, 'text/html' => 1)),
        );
    }
}
