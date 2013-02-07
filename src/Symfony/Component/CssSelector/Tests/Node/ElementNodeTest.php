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

use Symfony\Component\CssSelector\Node\ElementNode;

class ElementNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        // h1
        $element = new ElementNode('*', 'h1');

        $this->assertEquals('h1', (string) $element->toXpath(), '->toXpath() returns the xpath representation of the node');

        // foo|h1
        $element = new ElementNode('foo', 'h1');

        $this->assertEquals('foo:h1', (string) $element->toXpath(), '->toXpath() returns the xpath representation of the node');
    }
}
