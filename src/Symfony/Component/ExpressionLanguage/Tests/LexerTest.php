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
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\TokenStream;

class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    /**
     * @dataProvider getTokenizeData
     */
    public function testTokenize($tokens, $expression)
    {
        $tokens[] = new Token('end of expression', null, \strlen($expression) + 1);
        $this->assertEquals(new TokenStream($tokens, $expression), $this->lexer->tokenize($expression));
    }

    public function testTokenizeThrowsErrorWithMessage()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unexpected character "\'" around position 33 for expression `service(faulty.expression.example\').dummyMethod()`.');
        $expression = "service(faulty.expression.example').dummyMethod()";
        $this->lexer->tokenize($expression);
    }

    public function testTokenizeThrowsErrorOnUnclosedBrace()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Unclosed "(" around position 7 for expression `service(unclosed.expression.dummyMethod()`.');
        $expression = 'service(unclosed.expression.dummyMethod()';
        $this->lexer->tokenize($expression);
    }

    public static function getTokenizeData()
    {
        return [
            [
                [new Token('name', 'a', 3)],
                '  a  ',
            ],
            [
                [new Token('name', 'a', 1)],
                'a',
            ],
            [
                [new Token('string', 'foo', 1)],
                '"foo"',
            ],
            [
                [new Token('number', 3, 1)],
                '3',
            ],
            [
                [new Token('operator', '+', 1)],
                '+',
            ],
            [
                [new Token('punctuation', '.', 1)],
                '.',
            ],
            [
                [
                    new Token('punctuation', '(', 1),
                    new Token('number', 3, 2),
                    new Token('operator', '+', 4),
                    new Token('number', 5, 6),
                    new Token('punctuation', ')', 7),
                    new Token('operator', '~', 9),
                    new Token('name', 'foo', 11),
                    new Token('punctuation', '(', 14),
                    new Token('string', 'bar', 15),
                    new Token('punctuation', ')', 20),
                    new Token('punctuation', '.', 21),
                    new Token('name', 'baz', 22),
                    new Token('punctuation', '[', 25),
                    new Token('number', 4, 26),
                    new Token('punctuation', ']', 27),
                    new Token('operator', '-', 29),
                    new Token('number', 1990, 31),
                    new Token('operator', '+', 39),
                    new Token('operator', '~', 41),
                    new Token('name', 'qux', 42),
                ],
                '(3 + 5) ~ foo("bar").baz[4] - 1.99E+3 + ~qux',
            ],
            [
                [new Token('operator', '..', 1)],
                '..',
            ],
            [
                [
                    new Token('number', 23, 1),
                    new Token('operator', '..', 3),
                    new Token('number', 26, 5),
                ],
                '23..26',
            ],
            [
                [new Token('string', '#foo', 1)],
                "'#foo'",
            ],
            [
                [new Token('string', '#foo', 1)],
                '"#foo"',
            ],
            [
                [
                    new Token('name', 'foo', 1),
                    new Token('punctuation', '.', 4),
                    new Token('name', 'not', 5),
                    new Token('operator', 'in', 9),
                    new Token('punctuation', '[', 12),
                    new Token('name', 'bar', 13),
                    new Token('punctuation', ']', 16),
                ],
                'foo.not in [bar]',
            ],
            [
                [new Token('number', 0.787, 1)],
                '0.787',
            ],
            [
                [new Token('number', 0.1234, 1)],
                '.1234',
            ],
            [
                [new Token('number', 188165.1178, 1)],
                '188_165.1_178',
            ],
            [
                [
                    new Token('operator', '-', 1),
                    new Token('number', 7189000000.0, 2),
                ],
                '-.7_189e+10',
            ],
            [
                [
                    new Token('number', 65536, 1),
                ],
                '65536 /* this is 2^16 */',
            ],
            [
                [
                    new Token('number', 2, 1),
                    new Token('operator', '*', 21),
                    new Token('number', 4, 23),
                ],
                '2 /* /* comment1 */ * 4',
            ],
            [
                [
                    new Token('string', '/* this is', 1),
                    new Token('operator', '~', 14),
                    new Token('string', 'not a comment */', 16),
                ],
                '"/* this is" ~ "not a comment */"',
            ],
            [
                [
                    new Token('string', '/* this is not a comment */', 1),
                ],
                '"/* this is not a comment */"',
            ],
            [
                [
                    new Token('name', 'foo', 1),
                    new Token('operator', 'xor', 5),
                    new Token('name', 'bar', 9),
                ],
                'foo xor bar',
            ],
        ];
    }

    public function testOperatorRegexWasGeneratedWithScript()
    {
        ob_start();
        try {
            require $script = \dirname(__DIR__).'/Resources/bin/generate_operator_regex.php';
        } finally {
            $output = ob_get_clean();
        }

        self::assertStringContainsString(
            $output,
            file_get_contents((new \ReflectionClass(Lexer::class))->getFileName()),
            \sprintf('You need to run "%s" to generate the operator regex.', $script),
        );
    }
}
