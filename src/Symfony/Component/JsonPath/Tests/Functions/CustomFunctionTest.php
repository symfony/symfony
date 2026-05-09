<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\Functions;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\FunctionReturnType;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\Tests\FunctionsLocatorTrait;

class CustomFunctionTest extends TestCase
{
    use FunctionsLocatorTrait;

    public function testCustomFunctionWithSingleArgument()
    {
        $upperFunction = static fn (mixed $value): ?string => \is_string($value) ? strtoupper($value) : null;

        $crawler = new JsonCrawler(<<<JSON
                {"name": "test", "items": [{"title": "hello"}, {"title": "world"}]}
            JSON, $this->createFunctionsLocator(['upper' => $upperFunction]));

        $result = $crawler->find('$.items[?upper(@.title) == "HELLO"]');
        $this->assertCount(1, $result);
        $this->assertEquals('hello', $result[0]['title']);
    }

    public function testCustomFunctionWithMultipleArguments()
    {
        $concatFunction = static fn (mixed $first, mixed $second): string => \sprintf('%s%s', $first, $second);
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"first": "hello", "second": "world"}, {"first": "foo", "second": "bar"}]}
            JSON, $this->createFunctionsLocator(['concat' => $concatFunction]));

        $result = $crawler->find('$.items[?concat(@.first, @.second) == "helloworld"]');
        $this->assertCount(1, $result);
        $this->assertEquals('hello', $result[0]['first']);
        $this->assertEquals('world', $result[0]['second']);
    }

    public function testBooleanFunction()
    {
        $isPositiveFunction = static fn (mixed $value): bool => is_numeric($value) && $value > 0;
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"value": 5}, {"value": -3}, {"value": 10}]}
            JSON, $this->createFunctionsLocator(['is_positive' => $isPositiveFunction]));

        $result = $crawler->find('$.items[?is_positive(@.value)]');

        $this->assertCount(2, $result);
        $this->assertEquals(5, $result[0]['value']);
        $this->assertEquals(10, $result[1]['value']);
    }

    public function testNumericFunction()
    {
        $doubleFunction = static fn (mixed $value): int|float => is_numeric($value) ? $value * 2 : 0;
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"value": 5}, {"value": 10}]}
            JSON, $this->createFunctionsLocator(['double' => $doubleFunction]));

        $result = $crawler->find('$.items[?double(@.value) > 15]');
        $this->assertCount(1, $result);
        $this->assertEquals(10, $result[0]['value']);
    }

    public function testCustomFunctionReceivesEvaluatedArguments()
    {
        $surroundFunction = static fn (mixed $prefix, mixed $value, mixed $suffix): string => $prefix.$value.$suffix;

        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"title": "hello"}, {"title": "world"}]}
            JSON, $this->createFunctionsLocator(['surround' => $surroundFunction]));

        $result = $crawler->find('$.items[?surround("<", @.title, ">") == "<hello>"]');

        $this->assertCount(1, $result);
        $this->assertSame('hello', $result[0]['title']);
    }

    public function testCustomFunctionThrowingExceptionIsWrapped()
    {
        $failingFunction = static function (mixed $value): string {
            throw new \RuntimeException('Something went wrong inside the function');
        };

        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"title": "hello"}]}
            JSON, $this->createFunctionsLocator(['failing' => $failingFunction]));

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('An error occurred while executing the custom JsonPath function "failing"');

        $crawler->find('$.items[?failing(@.title) == "x"]');
    }

    public function testCustomFunctionOverridesBuiltIn()
    {
        $customLength = static fn (mixed $value): int => 9999;

        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"name": "hi"}, {"name": "hello world"}]}
            JSON, $this->createFunctionsLocator(['length' => $customLength]));

        // custom length returns 9999 for all values, so both items match > 5
        $result = $crawler->find('$.items[?length(@.name) > 5]');
        $this->assertCount(2, $result);
    }

    public function testCustomFunctionArityValidation()
    {
        $concatFunction = static fn (mixed $a, mixed $b): string => $a.$b;

        $crawler = new JsonCrawler(
            '{"items": [{"a": "x"}]}',
            $this->createFunctionsLocator(['concat' => $concatFunction]),
            ['concat' => ['arity' => 2, 'return_type' => FunctionReturnType::Value]],
        );

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('requires exactly 2 argument(s)');

        $crawler->find('$.items[?concat(@.a) == "x"]');
    }

    public function testCustomFunctionLogicalReturnTypeCannotBeCompared()
    {
        $existsFunction = static fn (mixed $value): bool => null !== $value;

        $crawler = new JsonCrawler(
            '{"items": [{"a": 1}]}',
            $this->createFunctionsLocator(['exists' => $existsFunction]),
            ['exists' => ['arity' => 1, 'return_type' => FunctionReturnType::Logical]],
        );

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('LogicalType');

        $crawler->find('$.items[?exists(@.a) == true]');
    }

    public function testCustomFunctionLogicalReturnTypeCanBeUsedAsFilter()
    {
        $existsFunction = static fn (mixed $value): bool => null !== $value;

        $crawler = new JsonCrawler(
            '{"items": [{"a": 1}, {"a": 2}]}',
            $this->createFunctionsLocator(['exists' => $existsFunction]),
            ['exists' => ['arity' => 1, 'return_type' => FunctionReturnType::Logical]],
        );

        $result = $crawler->find('$.items[?exists(@.a)]');
        $this->assertCount(2, $result);
    }

    public function testCustomFunctionValueReturnTypeCannotBeUsedAsFilter()
    {
        $stringifyFunction = static fn (mixed $value): string => (string) $value;

        $crawler = new JsonCrawler(
            '{"items": [{"a": 1}]}',
            $this->createFunctionsLocator(['stringify' => $stringifyFunction]),
            ['stringify' => ['arity' => 1, 'return_type' => FunctionReturnType::Value]],
        );

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('ValueType');

        $crawler->find('$.items[?stringify(@.a)]');
    }

    public function testCustomFunctionNodesReturnTypeCannotBeCompared()
    {
        $nodesFunction = static fn (mixed $value): array => [$value];

        $crawler = new JsonCrawler(
            '{"items": [{"a": 1}]}',
            $this->createFunctionsLocator(['nodes' => $nodesFunction]),
            ['nodes' => ['arity' => 1, 'return_type' => FunctionReturnType::Nodes]],
        );

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('NodesType');

        $crawler->find('$.items[?nodes(@.a) == 1]');
    }

    public function testCustomFunctionNodesReturnTypeCanBeUsedAsFilter()
    {
        $nodesFunction = static fn (mixed $value): array => null === $value ? [] : [$value];

        $crawler = new JsonCrawler(
            '{"items": [{"a": 1}, {"b": 2}]}',
            $this->createFunctionsLocator(['nodes' => $nodesFunction]),
            ['nodes' => ['arity' => 1, 'return_type' => FunctionReturnType::Nodes]],
        );

        $result = $crawler->find('$.items[?nodes(@.a)]');

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['a']);
    }
}
