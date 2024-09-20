<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\Node;

class NodeTest extends TestCase
{
    public function testToString()
    {
        $node = new Node([new ConstantNode('foo')]);

        $this->assertEquals(<<<'EOF'
Node(
    ConstantNode(value: 'foo')
)
EOF
            , (string) $node);
    }

    public function testSerialization()
    {
        $node = new Node(['foo' => 'bar'], ['bar' => 'foo']);

        $serializedNode = serialize($node);
        $unserializedNode = unserialize($serializedNode);

        $this->assertEquals($node, $unserializedNode);
    }

    public function testCompileActuallyCompilesAllNodes()
    {
        $nodes = [];
        foreach (range(1, 10) as $ignored) {
            $node = $this->createMock(Node::class);
            $node->expects($this->once())->method('compile');

            $nodes[] = $node;
        }

        $node = new Node($nodes);
        $node->compile($this->createMock(Compiler::class));
    }

    public function testEvaluateActuallyEvaluatesAllNodes()
    {
        $nodes = [];
        foreach (range(1, 3) as $i) {
            $node = $this->createMock(Node::class);
            $node->expects($this->once())->method('evaluate')
                ->willReturn($i);

            $nodes[] = $node;
        }

        $node = new Node($nodes);
        $this->assertSame([1, 2, 3], $node->evaluate([], []));
    }
}
