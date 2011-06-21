<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\CssSelector\Node;

use Symfony\Component\CssSelector\Node\PseudoNode;
use Symfony\Component\CssSelector\Node\ElementNode;

class PseudoNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        $element = new ElementNode('*', 'h1');

        // h1:checked
        $pseudo = new PseudoNode($element, ':', 'checked');
        $this->assertEquals("h1[(@selected or @checked) and (name(.) = 'input' or name(.) = 'option')]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:first-child
        $pseudo = new PseudoNode($element, ':', 'first-child');
        $this->assertEquals("*/*[name() = 'h1' and (position() = 1)]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:last-child
        $pseudo = new PseudoNode($element, ':', 'last-child');
        $this->assertEquals("*/*[name() = 'h1' and (position() = last())]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:first-of-type
        $pseudo = new PseudoNode($element, ':', 'first-of-type');
        $this->assertEquals("*/h1[position() = 1]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:last-of-type
        $pseudo = new PseudoNode($element, ':', 'last-of-type');
        $this->assertEquals("*/h1[position() = last()]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:only-child
        $pseudo = new PseudoNode($element, ':', 'only-child');
        $this->assertEquals("*/*[name() = 'h1' and (last() = 1)]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:only-of-type
        $pseudo = new PseudoNode($element, ':', 'only-of-type');
        $this->assertEquals("h1[last() = 1]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');

        // h1:empty
        $pseudo = new PseudoNode($element, ':', 'empty');
        $this->assertEquals("h1[not(*) and not(normalize-space())]", (string) $pseudo->toXpath(), '->toXpath() returns the xpath representation of the node');
    }
}
