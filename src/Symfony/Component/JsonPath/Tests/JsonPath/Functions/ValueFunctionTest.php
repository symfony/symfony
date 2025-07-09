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
use Symfony\Component\JsonPath\Functions\ValueFunction;
use Symfony\Component\JsonPath\Nothing;

class ValueFunctionTest extends TestCase
{
    private ValueFunction $function;

    protected function setUp(): void
    {
        $this->function = new ValueFunction();
    }

    public function testInvokeWithSingleValue()
    {
        $this->assertSame('hello', ($this->function)([['hello']], 'context'));
    }

    public function testInvokeWithMultipleValues()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([['hello', 'world']], 'context'));
    }

    public function testInvokeWithEmptyArray()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([[]], 'context'));
    }

    public function testInvokeWithNoArgs()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([], 'context'));
    }

    public function testInvokeWithNonArrayResults()
    {
        $this->assertSame('hello', ($this->function)(['hello'], 'context'));
    }

    public function testInvokeWithNullValue()
    {
        $this->assertNull(($this->function)([[null]], 'context'));
    }

    public function testInvokeWithNumericValue()
    {
        $this->assertSame(123, ($this->function)([[123]], 'context'));
    }

    public function testInvokeWithBooleanValue()
    {
        $this->assertTrue(($this->function)([[true]], 'context'));
    }

    public function testInvokeWithArrayValue()
    {
        $this->assertSame(['a', 'b'], ($this->function)([[['a', 'b']]], 'context'));
    }

    public function testInvokeWithObjectValue()
    {
        $object = ['key' => 'value'];
        $this->assertSame($object, ($this->function)([[$object]], 'context'));
    }

    public function testInvokeWithFloatValue()
    {
        $this->assertSame(3.14, ($this->function)([[3.14]], 'context'));
    }

    public function testInvokeWithZeroValue()
    {
        $this->assertSame(0, ($this->function)([[0]], 'context'));
    }

    public function testInvokeWithEmptyStringValue()
    {
        $this->assertSame('', ($this->function)([['']], 'context'));
    }

    public function testInvokeWithFalseValue()
    {
        $this->assertFalse(($this->function)([[false]], 'context'));
    }

    public function testInvokeWithThreeValues()
    {
        $this->assertSame(Nothing::Nothing, ($this->function)([['a', 'b', 'c']], 'context'));
    }

    public function testValidateWithCorrectArgumentCount()
    {
        $this->function->validate('value', ['@.name']);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidArgumentCountProvider')]
    public function testValidateThrowsExceptionWithIncorrectArgumentCount(array $args)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "value" requires exactly 1 argument(s).');
        $this->function->validate('value', $args);
    }

    public static function invalidArgumentCountProvider(): \Generator
    {
        yield 'no arguments' => [[]];
        yield 'two arguments' => [['@.name', '@.other']];
    }

    #[DataProvider('validQueryProvider')]
    public function testValidateWithValidQuery(string $query)
    {
        $this->function->validate('value', [$query]);

        $this->expectNotToPerformAssertions();
    }

    public static function validQueryProvider(): \Generator
    {
        yield 'indexed query' => ['@.items[0]'];
        yield 'root query' => ['$.name'];
    }
}
