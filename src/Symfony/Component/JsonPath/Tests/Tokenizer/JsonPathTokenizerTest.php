<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\Tokenizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Exception\InvalidJsonPathException;
use Symfony\Component\JsonPath\JsonPath;
use Symfony\Component\JsonPath\Tokenizer\JsonPathTokenizer;
use Symfony\Component\JsonPath\Tokenizer\TokenType;

class JsonPathTokenizerTest extends TestCase
{
    #[DataProvider('simplePathProvider')]
    public function testSimplePath(string $path, array $expectedTokens)
    {
        $jsonPath = new JsonPath($path);
        $tokens = JsonPathTokenizer::tokenize($jsonPath);

        $this->assertCount(\count($expectedTokens), $tokens);
        foreach ($tokens as $i => $token) {
            $this->assertSame($expectedTokens[$i][0], $token->type);
            $this->assertSame($expectedTokens[$i][1], $token->value);
        }
    }

    public static function simplePathProvider(): array
    {
        return [
            'root only' => [
                '$',
                [],
            ],
            'simple property' => [
                '$.store',
                [[TokenType::Name, 'store']],
            ],
            'nested property' => [
                '$.store.book',
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Name, 'book'],
                ],
            ],
            'recursive descent' => [
                '$..book',
                [
                    [TokenType::Recursive, '..'],
                    [TokenType::Name, 'book'],
                ],
            ],
        ];
    }

    #[DataProvider('bracketNotationProvider')]
    public function testBracketNotation(string $path, array $expectedTokens)
    {
        $jsonPath = new JsonPath($path);
        $tokens = JsonPathTokenizer::tokenize($jsonPath);

        $this->assertCount(\count($expectedTokens), $tokens);
        foreach ($tokens as $i => $token) {
            $this->assertSame($expectedTokens[$i][0], $token->type);
            $this->assertSame($expectedTokens[$i][1], $token->value);
        }
    }

    public static function bracketNotationProvider(): array
    {
        return [
            'bracket with quotes' => [
                "$['store']",
                [[TokenType::Bracket, "'store'"]],
            ],
            'multiple brackets' => [
                "$['store']['book']",
                [
                    [TokenType::Bracket, "'store'"],
                    [TokenType::Bracket, "'book'"],
                ],
            ],
            'mixed notation' => [
                "$.store['book'][0]",
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Bracket, "'book'"],
                    [TokenType::Bracket, '0'],
                ],
            ],
        ];
    }

    #[DataProvider('filterExpressionProvider')]
    public function testFilterExpressions(string $path, array $expectedTokens)
    {
        $jsonPath = new JsonPath($path);
        $tokens = JsonPathTokenizer::tokenize($jsonPath);

        $this->assertCount(\count($expectedTokens), $tokens);
        foreach ($tokens as $i => $token) {
            $this->assertSame($expectedTokens[$i][0], $token->type);
            $this->assertSame($expectedTokens[$i][1], $token->value);
        }
    }

    public static function filterExpressionProvider(): array
    {
        return [
            'simple filter' => [
                '$.store.book[?(@.price < 10)]',
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.price < 10)'],
                ],
            ],
            'nested filter' => [
                '$.store.book[?(@.price < 10 && @.category == "fiction")]',
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.price < 10 && @.category == "fiction")'],
                ],
            ],
            'filter with nested brackets' => [
                '$.store.book[?(@.authors[0] == "John Smith")]',
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.authors[0] == "John Smith")'],
                ],
            ],
        ];
    }

    #[DataProvider('complexPathProvider')]
    public function testComplexPaths(string $path, array $expectedTokens)
    {
        $jsonPath = new JsonPath($path);
        $tokens = JsonPathTokenizer::tokenize($jsonPath);

        $this->assertCount(\count($expectedTokens), $tokens);
        foreach ($tokens as $i => $token) {
            $this->assertSame($expectedTokens[$i][0], $token->type);
            $this->assertSame($expectedTokens[$i][1], $token->value);
        }
    }

    public static function complexPathProvider(): array
    {
        return [
            'mixed with recursive' => [
                '$..book[?(@.price < 10)].title',
                [
                    [TokenType::Recursive, '..'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.price < 10)'],
                    [TokenType::Name, 'title'],
                ],
            ],
            'multiple filters' => [
                '$.store.book[?(@.price < 10)][?(@.category == "fiction")]',
                [
                    [TokenType::Name, 'store'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.price < 10)'],
                    [TokenType::Bracket, '?(@.category == "fiction")'],
                ],
            ],
            'everything combined' => [
                '$..store[*].book[?(@.price < 10)].author["lastName"]',
                [
                    [TokenType::Recursive, '..'],
                    [TokenType::Name, 'store'],
                    [TokenType::Bracket, '*'],
                    [TokenType::Name, 'book'],
                    [TokenType::Bracket, '?(@.price < 10)'],
                    [TokenType::Name, 'author'],
                    [TokenType::Bracket, '"lastName"'],
                ],
            ],
        ];
    }

    public function testTokenizeThrowsExceptionForEmptyExpression()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error: empty JSONPath expression.');

        JsonPathTokenizer::tokenize(new JsonPath(''));
    }

    public function testTokenizeThrowsExceptionWhenNotStartingWithDollar()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error: expression must start with $');

        JsonPathTokenizer::tokenize(new JsonPath('store.book'));
    }

    public function testTokenizeThrowsExceptionForUnmatchedClosingBracket()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 7: unmatched closing bracket.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store]'));
    }

    public function testTokenizeThrowsExceptionForEmptyBrackets()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 8: empty brackets are not allowed.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[]'));
    }

    public function testTokenizeThrowsExceptionForUnexpectedCharsBeforeFilter()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 11: unexpected characters before filter expression.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[abc?(@.price > 10)]'));
    }

    public function testTokenizeThrowsExceptionForUnmatchedParenthesisInFilter()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 23: unmatched closing parenthesis in filter.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[?(@.price > 10))]'));
    }

    public function testTokenizeThrowsExceptionForPathEndingWithDot()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 7: path cannot end with a dot.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store.'));
    }

    public function testTokenizeThrowsExceptionForUnclosedBracket()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 8: unclosed bracket.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[0'));
    }

    public function testTokenizeThrowsExceptionForUnclosedStringLiteral()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 16: unclosed string literal.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store["unclosed'));
    }

    public function testTokenizeThrowsExceptionForUnclosedSingleQuotedString()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 16: unclosed string literal.');

        JsonPathTokenizer::tokenize(new JsonPath("$.store['unclosed"));
    }

    public function testTokenizeThrowsExceptionForNestedUnmatchedBrackets()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 10: unclosed bracket.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[[0]'));
    }

    public function testTokenizeThrowsExceptionForMultipleUnmatchedClosingBrackets()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 10: unmatched closing bracket.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[0]]]'));
    }

    public function testTokenizeThrowsExceptionForInvalidFilterSyntax()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 22: unclosed parenthesis.');

        JsonPathTokenizer::tokenize(new JsonPath('$.store[?(@.price > 10]'));
    }

    public function testTokenizeThrowsExceptionForConsecutiveDotsWithoutRecursive()
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessage('JSONPath syntax error at position 9: invalid character "." in property name');

        JsonPathTokenizer::tokenize(new JsonPath('$.store...name'));
    }

    #[DataProvider('provideValidUtf8Chars')]
    public function testUtf8ValidChars(string $propertyName)
    {
        $jsonPath = new JsonPath(\sprintf('$.%s', $propertyName));
        $tokens = JsonPathTokenizer::tokenize($jsonPath);

        $this->assertCount(1, $tokens);
        $this->assertSame(TokenType::Name, $tokens[0]->type);
        $this->assertSame($propertyName, $tokens[0]->value);
    }

    public static function provideValidUtf8Chars(): array
    {
        return [
            'basic lowercase letter' => ['hello'],
            'basic uppercase letter' => ['Hello'],
            'underscore first' => ['_test123'],
            'numbers allowed after first char' => ['a123'],
            'asterisk alone' => ['*'],
            'french accents' => ['héllo'],
            'russian' => ['привет'],
            'chinese' => ['漢字'],
        ];
    }

    #[DataProvider('provideInvalidUtf8PropertyName')]
    public function testUtf8InvalidPropertyName(string $propertyName)
    {
        $this->expectException(InvalidJsonPathException::class);
        $this->expectExceptionMessageMatches('/JSONPath syntax error.*: invalid character in property name "(.*)"/');

        $jsonPath = new JsonPath(\sprintf('$.%s', $propertyName));
        JsonPathTokenizer::tokenize($jsonPath);
    }

    public static function provideInvalidUtf8PropertyName(): array
    {
        return [
            'special char first' => ['#test'],
            'start with digit' => ['123test'],
            'asterisk' => ['test*test'],
            'at sign not allowed' => ['@test'],
            'ending control char' => ["test\xFF\xFA"],
            'dash sign' => ['-test'],
        ];
    }
}
