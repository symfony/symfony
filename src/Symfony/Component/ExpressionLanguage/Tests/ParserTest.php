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
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\Node;
use Symfony\Component\ExpressionLanguage\Parser;

class ParserTest extends TestCase
{
    public function testParseWithInvalidName()
    {
        $this->expectException('Symfony\Component\ExpressionLanguage\SyntaxError');
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1 for expression `foo`.');
        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->parse($lexer->tokenize('foo'));
    }

    public function testParseWithZeroInNames()
    {
        $this->expectException('Symfony\Component\ExpressionLanguage\SyntaxError');
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1 for expression `foo`.');
        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->parse($lexer->tokenize('foo'), [0]);
    }

    /**
     * @dataProvider getParseData
     */
    public function testParse($node, $expression, $names = [])
    {
        $lexer = new Lexer();
        $parser = new Parser([]);
        $this->assertEquals($node, $parser->parse($lexer->tokenize($expression), $names));
    }

    public function getParseData()
    {
        $arguments = new Node\ArgumentsNode();
        $arguments->addElement(new Node\ConstantNode('arg1'));
        $arguments->addElement(new Node\ConstantNode(2));
        $arguments->addElement(new Node\ConstantNode(true));

        return [
            [
                new Node\NameNode('a'),
                'a',
                ['a'],
            ],
            [
                new Node\ConstantNode('a'),
                '"a"',
            ],
            [
                new Node\ConstantNode(3),
                '3',
            ],
            [
                new Node\ConstantNode(false),
                'false',
            ],
            [
                new Node\ConstantNode(true),
                'true',
            ],
            [
                new Node\ConstantNode(null),
                'null',
            ],
            [
                new Node\UnaryNode('-', new Node\ConstantNode(3)),
                '-3',
            ],
            [
                new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                '3 - 3',
            ],
            [
                new Node\BinaryNode('*',
                    new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                    new Node\ConstantNode(2)
                ),
                '(3 - 3) * 2',
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL),
                'foo.bar',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.bar()',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('not', true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.not()',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(
                    new Node\NameNode('foo'),
                    new Node\ConstantNode('bar', true),
                    $arguments,
                    Node\GetAttrNode::METHOD_CALL
                ),
                'foo.bar("arg1", 2, true)',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode(3), new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL),
                'foo[3]',
                ['foo'],
            ],
            [
                new Node\ConditionalNode(new Node\ConstantNode(true), new Node\ConstantNode(true), new Node\ConstantNode(false)),
                'true ? true : false',
            ],
            [
                new Node\BinaryNode('matches', new Node\ConstantNode('foo'), new Node\ConstantNode('/foo/')),
                '"foo" matches "/foo/"',
            ],

            // chained calls
            [
                $this->createGetAttrNode(
                    $this->createGetAttrNode(
                        $this->createGetAttrNode(
                            $this->createGetAttrNode(new Node\NameNode('foo'), 'bar', Node\GetAttrNode::METHOD_CALL),
                            'foo', Node\GetAttrNode::METHOD_CALL),
                        'baz', Node\GetAttrNode::PROPERTY_CALL),
                    '3', Node\GetAttrNode::ARRAY_CALL),
                'foo.bar().foo().baz[3]',
                ['foo'],
            ],

            [
                new Node\NameNode('foo'),
                'bar',
                ['foo' => 'bar'],
            ],
        ];
    }

    private function createGetAttrNode($node, $item, $type)
    {
        return new Node\GetAttrNode($node, new Node\ConstantNode($item, Node\GetAttrNode::ARRAY_CALL !== $type), new Node\ArgumentsNode(), $type);
    }

    /**
     * @dataProvider getInvalidPostfixData
     */
    public function testParseWithInvalidPostfixData($expr, $names = [])
    {
        $this->expectException('Symfony\Component\ExpressionLanguage\SyntaxError');
        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->parse($lexer->tokenize($expr), $names);
    }

    public function getInvalidPostfixData()
    {
        return [
            [
                'foo."#"',
                ['foo'],
            ],
            [
                'foo."bar"',
                ['foo'],
            ],
            [
                'foo.**',
                ['foo'],
            ],
            [
                'foo.123',
                ['foo'],
            ],
        ];
    }

    public function testNameProposal()
    {
        $this->expectException('Symfony\Component\ExpressionLanguage\SyntaxError');
        $this->expectExceptionMessage('Did you mean "baz"?');
        $lexer = new Lexer();
        $parser = new Parser([]);

        $parser->parse($lexer->tokenize('foo > bar'), ['foo', 'baz']);
    }
}
