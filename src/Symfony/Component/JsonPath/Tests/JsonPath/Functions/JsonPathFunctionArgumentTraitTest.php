<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Tests\JsonPath\Functions;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Functions\JsonPathFunctionArgumentTrait;

#[CoversTrait(JsonPathFunctionArgumentTrait::class)]
class JsonPathFunctionArgumentTraitTest extends TestCase
{
    private object $trait;

    protected function setUp(): void
    {
        $this->trait = new class {
            use JsonPathFunctionArgumentTrait;
        };
    }

    public function testAssertArgumentsCountWithCorrectCount()
    {
        $this->trait->assertArgumentsCount('test', ['arg1', 'arg2'], 2);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertArgumentsCountWithIncorrectCount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "test" requires exactly 3 argument(s).');

        $this->trait->assertArgumentsCount('test', ['arg1', 'arg2'], 3);
    }

    public function testAssertArgumentsCountWithZeroExpected()
    {
        $this->trait->assertArgumentsCount('test', [], 0);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertArgumentsCountWithZeroActual()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "test" requires exactly 1 argument(s).');

        $this->trait->assertArgumentsCount('test', [], 1);
    }

    #[DataProvider('validSingularQueryProvider')]
    public function testAssertIsSingularQueryWithValidQuery(string $query)
    {
        $this->trait->assertIsSingularQuery('test', $query);

        $this->expectNotToPerformAssertions();
    }

    public static function validSingularQueryProvider(): \Generator
    {
        yield 'current query' => ['@.name'];
        yield 'root query' => ['$.name'];
        yield 'bracket query' => ['@["name"]'];
        yield 'query with spaces' => ['  @.name  '];
        yield 'array wildcard query' => ['@.items[*]'];
        yield 'nested wildcard query' => ['@.items.*.name'];
    }

    #[DataProvider('nonSingularQueryProvider')]
    public function testAssertIsSingularQueryWithNonSingularQuery(string $query)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "test" argument must be a singular query.');

        $this->trait->assertIsSingularQuery('test', $query);
    }

    public static function nonSingularQueryProvider(): \Generator
    {
        yield 'wildcard query' => ['@.*'];
    }

    #[DataProvider('validQueryProvider')]
    public function testAssertIsQueryWithValidQuery(string $query)
    {
        $this->trait->assertIsQuery('test', $query);

        $this->expectNotToPerformAssertions();
    }

    public static function validQueryProvider(): \Generator
    {
        yield 'current query' => ['@.items'];
        yield 'root query' => ['$.items'];
        yield 'complex query' => ['@.items[?(@.active == true)]'];
    }

    #[DataProvider('literalArgumentProvider')]
    public function testAssertIsQueryWithLiteral(string $literal)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "test" requires a query argument, not a literal.');

        $this->trait->assertIsQuery('test', $literal);
    }

    public static function literalArgumentProvider(): \Generator
    {
        yield 'numeric literal' => ['123'];
        yield 'float literal' => ['3.14'];
        yield 'string literal' => ['"hello"'];
        yield 'single quoted string literal' => ["'hello'"];
        yield 'boolean true literal' => ['true'];
        yield 'boolean false literal' => ['false'];
        yield 'null literal' => ['null'];
        yield 'scientific notation literal' => ['1e10'];
    }
}
