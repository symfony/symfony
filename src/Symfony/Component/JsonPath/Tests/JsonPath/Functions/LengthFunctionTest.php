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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonPath\Functions\LengthFunction;
use Symfony\Component\JsonPath\Nothing;

class LengthFunctionTest extends TestCase
{
    private LengthFunction $function;

    protected function setUp(): void
    {
        $this->function = new LengthFunction();
    }

    public function testInvokeWithStringValue()
    {
        $this->assertSame(5, ($this->function)([['hello']], 'context'));
    }

    public function testInvokeWithEmptyString()
    {
        $this->assertSame(0, ($this->function)([['']], 'context'));
    }

    public function testInvokeWithUnicodeString()
    {
        $this->assertSame(5, ($this->function)([['h🐘llo']], 'context'));
    }

    public function testInvokeWithArrayValue()
    {
        $this->assertSame(3, ($this->function)([[['a', 'b', 'c']]], 'context'));
    }

    public function testInvokeWithEmptyArray()
    {
        $this->assertSame(0, ($this->function)([[[]]], 'context'));
    }

    public function testInvokeWithNullValue()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([[null]], 'context'));
    }

    public function testInvokeWithNumericValue()
    {
        $this->assertSame(0, ($this->function)([[123]], 'context'));
    }

    public function testInvokeWithBooleanValue()
    {
        $this->assertSame(0, ($this->function)([[true]], 'context'));
    }

    public function testInvokeWithNonArrayResults()
    {
        $this->assertSame(5, ($this->function)(['hello'], 'context'));
    }

    public function testInvokeWithNoArgs()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([], 'context'));
    }

    public function testInvokeWithEmptyArrayResults()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([[]], 'context'));
    }

    public function testValidateWithCorrectArgumentCount()
    {
        $this->function->validate('length', ['@.name']);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateThrowsExceptionWithIncorrectArgumentCount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "length" requires exactly 1 argument(s).');

        $this->function->validate('length', []);
    }

    public function testValidateThrowsExceptionWithTooManyArguments()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "length" requires exactly 1 argument(s).');

        $this->function->validate('length', ['@.name', '@.other']);
    }

    public function testValidateThrowsExceptionWithNonSingularQuery()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "length" argument must be a singular query.');

        $this->function->validate('length', ['@.*']);
    }

    #[DataProvider('validSingularQueryProvider')]
    public function testValidateWithValidSingularQuery(string $query)
    {
        $this->function->validate('length', [$query]);

        $this->expectNotToPerformAssertions();
    }

    public static function validSingularQueryProvider(): \Generator
    {
        yield 'current query' => ['@.name'];
        yield 'root query' => ['$.name'];
        yield 'bracket notation' => ['@["name"]'];
        yield 'array wildcard query' => ['@.items[*]'];
        yield 'nested wildcard query' => ['@.items.*.name'];
    }
}
