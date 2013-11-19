<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Parsers a token stream.
 *
 * This parser implements a "Precedence climbing" algorithm.
 *
 * @see http://www.engr.mun.ca/~theo/Misc/exp_parsing.htm
 * @see http://en.wikipedia.org/wiki/Operator-precedence_parser
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parser
{
    const OPERATOR_LEFT = 1;
    const OPERATOR_RIGHT = 2;

    private $stream;
    private $unaryOperators;
    private $binaryOperators;
    private $functions;
    private $names;

    public function __construct(array $functions)
    {
        $this->functions = $functions;

        $this->unaryOperators = array(
            'not' => array('precedence' => 50),
            '!'   => array('precedence' => 50),
            '-'   => array('precedence' => 500),
            '+'   => array('precedence' => 500),
        );
        $this->binaryOperators = array(
            'or'      => array('precedence' => 10,  'associativity' => Parser::OPERATOR_LEFT),
            '||'      => array('precedence' => 10,  'associativity' => Parser::OPERATOR_LEFT),
            'and'     => array('precedence' => 15,  'associativity' => Parser::OPERATOR_LEFT),
            '&&'      => array('precedence' => 15,  'associativity' => Parser::OPERATOR_LEFT),
            '|'       => array('precedence' => 16,  'associativity' => Parser::OPERATOR_LEFT),
            '^'       => array('precedence' => 17,  'associativity' => Parser::OPERATOR_LEFT),
            '&'       => array('precedence' => 18,  'associativity' => Parser::OPERATOR_LEFT),
            '=='      => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '==='     => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '!='      => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '!=='     => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '<'       => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '>'       => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '>='      => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '<='      => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            'not in'  => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            'in'      => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            'matches' => array('precedence' => 20,  'associativity' => Parser::OPERATOR_LEFT),
            '..'      => array('precedence' => 25,  'associativity' => Parser::OPERATOR_LEFT),
            '+'       => array('precedence' => 30,  'associativity' => Parser::OPERATOR_LEFT),
            '-'       => array('precedence' => 30,  'associativity' => Parser::OPERATOR_LEFT),
            '~'       => array('precedence' => 40,  'associativity' => Parser::OPERATOR_LEFT),
            '*'       => array('precedence' => 60,  'associativity' => Parser::OPERATOR_LEFT),
            '/'       => array('precedence' => 60,  'associativity' => Parser::OPERATOR_LEFT),
            '%'       => array('precedence' => 60,  'associativity' => Parser::OPERATOR_LEFT),
            '**'      => array('precedence' => 200, 'associativity' => Parser::OPERATOR_RIGHT),
        );
    }

    /**
     * Converts a token stream to a node tree.
     *
     * @param TokenStream $stream A token stream instance
     * @param array       $names  An array of valid names
     *
     * @return Node A node tree
     *
     * @throws SyntaxError
     */
    public function parse(TokenStream $stream, $names = array())
    {
        $this->stream = $stream;
        $this->names = $names;

        $node = $this->parseExpression();
        if (!$stream->isEOF()) {
            throw new SyntaxError(sprintf('Unexpected token "%s" of value "%s"', $stream->current->type, $stream->current->value), $stream->current->cursor);
        }

        return $node;
    }

    public function parseExpression($precedence = 0)
    {
        $expr = $this->getPrimary();
        $token = $this->stream->current;
        while ($token->test(Token::OPERATOR_TYPE) && isset($this->binaryOperators[$token->value]) && $this->binaryOperators[$token->value]['precedence'] >= $precedence) {
            $op = $this->binaryOperators[$token->value];
            $this->stream->next();

            $expr1 = $this->parseExpression(self::OPERATOR_LEFT === $op['associativity'] ? $op['precedence'] + 1 : $op['precedence']);
            $expr = new Node\BinaryNode($token->value, $expr, $expr1);

            $token = $this->stream->current;
        }

        if (0 === $precedence) {
            return $this->parseConditionalExpression($expr);
        }

        return $expr;
    }

    protected function getPrimary()
    {
        $token = $this->stream->current;

        if ($token->test(Token::OPERATOR_TYPE) && isset($this->unaryOperators[$token->value])) {
            $operator = $this->unaryOperators[$token->value];
            $this->stream->next();
            $expr = $this->parseExpression($operator['precedence']);

            return $this->parsePostfixExpression(new Node\UnaryNode($token->value, $expr));
        }

        if ($token->test(Token::PUNCTUATION_TYPE, '(')) {
            $this->stream->next();
            $expr = $this->parseExpression();
            $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');

            return $this->parsePostfixExpression($expr);
        }

        return $this->parsePrimaryExpression();
    }

    protected function parseConditionalExpression($expr)
    {
        while ($this->stream->current->test(Token::PUNCTUATION_TYPE, '?')) {
            $this->stream->next();
            if (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                $expr2 = $this->parseExpression();
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                    $this->stream->next();
                    $expr3 = $this->parseExpression();
                } else {
                    $expr3 = new Node\ConstantNode(null);
                }
            } else {
                $this->stream->next();
                $expr2 = $expr;
                $expr3 = $this->parseExpression();
            }

            $expr = new Node\ConditionalNode($expr, $expr2, $expr3);
        }

        return $expr;
    }

    public function parsePrimaryExpression()
    {
        $token = $this->stream->current;
        switch ($token->type) {
            case Token::NAME_TYPE:
                $this->stream->next();
                switch ($token->value) {
                    case 'true':
                    case 'TRUE':
                        return new Node\ConstantNode(true);

                    case 'false':
                    case 'FALSE':
                        return new Node\ConstantNode(false);

                    case 'null':
                    case 'NULL':
                        return new Node\ConstantNode(null);

                    default:
                        if ('(' === $this->stream->current->value) {
                            if (false === isset($this->functions[$token->value])) {
                                throw new SyntaxError(sprintf('The function "%s" does not exist', $token->value), $token->cursor);
                            }

                            $node = new Node\FunctionNode($token->value, $this->parseArguments());
                        } else {
                            if (!in_array($token->value, $this->names)) {
                                throw new SyntaxError(sprintf('Variable "%s" is not valid', $token->value), $token->cursor);
                            }

                            $node = new Node\NameNode($token->value);
                        }
                }
                break;

            case Token::NUMBER_TYPE:
            case Token::STRING_TYPE:
                $this->stream->next();

                return new Node\ConstantNode($token->value);

            default:
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parseArrayExpression();
                } elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parseHashExpression();
                } else {
                    throw new SyntaxError(sprintf('Unexpected token "%s" of value "%s"', $token->type, $token->value), $token->cursor);
                }
        }

        return $this->parsePostfixExpression($node);
    }

    public function parseArrayExpression()
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '[', 'An array element was expected');

        $node = new Node\ArrayNode();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');

                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
                    break;
                }
            }
            $first = false;

            $node->addElement($this->parseExpression());
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');

        return $node;
    }

    public function parseHashExpression()
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '{', 'A hash element was expected');

        $node = new Node\ArrayNode();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');

                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;

            // a hash key can be:
            //
            //  * a number -- 12
            //  * a string -- 'a'
            //  * a name, which is equivalent to a string -- a
            //  * an expression, which must be enclosed in parentheses -- (1 + 2)
            if ($this->stream->current->test(Token::STRING_TYPE) || $this->stream->current->test(Token::NAME_TYPE) || $this->stream->current->test(Token::NUMBER_TYPE)) {
                $key = new Node\ConstantNode($this->stream->current->value);
                $this->stream->next();
            } elseif ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                $key = $this->parseExpression();
            } else {
                $current = $this->stream->current;

                throw new SyntaxError(sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s"', $current->type, $current->value), $current->cursor);
            }

            $this->stream->expect(Token::PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
            $value = $this->parseExpression();

            $node->addElement($value, $key);
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');

        return $node;
    }

    public function parsePostfixExpression($node)
    {
        $token = $this->stream->current;
        while ($token->type == Token::PUNCTUATION_TYPE) {
            if ('.' === $token->value) {
                $this->stream->next();
                $token = $this->stream->current;
                $this->stream->next();

                if (
                    $token->type !== Token::NAME_TYPE
                    &&
                    $token->type !== Token::NUMBER_TYPE
                    &&
                    // operators line "not" are valid method or property names
                    ($token->type !== Token::OPERATOR_TYPE && preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $token->value))
                ) {
                    throw new SyntaxError('Expected name or number', $token->cursor);
                }

                $arg = new Node\ConstantNode($token->value);

                $arguments = new Node\ArgumentsNode();
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                    $type = Node\GetAttrNode::METHOD_CALL;
                    foreach ($this->parseArguments()->nodes as $n) {
                        $arguments->addElement($n);
                    }
                } else {
                    $type = Node\GetAttrNode::PROPERTY_CALL;
                }

                $node = new Node\GetAttrNode($node, $arg, $arguments, $type);
            } elseif ('[' === $token->value) {
                if ($node instanceof Node\GetAttrNode && Node\GetAttrNode::METHOD_CALL === $node->attributes['type'] && version_compare(PHP_VERSION, '5.4.0', '<')) {
                    throw new SyntaxError('Array calls on a method call is only supported on PHP 5.4+', $token->cursor);
                }

                $this->stream->next();
                $arg = $this->parseExpression();
                $this->stream->expect(Token::PUNCTUATION_TYPE, ']');

                $node = new Node\GetAttrNode($node, $arg, new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL);
            } else {
                break;
            }

            $token = $this->stream->current;
        }

        return $node;
    }

    /**
     * Parses arguments.
     */
    public function parseArguments()
    {
        $args = array();
        $this->stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ')')) {
            if (!empty($args)) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');
            }

            $args[] = $this->parseExpression();
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

        return new Node\Node($args);
    }
}
