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
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\Functions\JsonPathComparableFunctionInterface;
use Symfony\Component\JsonPath\Functions\JsonPathFunctionInterface;
use Symfony\Component\JsonPath\Functions\JsonPathFunctionsProvider;
use Symfony\Component\JsonPath\JsonCrawler;

class CustomFunctionTest extends TestCase
{
    public function testCustomFunction()
    {
        $customFunction = new class implements JsonPathFunctionInterface {
            public function getName(): string
            {
                return 'upper';
            }

            public function __invoke(array $args, mixed $context): ?string
            {
                $results = $args[0] ?? [];
                $value = \is_array($results) ? ($results[0] ?? null) : $results;

                return \is_string($value) ? strtoupper($value) : null;
            }

            public function validate(string $name, array $args): void
            {
            }
        };

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => 'upper' === $name);
        $locator->method('get')->willReturnCallback(fn ($name) => match ($name) {
            'upper' => $customFunction,
        });

        $functionsProvider = new JsonPathFunctionsProvider($locator);

        $crawler = new JsonCrawler(<<<JSON
                {"name": "test", "items": [{"title": "hello"}, {"title": "world"}]}
            JSON, $functionsProvider);

        $result = $crawler->find('$.items[?upper(@.title) == "HELLO"]');
        $this->assertCount(1, $result);
        $this->assertEquals('hello', $result[0]['title']);
    }

    public function testCustomFunctionWithMultipleArguments()
    {
        $customFunction = new class implements JsonPathFunctionInterface {
            public function getName(): string
            {
                return 'concat';
            }

            public function __invoke(array $args, mixed $context): string
            {
                $results1 = $args[0] ?? [];
                $results2 = $args[1] ?? [];

                $value1 = \is_array($results1) ? ($results1[0] ?? '') : $results1;
                $value2 = \is_array($results2) ? ($results2[0] ?? '') : $results2;

                return $value1.$value2;
            }

            public function validate(string $name, array $args): void
            {
            }
        };

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => 'concat' === $name);
        $locator->method('get')->willReturnCallback(fn ($name) => match ($name) {
            'concat' => $customFunction,
        });

        $functionsProvider = new JsonPathFunctionsProvider($locator);
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"first": "hello", "second": "world"}, {"first": "foo", "second": "bar"}]}
            JSON, $functionsProvider);

        $result = $crawler->find('$.items[?concat(@.first, @.second) == "helloworld"]');
        $this->assertCount(1, $result);
        $this->assertEquals('hello', $result[0]['first']);
        $this->assertEquals('world', $result[0]['second']);
    }

    public function testComparableFunctionRequiresComparison()
    {
        $comparableFunction = new class implements JsonPathComparableFunctionInterface {
            public function getName(): string
            {
                return 'double';
            }

            public function __invoke(array $args, mixed $context): int|float
            {
                $results = $args[0] ?? [];
                $value = \is_array($results) ? ($results[0] ?? 0) : $results;

                return is_numeric($value) ? $value * 2 : 0;
            }

            public function validate(string $name, array $args): void
            {
            }
        };

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => 'double' === $name);
        $locator->method('get')->willReturnCallback(fn ($name) => match ($name) {
            'double' => $comparableFunction,
        });

        $functionsProvider = new JsonPathFunctionsProvider($locator);
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"value": 5}, {"value": 10}]}
            JSON, $functionsProvider);

        $result = $crawler->find('$.items[?double(@.value) > 15]');
        $this->assertCount(1, $result);
        $this->assertEquals(10, $result[0]['value']);

        $this->expectException(JsonCrawlerException::class);
        $this->expectExceptionMessage('Function result must be compared');

        $crawler->find('$.items[?double(@.value)]');
    }

    public function testBooleanFunctionWorksStandalone()
    {
        $booleanFunction = new class implements JsonPathFunctionInterface {
            public function getName(): string
            {
                return 'is_positive';
            }

            public function __invoke(array $args, mixed $context): bool
            {
                $results = $args[0] ?? [];
                $value = \is_array($results) ? ($results[0] ?? 0) : $results;

                return is_numeric($value) && $value > 0;
            }

            public function validate(string $name, array $args): void
            {
            }
        };

        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('has')->willReturnCallback(fn ($name) => 'is_positive' === $name);
        $locator->method('get')->willReturnCallback(fn ($name) => match ($name) {
            'is_positive' => $booleanFunction,
        });

        $functionsProvider = new JsonPathFunctionsProvider($locator);
        $crawler = new JsonCrawler(<<<JSON
                {"items": [{"value": 5}, {"value": -3}, {"value": 10}]}
            JSON, $functionsProvider);

        $result = $crawler->find('$.items[?is_positive(@.value)]');

        $this->assertCount(2, $result);
        $this->assertEquals(5, $result[0]['value']);
        $this->assertEquals(10, $result[1]['value']);
    }
}
