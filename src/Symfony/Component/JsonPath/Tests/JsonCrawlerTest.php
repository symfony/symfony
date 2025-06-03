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
use Symfony\Component\JsonPath\Exception\InvalidArgumentException;
use Symfony\Component\JsonPath\Exception\InvalidJsonStringInputException;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

class JsonCrawlerTest extends TestCase
{
    public function testNotStringOrResourceThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected string or resource, got "int".');

        new JsonCrawler(42);
    }

    public function testInvalidInputJson()
    {
        $this->expectException(InvalidJsonStringInputException::class);
        $this->expectExceptionMessage('Invalid JSON input: Syntax error.');

        (new JsonCrawler('invalid'))->find('$..*');
    }

    public function testAllAuthors()
    {
        $result = self::getBookstoreCrawler()->find('$..author');

        $this->assertCount(4, $result);
        $this->assertSame([
            'Nigel Rees',
            'Evelyn Waugh',
            'Herman Melville',
            'J. R. R. Tolkien',
        ], $result);
    }

    public function testAllThingsInStore()
    {
        $result = self::getBookstoreCrawler()->find('$.store.*');

        $this->assertCount(2, $result);
        $this->assertCount(4, $result[0]);
        $this->assertArrayHasKey('color', $result[1]);
    }

    public function testEscapedDoubleQuotesInFieldName()
    {
        $crawler = new JsonCrawler(<<<JSON
{"a": {"b\\"c": 42}}
JSON);

        $result = $crawler->find("$['a']['b\\\"c']");

        $this->assertSame(42, $result[0]);
    }

    public function testBasicNameSelector()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book')[0];

        $this->assertCount(4, $result);
        $this->assertSame('Nigel Rees', $result[0]['author']);
    }

    public function testAllPrices()
    {
        $result = self::getBookstoreCrawler()->find('$.store..price');

        $this->assertCount(5, $result);
        $this->assertSame([8.95, 12.99, 8.99, 22.99, 399], $result);
    }

    public function testSpecificBookByIndex()
    {
        $result = self::getBookstoreCrawler()->find('$..book[2]');

        $this->assertCount(1, $result);
        $this->assertSame('Moby Dick', $result[0]['title']);
    }

    public function testLastBookInOrder()
    {
        $result = self::getBookstoreCrawler()->find('$..book[-1]');

        $this->assertCount(1, $result);
        $this->assertSame('The Lord of the Rings', $result[0]['title']);
    }

    public function testFirstTwoBooks()
    {
        $result = self::getBookstoreCrawler()->find('$..book[0,1]');

        $this->assertCount(2, $result);
        $this->assertSame('Sayings of the Century', $result[0]['title']);
        $this->assertSame('Sword of Honour', $result[1]['title']);
    }

    public function testBooksWithIsbn()
    {
        $result = self::getBookstoreCrawler()->find('$..book[?(@.isbn)]');

        $this->assertCount(2, $result);
        $this->assertSame([
            '0-553-21311-3',
            '0-395-19395-8',
        ], [$result[0]['isbn'], $result[1]['isbn']]);
    }

    public function testBooksLessThanTenDollars()
    {
        $result = self::getBookstoreCrawler()->find('$..book[?(@.price < 10)]');

        $this->assertCount(2, $result);
        $this->assertSame([
            'Sayings of the Century',
            'Moby Dick',
        ], [$result[0]['title'], $result[1]['title']]);
    }

    public function testRecursiveWildcard()
    {
        $result = self::getBookstoreCrawler()->find('$..*');

        $this->assertNotEmpty($result);
    }

    public function testSliceWithStep()
    {
        $crawler = new JsonCrawler(<<<JSON
{"a": [3, 5, 1, 2, 4, 6, {"b": "j"}, {"b": "k"}, {"b": {}}, {"b": "kilo"}]}
JSON);

        $result = $crawler->find('$.a[1:5:2]');
        $this->assertSame([5, 2], $result);
    }

    public function testNegativeSlice()
    {
        $crawler = new JsonCrawler(<<<JSON
{"a": [3, 5, 1, 2, 4, 6, {"b": "j"}, {"b": "k"}, {"b": {}}, {"b": "kilo"}]}
JSON);

        $result = $crawler->find('$.a[-3:]');

        $this->assertCount(3, $result);
    }

    public function testBooleanAndNullValues()
    {
        $crawler = new JsonCrawler('{"a": true, "b": false, "c": null}');

        $result = $crawler->find('$.*');
        $this->assertSame([true, false, null], $result);
    }

    public function testFullArraySlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[:]');
        $this->assertSame([3, 5, 1, 2, 4, 6], $result);
    }

    public function testReverseArraySlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[::-1]');
        $this->assertSame([6, 4, 2, 1, 5, 3], $result);
    }

    public function testLastTwoElementsSlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[-2:]');
        $this->assertSame([4, 6], $result);
    }

    public function testAllButLastTwoElementsSlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[:-2]');
        $this->assertSame([3, 5, 1, 2], $result);
    }

    public function testEverySecondElementSlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[::2]');
        $this->assertSame([3, 1, 4], $result);
    }

    public function testEverySecondElementReverseSlice()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[::-2]');
        $this->assertSame([6, 2, 5], $result);
    }

    public function testEmptyResults()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $this->assertEmpty($crawler->find('$.a[::0]'));
        $this->assertEmpty($crawler->find('$.a[10:20]'));
        $this->assertEmpty($crawler->find('$.a[5:2]'));
    }

    public function testNegativeIndicesEdgeCases()
    {
        $crawler = self::getSimpleCollectionCrawler();

        $result = $crawler->find('$.a[-4:-2]');
        $this->assertSame([1, 2], $result);

        $result = $crawler->find('$.a[-3:5]');
        $this->assertSame([2, 4], $result);

        $result = $crawler->find('$.a[-2:-5:-1]');
        $this->assertSame([4, 2, 1], $result);
    }

    public function testBoundaryConditions()
    {
        $crawler = new JsonCrawler(<<<JSON
{"a": [3, 5, 1, 2, 4, 6]}
JSON);

        $result = $crawler->find('$.a[0:6]');
        $this->assertSame([3, 5, 1, 2, 4, 6], $result);

        $result = $crawler->find('$.a[-10:10]');
        $this->assertSame([3, 5, 1, 2, 4, 6], $result);

        $result = $crawler->find('$.a[2:3]');
        $this->assertSame([1], $result);
    }

    public function testFilterByValue()
    {
        $crawler = new JsonCrawler(<<<JSON
{"a": [3, 5, 1, 2, 4, 6, {"b": "j"}, {"b": "k"}, {"b": {}}, {"b": "kilo"}]}
JSON);

        $result = $crawler->find("$.a[?(@.b == 'kilo')]");

        $this->assertCount(1, $result);
        $this->assertSame('kilo', $result[0]['b']);
    }

    public function testMultipleConditions()
    {
        $result = self::getBookstoreCrawler()->find("$..book[?(@.price < 10 && @.category == 'reference')]");

        $this->assertCount(1, $result);
        $this->assertSame('Sayings of the Century', $result[0]['title']);
    }

    public function testEmptyResult()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?(@.price > 1000)]');

        $this->assertEmpty($result);
    }

    public function testDirectRecursion()
    {
        $result = self::getBookstoreCrawler()->find('$..price');

        $this->assertCount(5, $result);
    }

    public function testCombinedFilters()
    {
        $result = self::getBookstoreCrawler()->find("$..book[?(@.price > 20 && @.category == 'fiction')]");

        $this->assertCount(1, $result);
        $this->assertSame('The Lord of the Rings', $result[0]['title']);
    }

    public function testMatchFunction()
    {
        $result = self::getBookstoreCrawler()->find("$.store.book[?match(@.title, 'Sw[a-z]rd of Honour')]");

        $this->assertCount(1, $result);
        $this->assertSame('Sword of Honour', $result[0]['title']);
    }

    public function testMatchFunctionDoesNotMatchSubstring()
    {
        $result = self::getBookstoreCrawler()->find("$.store.book[?match(@.title, 'Sw[a-z]rd')]");

        $this->assertCount(0, $result);
    }

    public function testMatchFunctionWithOuterParentheses()
    {
        $result = self::getBookstoreCrawler()->find("$.store.book[?(match(@.title, 'Sw[a-z]rd of Honour'))]");

        $this->assertCount(1, $result);
        $this->assertSame('Sword of Honour', $result[0]['title']);
    }

    public function testSearchFunctionMatchSubstring()
    {
        $result = self::getBookstoreCrawler()->find("$.store.book[?search(@.title, 'of H[ou]nour')]");

        $this->assertCount(1, $result);
        $this->assertSame('Sword of Honour', $result[0]['title']);
    }

    public function testSearchFunctionWithOuterParentheses()
    {
        $result = self::getBookstoreCrawler()->find("$.store.book[?(search(@.title, 'of Hon.{2}r'))]");

        $this->assertCount(1, $result);
        $this->assertSame('Sword of Honour', $result[0]['title']);
    }

    public function testValueFunction()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?value(@.price) == 8.95]');

        $this->assertCount(1, $result);
        $this->assertSame('Sayings of the Century', $result[0]['title']);
    }

    public function testValueFunctionWithOuterParentheses()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?(value(@.price) == 8.95)]');

        $this->assertCount(1, $result);
        $this->assertSame('Sayings of the Century', $result[0]['title']);
    }

    public function testLengthFunction()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?length(@.author) > 12]');

        $this->assertCount(2, $result);
        $this->assertSame('Herman Melville', $result[0]['author']);
        $this->assertSame('J. R. R. Tolkien', $result[1]['author']);
    }

    public function testLengthFunctionWithOuterParentheses()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?(length(@.author) > 12)]');

        $this->assertCount(2, $result);
        $this->assertSame('Herman Melville', $result[0]['author']);
        $this->assertSame('J. R. R. Tolkien', $result[1]['author']);
    }

    public function testCountFunction()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?count(@.extra) != 0]');

        $this->assertCount(1, $result);
        $this->assertSame([42], $result[0]['extra']);
    }

    public function testCountFunctionWithOuterParentheses()
    {
        $result = self::getBookstoreCrawler()->find('$.store.book[?(count(@.extra) != 0)]');

        $this->assertCount(1, $result);
        $this->assertSame([42], $result[0]['extra']);
    }

    public function testUnknownFunction()
    {
        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('invalid function "unknown"');

        self::getBookstoreCrawler()->find('$.store.book[?unknown(@.extra) != 0]');
    }

    public function testAcceptsJsonPath()
    {
        $bicyclePath = new JsonPath('$.store.bicycle');

        $result = self::getBookstoreCrawler()->find($bicyclePath);

        $this->assertCount(1, $result);
        $this->assertSame('red', $result[0]['color']);
    }

    private static function getBookstoreCrawler(): JsonCrawler
    {
        return new JsonCrawler(<<<JSON
{
    "store": {
        "book": [
            {
                "category": "reference",
                "author": "Nigel Rees",
                "title": "Sayings of the Century",
                "price": 8.95
            },
            {
                "category": "fiction",
                "author": "Evelyn Waugh",
                "title": "Sword of Honour",
                "price": 12.99
            },
            {
                "category": "fiction",
                "author": "Herman Melville",
                "title": "Moby Dick",
                "isbn": "0-553-21311-3",
                "price": 8.99,
                "extra": [42]
            },
            {
                "category": "fiction",
                "author": "J. R. R. Tolkien",
                "title": "The Lord of the Rings",
                "isbn": "0-395-19395-8",
                "price": 22.99
            }
        ],
        "bicycle": {
            "color": "red",
            "price": 399
        }
    }
}
JSON);
    }

    private static function getSimpleCollectionCrawler(): JsonCrawler
    {
        return new JsonCrawler(<<<JSON
{"a": [3, 5, 1, 2, 4, 6]}
JSON);
    }
}
