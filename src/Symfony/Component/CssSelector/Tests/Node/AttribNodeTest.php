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

use Symfony\Component\CssSelector\Node\AttribNode;
use Symfony\Component\CssSelector\Node\ElementNode;

class AttribNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        $element = new ElementNode('*', 'h1');

        $operators = array(
            '^=' => "h1[starts-with(@class, 'foo')]",
            '$=' => "h1[substring(@class, string-length(@class)-2) = 'foo']",
            '*=' => "h1[contains(@class, 'foo')]",
            '='  => "h1[@class = 'foo']",
            '~=' => "h1[contains(concat(' ', normalize-space(@class), ' '), ' foo ')]",
            '|=' => "h1[@class = 'foo' or starts-with(@class, 'foo-')]",
            '!=' => "h1[not(@class) or @class != 'foo']",
        );

        // h1[class??foo]
        foreach ($operators as $op => $xpath) {
            $attrib = new AttribNode($element, '*', 'class', $op, 'foo');
            $this->assertEquals($xpath, (string) $attrib->toXpath(), '->toXpath() returns the xpath representation of the node');
        }

        // h1[class]
        $attrib = new AttribNode($element, '*', 'class', 'exists', 'foo');
        $this->assertEquals('h1[@class]', (string) $attrib->toXpath(), '->toXpath() returns the xpath representation of the node');
    }
}
