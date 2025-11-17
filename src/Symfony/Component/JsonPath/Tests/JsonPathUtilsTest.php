<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\JsonPath;
use Symfony\Component\JsonPath\JsonPathUtils;
use Symfony\Component\JsonPath\Tokenizer\JsonPathToken;
use Symfony\Component\JsonPath\Tokenizer\JsonPathTokenizer;
use Symfony\Component\JsonPath\Tokenizer\TokenType;

class JsonPathUtilsTest extends TestCase
{
    public function testReduceWithArrayAccess()
    {
        $path = new JsonPath('$.store.book[0].title');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        fclose($resource);

        $this->assertSame('{"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95}', $reduced['json']);
        $this->assertEquals([new JsonPathToken(TokenType::Name, 'title')], $reduced['tokens']);
    }

    public function testReduceWithBasicProperty()
    {
        $path = new JsonPath('$.store.book');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        fclose($resource);

        $this->assertSame(<<<JSON
            {"book": [
                {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
                {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
            ]}
            JSON,
            $reduced['json']
        );
        $this->assertEquals([new JsonPathToken(TokenType::Name, 'book')], $reduced['tokens']);
    }

    public function testReduceUntilFilter()
    {
        $path = new JsonPath('$.store[?(@.book.author == "Nigel Rees")]');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        fclose($resource);

        $this->assertSame(<<<JSON
            {"book": [
                {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
                {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
            ]}
            JSON,
            $reduced['json']
        );
        $this->assertEquals([new JsonPathToken(TokenType::Bracket, '?(@.book.author == "Nigel Rees")')], $reduced['tokens']);
    }

    public function testDoesNotReduceOnRecursiveDescent()
    {
        $path = new JsonPath('$..book');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        rewind($resource);
        $fullJson = stream_get_contents($resource);
        fclose($resource);

        $this->assertSame($fullJson, $reduced['json']);
        $this->assertEquals([
            new JsonPathToken(TokenType::Recursive, '..'),
            new JsonPathToken(TokenType::Name, 'book'),
        ], $reduced['tokens']);
    }

    public function testDoesNotReduceOnArraySlice()
    {
        $path = new JsonPath('$.store.book[1:2]');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        fclose($resource);

        $this->assertSame(<<<JSON
            [
                {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
                {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
            ]
            JSON,
            $reduced['json'],
            'reduce to "book", but not further'
        );
        $this->assertEquals([
            new JsonPathToken(TokenType::Bracket, '1:2'),
        ], $reduced['tokens']);
    }

    public function testDoesNotReduceOnUnknownProperty()
    {
        $path = new JsonPath('$.unknown');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        $fullJson = stream_get_contents($resource);
        fclose($resource);

        $this->assertSame($fullJson, $reduced['json']);
        $this->assertEquals([
            new JsonPathToken(TokenType::Name, 'unknown'),
        ], $reduced['tokens']);
    }

    public function testDoesNotReduceOnUnknownIndex()
    {
        $path = new JsonPath('$.store.book[123].title');
        $resource = self::provideJsonResource();

        $reduced = JsonPathUtils::findSmallestDeserializableStringAndPath(
            JsonPathTokenizer::tokenize($path),
            $resource
        );

        fclose($resource);

        $this->assertSame(<<<JSON
            [
                {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
                {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
            ]
            JSON,
            $reduced['json'],
            'reduce to "book", but not further'
        );
        $this->assertEquals([
            new JsonPathToken(TokenType::Bracket, '123'),
            new JsonPathToken(TokenType::Name, 'title'),
        ], $reduced['tokens']);
    }

    /**
     * @return resource
     */
    private static function provideJsonResource(): mixed
    {
        $json = <<<'JSON'
            {"store": {"book": [
                {"category": "reference", "author": "Nigel Rees", "title": "Sayings", "price": 8.95},
                {"category": "fiction", "author": "Evelyn Waugh", "title": "Sword", "price": 12.99}
            ]}}
            JSON;

        $resource = fopen('php://memory', 'r+');
        fwrite($resource, $json);
        rewind($resource);

        return $resource;
    }
}
