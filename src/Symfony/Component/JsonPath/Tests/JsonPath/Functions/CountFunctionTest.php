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
use Symfony\Component\JsonPath\Functions\CountFunction;

class CountFunctionTest extends TestCase
{
    private CountFunction $function;

    protected function setUp(): void
    {
        $this->function = new CountFunction();
    }

    public function testInvokeWithArrayResults()
    {
        $this->assertSame(3, ($this->function)([['a', 'b', 'c']], 'context'));
    }

    public function testInvokeWithEmptyArray()
    {
        $this->assertSame(0, ($this->function)([[]], 'context'));
    }

    public function testInvokeWithNoArgs()
    {
        $this->assertSame(0, ($this->function)([], 'context'));
    }

    public function testInvokeWithNonArrayResults()
    {
        $this->assertSame(0, ($this->function)(['string'], 'context'));
    }

    public function testInvokeWithNullResults()
    {
        $this->assertSame(0, ($this->function)([null], 'context'));
    }

    public function testInvokeWithNumericResults()
    {
        $this->assertSame(0, ($this->function)([123], 'context'));
    }

    public function testValidateWithCorrectArgumentCount()
    {
        $this->function->validate('count', ['@.items']);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateThrowsExceptionWithIncorrectArgumentCount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "count" requires exactly 1 argument(s).');

        $this->function->validate('count', []);
    }

    public function testValidateThrowsExceptionWithTooManyArguments()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "count" requires exactly 1 argument(s).');

        $this->function->validate('count', ['@.items', '@.other']);
    }

    #[DataProvider('invalidLiteralArgumentProvider')]
    public function testValidateThrowsExceptionWithLiteralArgument(string $literal)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "count" requires a query argument, not a literal.');

        $this->function->validate('count', [$literal]);
    }

    public static function invalidLiteralArgumentProvider(): \Generator
    {
        yield 'numeric literal' => ['123'];
        yield 'string literal' => ['"string"'];
        yield 'boolean literal' => ['true'];
        yield 'null literal' => ['null'];
        yield 'float literal' => ['3.14'];
        yield 'scientific notation literal' => ['1e10'];
    }

    public function testValidateWithValidQuery()
    {
        $this->function->validate('count', ['@.items[*]']);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateWithRootQuery()
    {
        $this->function->validate('count', ['$.items']);

        $this->expectNotToPerformAssertions();
    }
}
