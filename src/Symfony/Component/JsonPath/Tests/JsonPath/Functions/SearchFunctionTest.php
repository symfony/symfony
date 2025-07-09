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
use Symfony\Component\JsonPath\Functions\SearchFunction;

class SearchFunctionTest extends TestCase
{
    private SearchFunction $function;

    protected function setUp(): void
    {
        $this->function = new SearchFunction();
    }

    public function testInvokeWithMatchingSubstring()
    {
        $result = ($this->function)([['hello world'], ['world']], 'context');
        $this->assertTrue($result);
    }

    public function testInvokeWithNonMatchingSubstring()
    {
        $this->assertFalse(($this->function)([['hello world'], ['xyz']], 'context'));
    }

    public function testInvokeWithRegexPattern()
    {
        $this->assertTrue(($this->function)([['hello123'], ['\\d+']], 'context'));
    }

    public function testInvokeWithNonMatchingRegexPattern()
    {
        $this->assertFalse(($this->function)([['hello'], ['\\d+']], 'context'));
    }

    public function testInvokeWithCaseInsensitivePattern()
    {
        $this->assertFalse(($this->function)([['Hello World'], ['hello']], 'context'));
    }

    public function testInvokeWithDotPattern()
    {
        $this->assertTrue(($this->function)([['abc'], ['.']], 'context'));
    }

    public function testInvokeWithStartOfString()
    {
        $this->assertTrue(($this->function)([['hello world'], ['hello']], 'context'));
    }

    public function testInvokeWithEndOfString()
    {
        $this->assertTrue(($this->function)([['hello world'], ['world']], 'context'));
    }

    public function testInvokeWithMiddleOfString()
    {
        $this->assertTrue(($this->function)([['hello world'], ['o w']], 'context'));
    }

    public function testInvokeWithNonStringValue()
    {
        $this->assertFalse(($this->function)([[123], ['123']], 'context'));
    }

    public function testInvokeWithNonStringPattern()
    {
        $this->assertFalse(($this->function)([['hello'], [123]], 'context'));
    }

    public function testInvokeWithNullValue()
    {
        $this->assertFalse(($this->function)([[null], ['pattern']], 'context'));
    }

    public function testInvokeWithNullPattern()
    {
        $this->assertFalse(($this->function)([['value'], [null]], 'context'));
    }

    public function testInvokeWithEmptyArgs()
    {
        $this->assertFalse(($this->function)([], 'context'));
    }

    public function testInvokeWithSingleArg()
    {
        $this->assertFalse(($this->function)([['value']], 'context'));
    }

    public function testInvokeWithNonArrayResults()
    {
        $this->assertTrue(($this->function)(['hello world', 'world'], 'context'));
    }

    public function testInvokeWithUnicodeString()
    {
        $this->assertTrue(($this->function)([['héllo world'], ['héllo']], 'context'));
    }

    public function testInvokeWithInvalidRegex()
    {
        $this->assertFalse(($this->function)([['hello'], ['[']], 'context'));
    }

    #[DataProvider('invalidRegexPatternProvider')]
    public function testInvokeWithInvalidRegexPatterns(string $invalidPattern)
    {
        $this->assertFalse(($this->function)([['hello'], [$invalidPattern]], 'context'));
    }

    public static function invalidRegexPatternProvider(): \Generator
    {
        yield 'unclosed bracket' => ['['];
        yield 'unclosed parenthesis' => ['('];
        yield 'invalid quantifier' => ['*'];
        yield 'invalid escape sequence' => ['\\'];
    }

    public function testInvokeWithEmptyString()
    {
        $this->assertTrue(($this->function)([[''], ['']], 'context'));
    }

    public function testInvokeWithEmptyPattern()
    {
        $this->assertTrue(($this->function)([['hello'], ['']], 'context'));
    }

    public function testValidateWithCorrectArgumentCount()
    {
        $this->function->validate('search', ['@.name', '"pattern"']);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidArgumentCountProvider')]
    public function testValidateThrowsExceptionWithIncorrectArgumentCount(array $args)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "search" requires exactly 2 argument(s).');

        $this->function->validate('search', $args);
    }

    public static function invalidArgumentCountProvider(): \Generator
    {
        yield 'no arguments' => [[]];
        yield 'one argument' => [['@.name']];
        yield 'three arguments' => [['@.name', '"pattern"', 'extra']];
    }
}
