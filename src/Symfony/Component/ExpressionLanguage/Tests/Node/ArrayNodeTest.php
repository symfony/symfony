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

class ArrayNodeTest extends AbstractNodeTestCase
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

    public static function getEvaluateData(): array
    {
        return [
            [['b' => 'a', 'b'], static::getArrayNode()],
        ];
    }

    public static function getCompileData(): array
    {
        return [
            ['["b" => "a", 0 => "b"]', static::getArrayNode()],
        ];
    }

    public static function getDumpData(): \Generator
    {
        yield ['{"b": "a", 0: "b"}', static::getArrayNode()];

        $array = static::createArrayNode();
        $array->addElement(new ConstantNode('c'), new ConstantNode('a"b'));
        $array->addElement(new ConstantNode('d'), new ConstantNode('a\b'));
        yield ['{"a\\"b": "c", "a\\\\b": "d"}', $array];

        $array = static::createArrayNode();
        $array->addElement(new ConstantNode('c'));
        $array->addElement(new ConstantNode('d'));
        yield ['["c", "d"]', $array];
    }

    protected static function getArrayNode(): ArrayNode
    {
        $array = static::createArrayNode();
        $array->addElement(new ConstantNode('a'), new ConstantNode('b'));
        $array->addElement(new ConstantNode('b'));

        return $array;
    }

    protected static function createArrayNode(): ArrayNode
    {
        return new ArrayNode();
    }
}
