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

use Symfony\Component\CssSelector\Node\ClassNode;
use Symfony\Component\CssSelector\Node\ElementNode;

class ClassNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        // h1.foo
        $element = new ElementNode('*', 'h1');
        $class = new ClassNode($element, 'foo');

        $this->assertEquals("h1[contains(concat(' ', normalize-space(@class), ' '), ' foo ')]", (string) $class->toXpath(), '->toXpath() returns the xpath representation of the node');
    }
}
