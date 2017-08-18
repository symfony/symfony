<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Parser;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\Node;

class ParserTest extends TestCase
{
    /**
     * @expectedException        \Symfony\Component\ExpressionLanguage\SyntaxError
     * @expectedExceptionMessage Variable "foo" is not valid around position 1 for expression `foo`.
     */
    public function testParseWithInvalidName()
    {
        $lexer = new Lexer();
        $parser = new Parser(array());
        $parser->parse($lexer->tokenize('foo'));
    }

    /**
     * @expectedException        \Symfony\Component\ExpressionLanguage\SyntaxError
     * @expectedExceptionMessage Variable "foo" is not valid around position 1 for expression `foo`.
     */
    public function testParseWithZeroInNames()
    {
        $lexer = new Lexer();
        $parser = new Parser(array());
        $parser->parse($lexer->tokenize('foo'), array(0));
    }

    /**
     * @dataProvider getParseData
     */
    public function testParse($node, $expression, $names = array())
    {
        $lexer = new Lexer();
        $parser = new Parser(array());
        $this->assertEquals($node, $parser->parse($lexer->tokenize($expression), $names));
    }

    public function getParseData()
    {
        $arguments = new Node\ArgumentsNode();
        $arguments->addElement(new Node\ConstantNode('arg1'));
        $arguments->addElement(new Node\ConstantNode(2));
        $arguments->addElement(new Node\ConstantNode(true));

        return array(
            array(
                new Node\NameNode('a'),
                'a',
                array('a'),
            ),
            array(
                new Node\ConstantNode('a'),
                '"a"',
            ),
            array(
                new Node\ConstantNode(3),
                '3',
            ),
            array(
                new Node\ConstantNode(false),
                'false',
            ),
            array(
                new Node\ConstantNode(true),
                'true',
            ),
            array(
                new Node\ConstantNode(null),
                'null',
            ),
            array(
                new Node\UnaryNode('-', new Node\ConstantNode(3)),
                '-3',
            ),
            array(
                new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                '3 - 3',
            ),
            array(
                new Node\BinaryNode('*',
                    new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                    new Node\ConstantNode(2)
                ),
                '(3 - 3) * 2',
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL),
                'foo.bar',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.bar()',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('not', true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.not()',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(
                    new Node\NameNode('foo'),
                    new Node\ConstantNode('bar', true),
                    $arguments,
                    Node\GetAttrNode::METHOD_CALL
                ),
                'foo.bar("arg1", 2, true)',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode(3), new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL),
                'foo[3]',
                array('foo'),
            ),
            array(
                new Node\ConditionalNode(new Node\ConstantNode(true), new Node\ConstantNode(true), new Node\ConstantNode(false)),
                'true ? true : false',
            ),
            array(
                new Node\BinaryNode('matches', new Node\ConstantNode('foo'), new Node\ConstantNode('/foo/')),
                '"foo" matches "/foo/"',
            ),

            // chained calls
            array(
                $this->createGetAttrNode(
                    $this->createGetAttrNode(
                        $this->createGetAttrNode(
                            $this->createGetAttrNode(new Node\NameNode('foo'), 'bar', Node\GetAttrNode::METHOD_CALL),
                            'foo', Node\GetAttrNode::METHOD_CALL),
                        'baz', Node\GetAttrNode::PROPERTY_CALL),
                    '3', Node\GetAttrNode::ARRAY_CALL),
                'foo.bar().foo().baz[3]',
                array('foo'),
            ),

            array(
                new Node\NameNode('foo'),
                'bar',
                array('foo' => 'bar'),
            ),
        );
    }

    private function createGetAttrNode($node, $item, $type)
    {
        return new Node\GetAttrNode($node, new Node\ConstantNode($item, Node\GetAttrNode::ARRAY_CALL !== $type), new Node\ArgumentsNode(), $type);
    }

    /**
     * @dataProvider getInvalidPostfixData
     * @expectedException \Symfony\Component\ExpressionLanguage\SyntaxError
     */
    public function testParseWithInvalidPostfixData($expr, $names = array())
    {
        $lexer = new Lexer();
        $parser = new Parser(array());
        $parser->parse($lexer->tokenize($expr), $names);
    }

    public function getInvalidPostfixData()
    {
        return array(
            array(
                'foo."#"',
                array('foo'),
            ),
            array(
                'foo."bar"',
                array('foo'),
            ),
            array(
                'foo.**',
                array('foo'),
            ),
            array(
                'foo.123',
                array('foo'),
            ),
        );
    }
}
