<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\CssSelector\Node;

use Symfony\Components\CssSelector\Node\HashNode;
use Symfony\Components\CssSelector\Node\ElementNode;

class HashNodeTest extends \PHPUnit_Framework_TestCase
{
    public function testToXpath()
    {
        // h1#foo
        $element = new ElementNode('*', 'h1');
        $hash = new HashNode($element, 'foo');

        $this->assertEquals("h1[@id = 'foo']", (string) $hash->toXpath(), '->toXpath() returns the xpath representation of the node');
    }
}
