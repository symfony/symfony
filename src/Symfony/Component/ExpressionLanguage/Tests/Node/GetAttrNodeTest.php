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
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;

class GetAttrNodeTest extends AbstractNodeTestCase
{
    public static function getEvaluateData(): array
    {
        return [
            ['b', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), self::getArrayNode(), GetAttrNode::ARRAY_CALL), ['foo' => ['b' => 'a', 'b']]],
            ['a', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), self::getArrayNode(), GetAttrNode::ARRAY_CALL), ['foo' => ['b' => 'a', 'b']]],

            ['bar', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::PROPERTY_CALL), ['foo' => new Obj()]],

            ['baz', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::METHOD_CALL), ['foo' => new Obj()]],
            ['a', new GetAttrNode(new NameNode('foo'), new NameNode('index'), self::getArrayNode(), GetAttrNode::ARRAY_CALL), ['foo' => ['b' => 'a', 'b'], 'index' => 'b']],
        ];
    }

    public static function getCompileData(): array
    {
        return [
            ['$foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
            ['$foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['$foo->foo', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::PROPERTY_CALL), ['foo' => new Obj()]],

            ['$foo->foo(["b" => "a", 0 => "b"])', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::METHOD_CALL), ['foo' => new Obj()]],
            ['$foo[$index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
        ];
    }

    public static function getDumpData(): array
    {
        return [
            ['foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
            ['foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['foo.foo', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), self::getArrayNode(), GetAttrNode::PROPERTY_CALL), ['foo' => new Obj()]],

            ['foo.foo({"b": "a", 0: "b"})', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), self::getArrayNode(), GetAttrNode::METHOD_CALL), ['foo' => new Obj()]],
            ['foo[index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
        ];
    }

    protected static function getArrayNode(): ArrayNode
    {
        $array = new ArrayNode();
        $array->addElement(new ConstantNode('a'), new ConstantNode('b'));
        $array->addElement(new ConstantNode('b'));

        return $array;
    }
}

class Obj
{
    public $foo = 'bar';

    public function foo()
    {
        return 'baz';
    }
}
