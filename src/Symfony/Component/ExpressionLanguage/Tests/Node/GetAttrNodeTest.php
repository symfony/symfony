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

class GetAttrNodeTest extends AbstractNodeTest
{
    public function getEvaluateData()
    {
        return array(
            array('b', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), $this->getArrayNode(), GetAttrNode::ARRAY_CALL), array('foo' => array('b' => 'a', 'b'))),
            array('a', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL), array('foo' => array('b' => 'a', 'b'))),

            array('bar', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), $this->getArrayNode(), GetAttrNode::PROPERTY_CALL), array('foo' => new Obj())),

            array('baz', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), $this->getArrayNode(), GetAttrNode::METHOD_CALL), array('foo' => new Obj())),
            array('a', new GetAttrNode(new NameNode('foo'), new NameNode('index'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL), array('foo' => array('b' => 'a', 'b'), 'index' => 'b')),
        );
    }

    public function getCompileData()
    {
        return array(
            array('$foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),
            array('$foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),

            array('$foo->foo', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), $this->getArrayNode(), GetAttrNode::PROPERTY_CALL), array('foo' => new Obj())),

            array('$foo->foo(array("b" => "a", 0 => "b"))', new GetAttrNode(new NameNode('foo'), new ConstantNode('foo'), $this->getArrayNode(), GetAttrNode::METHOD_CALL), array('foo' => new Obj())),
            array('$foo[$index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),
        );
    }

    public function getDumpData()
    {
        return array(
            array('foo[0]', new GetAttrNode(new NameNode('foo'), new ConstantNode(0), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),
            array('foo["b"]', new GetAttrNode(new NameNode('foo'), new ConstantNode('b'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),

            array('foo.foo', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), $this->getArrayNode(), GetAttrNode::PROPERTY_CALL), array('foo' => new Obj())),

            array('foo.foo({"b": "a", 0: "b"})', new GetAttrNode(new NameNode('foo'), new NameNode('foo'), $this->getArrayNode(), GetAttrNode::METHOD_CALL), array('foo' => new Obj())),
            array('foo[index]', new GetAttrNode(new NameNode('foo'), new NameNode('index'), $this->getArrayNode(), GetAttrNode::ARRAY_CALL)),
        );
    }

    protected function getArrayNode()
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
