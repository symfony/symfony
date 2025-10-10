<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Test\JsonPathAssertionsTrait;

class JsonPathAssertionsTraitTest extends TestCase
{
    use JsonPathAssertionsTrait;

    public function testAssertJsonPathEqualsOk()
    {
        self::assertJsonPathEquals([1], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathEqualsOkWithTypeCoercion()
    {
        self::assertJsonPathEquals(['1'], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathEqualsKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathEquals([2], '$.a[2]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertMatchesRegularExpression('/Failed asserting that .+ equals JSON path "\$\.a\[2]" result./s', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathNotEqualsOk()
    {
        self::assertJsonPathNotEquals([2], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathNotEqualsKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathNotEquals([1], '$.a[2]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertMatchesRegularExpression('/Failed asserting that .+ does not equal JSON path "\$\.a\[2]" result./s', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathCountOk()
    {
        self::assertJsonPathCount(6, '$.a[*]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathCountOkWithFilter()
    {
        self::assertJsonPathCount(2, '$.book[?(@.price > 25)]', <<<JSON
            {
                "book": [
                    { "price":  10 },
                    { "price":  20 },
                    { "price":  30 },
                    { "price":  40 }
                ]
            }
            JSON
        );
    }

    public function testAssertJsonPathCountKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathCount(5, '$.a[*]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertSame('Failed asserting that 5 matches expected count of JSON path "$.a[*]".', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathSameOk()
    {
        self::assertJsonPathSame([1], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathSameKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathSame([2], '$.a[2]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertMatchesRegularExpression('/Failed asserting that .+ is identical to JSON path "\$\.a\[2]" result\./s', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathHasNoTypeCoercion()
    {
        $thrown = false;
        try {
            self::assertJsonPathSame(['1'], '$.a[2]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertMatchesRegularExpression('/Failed asserting that .+ is identical to JSON path "\$\.a\[2]" result\./s', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathNotSameOk()
    {
        self::assertJsonPathNotSame([2], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathNotSameKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathNotSame([1], '$.a[2]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertMatchesRegularExpression('/Failed asserting that .+ is not identical to JSON path "\$\.a\[2]" result\./s', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathNotSameHasNoTypeCoercion()
    {
        self::assertJsonPathNotSame(['1'], '$.a[2]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathContainsOk()
    {
        self::assertJsonPathContains(1, '$.a[*]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathContainsKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathContains(0, '$.a[*]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertSame('Failed asserting that 0 is found in elements at JSON path "$.a[*]".', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    public function testAssertJsonPathNotContainsOk()
    {
        self::assertJsonPathNotContains(0, '$.a[*]', self::getSimpleCollectionCrawlerData());
    }

    public function testAssertJsonPathNotContainsKo()
    {
        $thrown = false;
        try {
            self::assertJsonPathNotContains(1, '$.a[*]', self::getSimpleCollectionCrawlerData());
        } catch (AssertionFailedError $exception) {
            self::assertSame('Failed asserting that 1 is not found in elements at JSON path "$.a[*]".', $exception->getMessage());

            $thrown = true;
        }

        self::assertTrue($thrown);
    }

    private static function getSimpleCollectionCrawlerData(): string
    {
        return <<<JSON
            {"a": [3, 5, 1, 2, 4, 6]}
            JSON;
    }
}
