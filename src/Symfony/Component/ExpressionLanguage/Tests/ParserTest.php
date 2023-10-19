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
use Symfony\Component\ExpressionLanguage\SyntaxError;

class ParserTest extends TestCase
{
    public function testParseWithInvalidName()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1 for expression `foo`.');
        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->parse($lexer->tokenize('foo'));
    }

    public function testParseWithZeroInNames()
    {
        $this->expectException(SyntaxError::class);
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

    public static function getParseData()
    {
        $arguments = new Node\ArgumentsNode();
        $arguments->addElement(new Node\ConstantNode('arg1'));
        $arguments->addElement(new Node\ConstantNode(2));
        $arguments->addElement(new Node\ConstantNode(true));

        $arrayNode = new Node\ArrayNode();
        $arrayNode->addElement(new Node\NameNode('bar'));

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
            [
                new Node\BinaryNode('starts with', new Node\ConstantNode('foo'), new Node\ConstantNode('f')),
                '"foo" starts with "f"',
            ],
            [
                new Node\BinaryNode('ends with', new Node\ConstantNode('foo'), new Node\ConstantNode('f')),
                '"foo" ends with "f"',
            ],
            [
                new Node\BinaryNode('contains', new Node\ConstantNode('foo'), new Node\ConstantNode('f')),
                '"foo" contains "f"',
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true, true), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL),
                'foo?.bar',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true, true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo?.bar()',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('not', true, true), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo?.not()',
                ['foo'],
            ],
            [
                new Node\NullCoalesceNode(new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar', true), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL), new Node\ConstantNode('default')),
                'foo.bar ?? "default"',
                ['foo'],
            ],
            [
                new Node\NullCoalesceNode(new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode('bar'), new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL), new Node\ConstantNode('default')),
                'foo["bar"] ?? "default"',
                ['foo'],
            ],

            // chained calls
            [
                self::createGetAttrNode(
                    self::createGetAttrNode(
                        self::createGetAttrNode(
                            self::createGetAttrNode(new Node\NameNode('foo'), 'bar', Node\GetAttrNode::METHOD_CALL),
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

            // Operators collisions
            [
                new Node\BinaryNode(
                    'in',
                    new Node\GetAttrNode(
                        new Node\NameNode('foo'),
                        new Node\ConstantNode('not', true),
                        new Node\ArgumentsNode(),
                        Node\GetAttrNode::PROPERTY_CALL
                    ),
                    $arrayNode
                ),
                'foo.not in [bar]',
                ['foo', 'bar'],
            ],
            [
                new Node\BinaryNode(
                    'or',
                    new Node\UnaryNode('not', new Node\NameNode('foo')),
                    new Node\GetAttrNode(
                        new Node\NameNode('foo'),
                        new Node\ConstantNode('not', true),
                        new Node\ArgumentsNode(),
                        Node\GetAttrNode::PROPERTY_CALL
                    )
                ),
                'not foo or foo.not',
                ['foo'],
            ],
            [
                new Node\BinaryNode('..', new Node\ConstantNode(0), new Node\ConstantNode(3)),
                '0..3',
            ],
            [
                new Node\BinaryNode('+', new Node\ConstantNode(0), new Node\ConstantNode(0.1)),
                '0+.1',
            ],
        ];
    }

    private static function createGetAttrNode($node, $item, $type)
    {
        return new Node\GetAttrNode($node, new Node\ConstantNode($item, Node\GetAttrNode::ARRAY_CALL !== $type), new Node\ArgumentsNode(), $type);
    }

    /**
     * @dataProvider getInvalidPostfixData
     */
    public function testParseWithInvalidPostfixData($expr, $names = [])
    {
        $this->expectException(SyntaxError::class);
        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->parse($lexer->tokenize($expr), $names);
    }

    public static function getInvalidPostfixData()
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
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Did you mean "baz"?');
        $lexer = new Lexer();
        $parser = new Parser([]);

        $parser->parse($lexer->tokenize('foo > bar'), ['foo', 'baz']);
    }

    /**
     * @dataProvider getLintData
     */
    public function testLint($expression, $names, string $exception = null)
    {
        if ($exception) {
            $this->expectException(SyntaxError::class);
            $this->expectExceptionMessage($exception);
        }

        $lexer = new Lexer();
        $parser = new Parser([]);
        $parser->lint($lexer->tokenize($expression), $names);

        // Parser does't return anything when the correct expression is passed
        $this->expectNotToPerformAssertions();
    }

    public static function getLintData(): array
    {
        return [
            'valid expression' => [
                'expression' => 'foo["some_key"].callFunction(a ? b)',
                'names' => ['foo', 'a', 'b'],
            ],
            'valid expression with null safety' => [
                'expression' => 'foo["some_key"]?.callFunction(a ? b)',
                'names' => ['foo', 'a', 'b'],
            ],
            'allow expression without names' => [
                'expression' => 'foo.bar',
                'names' => null,
            ],
            'disallow expression without names' => [
                'expression' => 'foo.bar',
                'names' => [],
                'exception' => 'Variable "foo" is not valid around position 1 for expression `foo.bar',
            ],
            'operator collisions' => [
                'expression' => 'foo.not in [bar]',
                'names' => ['foo', 'bar'],
            ],
            'incorrect expression ending' => [
                'expression' => 'foo["a"] foo["b"]',
                'names' => ['foo'],
                'exception' => 'Unexpected token "name" of value "foo" '.
                    'around position 10 for expression `foo["a"] foo["b"]`.',
            ],
            'incorrect operator' => [
                'expression' => 'foo["some_key"] // 2',
                'names' => ['foo'],
                'exception' => 'Unexpected token "operator" of value "/" '.
                    'around position 18 for expression `foo["some_key"] // 2`.',
            ],
            'incorrect array' => [
                'expression' => '[value1, value2 value3]',
                'names' => ['value1', 'value2', 'value3'],
                'exception' => 'An array element must be followed by a comma. '.
                    'Unexpected token "name" of value "value3" ("punctuation" expected with value ",") '.
                    'around position 17 for expression `[value1, value2 value3]`.',
            ],
            'incorrect array element' => [
                'expression' => 'foo["some_key")',
                'names' => ['foo'],
                'exception' => 'Unclosed "[" around position 3 for expression `foo["some_key")`.',
            ],
            'missed array key' => [
                'expression' => 'foo[]',
                'names' => ['foo'],
                'exception' => 'Unexpected token "punctuation" of value "]" around position 5 for expression `foo[]`.',
            ],
            'missed closing bracket in sub expression' => [
                'expression' => 'foo[(bar ? bar : "default"]',
                'names' => ['foo', 'bar'],
                'exception' => 'Unclosed "(" around position 4 for expression `foo[(bar ? bar : "default"]`.',
            ],
            'incorrect hash following' => [
                'expression' => '{key: foo key2: bar}',
                'names' => ['foo', 'bar'],
                'exception' => 'A hash value must be followed by a comma. '.
                    'Unexpected token "name" of value "key2" ("punctuation" expected with value ",") '.
                    'around position 11 for expression `{key: foo key2: bar}`.',
            ],
            'incorrect hash assign' => [
                'expression' => '{key => foo}',
                'names' => ['foo'],
                'exception' => 'Unexpected character "=" around position 5 for expression `{key => foo}`.',
            ],
            'incorrect array as hash using' => [
                'expression' => '[foo: foo]',
                'names' => ['foo'],
                'exception' => 'An array element must be followed by a comma. '.
                    'Unexpected token "punctuation" of value ":" ("punctuation" expected with value ",") '.
                    'around position 5 for expression `[foo: foo]`.',
            ],
        ];
    }
}
