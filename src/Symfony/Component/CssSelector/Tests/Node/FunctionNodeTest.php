<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\CssSelector;

class FunctionNodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider testToXPathProvider
     **/
    public function testToXPath($input, $expected)
    {
        $this->assertEquals($expected, CssSelector::toXPath($input, ''));
    }
    public function testToXPathProvider()
    {
        return array(
            array(
                'h1:contains("foo")',
                "h1[contains(string(.), 'foo')]"
            ),
            array(
                'h1:nth-child(1)',
                "*/*[name() = 'h1' and (position() = 1)]"
            ),
            array(
                'h1:nth-child()',
                "h1[false() and position() = 0]"
            ),
            array(
                'h1:nth-child(odd)',
                "*/*[name() = 'h1' and ((position() >= 1) and (((position() - 1) mod 2) = 0))]"
            ),
            array(
                'h1:nth-child(even)',
                "*/*[name() = 'h1' and ((position() mod 2) = 0)]"
            ),
            array(
                'h1:nth-child(n)',
                "*/*[name() = 'h1' and ((position() mod 1) = 0)]"
            ),
            array(
                'h1:nth-child(3n+1)',
                "*/*[name() = 'h1' and ((position() >= 1) and (((position() - 1) mod 3) = 0))]"
            ),
            array(
                'h1:nth-child(n+1)',
                "*/*[name() = 'h1' and ((position() >= 1) and (((position() - 1) mod 1) = 0))]"
            ),
            array(
                'h1:nth-child(2n)',
                "*/*[name() = 'h1' and ((position() mod 2) = 0)]"
            ),
            array(
                'h1:nth-child(-n)',
                "*/*[name() = 'h1' and ((position() mod -1) = 0)]"
            ),
            array(
                'h1:nth-child(-1n+3)',
                "*/*[name() = 'h1' and ((position() <= 3) and (((position() - 3) mod 1) = 0))]"
            ),
            array(
                'h1:nth-last-child(2)',
                "*/*[name() = 'h1' and (position() = last() - 1)]"
            ),
            array(
                'h1:nth-of-type(2)', "*/h1[position() = 2]"
            ),
            array(
                'h1:nth-last-of-type(2)', "*/h1[position() = last() - 1]"
            ),
            // Negation
            array(
                'h1:not(#foo)', "h1[not(@id = 'foo')]"
            ),
            array(
              '*:not(p)', "*[not(name() = 'p')]"
            ),
            array(
                '*:not(html|p)', "*[not(name() = 'html:p')]"
            ),
        );
    }
}
