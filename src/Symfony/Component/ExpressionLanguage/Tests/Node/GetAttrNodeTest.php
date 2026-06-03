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

use Symfony\Component\ExpressionLanguage\Node\ArgumentsNode;
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
            [null, new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true), ['foo' => null]],

            // null-safe array access against an \ArrayAccess receiver: helper returns it as-is.
            ['v', new GetAttrNode(new NameNode('foo'), new ConstantNode('k'), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true), ['foo' => new \ArrayObject(['k' => 'v'])]],

            // nested null-safe array access on a present 2D structure.
            ['x', new GetAttrNode(new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true), new ConstantNode(1), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true), ['foo' => [['a', 'x']]]],
        ];
    }

    public function testEvaluateNullSafeArrayOnNonArrayThrows()
    {
        $node = new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to get an item of non-array "foo".');

        $node->evaluate([], ['foo' => 42]);
    }

    public function testUnserializeMissingIsNullSafeDefaultsToFalse()
    {
        // Mimic a payload serialized before 8.1: no `is_null_safe` key in attributes.
        $data = [
            'nodes' => [
                'node' => new NameNode('foo'),
                'attribute' => new ConstantNode(0),
                'arguments' => new ArgumentsNode(),
            ],
            'attributes' => [
                'type' => GetAttrNode::ARRAY_CALL,
                'is_null_coalesce' => false,
                'is_short_circuited' => false,
            ],
        ];

        $restored = (new \ReflectionClass(GetAttrNode::class))->newInstanceWithoutConstructor();
        $restored->__unserialize($data);

        $this->assertSame('foo[0]', $restored->dump());
        $this->assertSame('b', $restored->evaluate([], ['foo' => ['b' => 'a', 'b']]));
    }

    public static function getCompileData(): array
    {
        return [
            ['$foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
            ['$foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['$foo->foo', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::PROPERTY_CALL), ['foo' => new Obj()]],

            ['$foo->foo(["b" => "a", 0 => "b"])', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), self::getArrayNode(), GetAttrNode::METHOD_CALL), ['foo' => new Obj()]],
            ['$foo[$index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['\\'.GetAttrNode::class.'::convertToArrayAccess($foo, "foo")?->offsetGet(0)', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true)],
            ['\\'.GetAttrNode::class.'::convertToArrayAccess(\\'.GetAttrNode::class.'::convertToArrayAccess($foo, "foo")?->offsetGet(0), "foo?.[0]")?->offsetGet(1)', new GetAttrNode(new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true), new ConstantNode(1), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true)],
        ];
    }

    public static function getDumpData(): array
    {
        return [
            ['foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],
            ['foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['foo.foo', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), self::getArrayNode(), GetAttrNode::PROPERTY_CALL)],

            ['foo.foo({"b": "a", 0: "b"})', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), self::getArrayNode(), GetAttrNode::METHOD_CALL)],
            ['foo[index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), self::getArrayNode(), GetAttrNode::ARRAY_CALL)],

            ['foo?.foo()', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo', true, true), new ArgumentsNode(), GetAttrNode::METHOD_CALL)],
            ['foo?.[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), new ArgumentsNode(), GetAttrNode::ARRAY_CALL, true)],
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
