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

use Symfony\Component\CssSelector\Node\OrNode;
use Symfony\Component\CssSelector\Node\ElementNode;

class OrNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        // h1, h2, h3
        $element1 = new ElementNode('*', 'h1');
        $element2 = new ElementNode('*', 'h2');
        $element3 = new ElementNode('*', 'h3');
        $or = new OrNode(array($element1, $element2, $element3));

        $this->assertEquals("h1 | h2 | h3", (string) $or->toXpath(), '->toXpath() returns the xpath representation of the node');
    }

    public function testIssueMissingPrefix()
    {
        // h1, h2, h3
        $element1 = new ElementNode('*', 'h1');
        $element2 = new ElementNode('*', 'h2');
        $element3 = new ElementNode('*', 'h3');
        $or = new OrNode(array($element1, $element2, $element3));

        $xPath = $or->toXPath();
        $xPath->addPrefix('descendant-or-self::');

        $this->assertEquals("descendant-or-self::h1 | descendant-or-self::h2 | descendant-or-self::h3", (string) $xPath);
    }
}
