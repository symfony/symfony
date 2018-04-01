<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\ExpressionLanguage\Tests\Node;

use PHPUnit\Framework\TestCase;
use Symphony\Component\ExpressionLanguage\Node\Node;
use Symphony\Component\ExpressionLanguage\Node\ConstantNode;

class NodeTest extends TestCase
{
    public function testToString()
    {
        $node = new Node(array(new ConstantNode('foo')));

        $this->assertEquals(<<<'EOF'
Node(
    ConstantNode(value: 'foo')
)
EOF
        , (string) $node);
    }

    public function testSerialization()
    {
        $node = new Node(array('foo' => 'bar'), array('bar' => 'foo'));

        $serializedNode = serialize($node);
        $unserializedNode = unserialize($serializedNode);

        $this->assertEquals($node, $unserializedNode);
    }
}
