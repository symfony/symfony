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

use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class ArrayNodeTest extends AbstractNodeTest
{
    public function testSerialization()
    {
        $node = $this->createArrayNode();
        $node->addElement(new ConstantNode('foo'));

        $serializedNode = serialize($node);
        $unserializedNode = unserialize($serializedNode);

        $this->assertEquals($node, $unserializedNode);
        $this->assertNotEquals($this->createArrayNode(), $unserializedNode);
    }

    public function getEvaluateData()
    {
        return [
            [['b' => 'a', 'b'], $this->getArrayNode()],
        ];
    }

    public function getCompileData()
    {
        return [
            ['["b" => "a", 0 => "b"]', $this->getArrayNode()],
        ];
    }

    public function getDumpData()
    {
        yield ['{"b": "a", 0: "b"}', $this->getArrayNode()];

        $array = $this->createArrayNode();
        $array->addElement(new ConstantNode('c'), new ConstantNode('a"b'));
        $array->addElement(new ConstantNode('d'), new ConstantNode('a\b'));
        yield ['{"a\\"b": "c", "a\\\\b": "d"}', $array];

        $array = $this->createArrayNode();
        $array->addElement(new ConstantNode('c'));
        $array->addElement(new ConstantNode('d'));
        yield ['["c", "d"]', $array];
    }

    protected function getArrayNode()
    {
        $array = $this->createArrayNode();
        $array->addElement(new ConstantNode('a'), new ConstantNode('b'));
        $array->addElement(new ConstantNode('b'));

        return $array;
    }

    protected function createArrayNode()
    {
        return new ArrayNode();
    }
}
