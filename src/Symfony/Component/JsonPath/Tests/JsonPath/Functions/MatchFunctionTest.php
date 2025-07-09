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
use Symfony\Component\JsonPath\Functions\MatchFunction;

class MatchFunctionTest extends TestCase
{
    private MatchFunction $function;

    protected function setUp(): void
    {
        $this->function = new MatchFunction();
    }

    public function testInvokeWithMatchingStrings()
    {
        $this->assertTrue(($this->function)([['hello'], ['hello']], 'context'));
    }

    public function testInvokeWithNonMatchingStrings()
    {
        $this->assertFalse(($this->function)([['hello'], ['world']], 'context'));
    }

    public function testInvokeWithRegexPattern()
    {
        $this->assertTrue(($this->function)([['hello123'], ['hello\\d+']], 'context'));
    }

    public function testInvokeWithNonMatchingRegexPattern()
    {
        $this->assertFalse(($this->function)([['hello'], ['\\d+']], 'context'));
    }

    public function testInvokeWithCaseInsensitivePattern()
    {
        $this->assertFalse(($this->function)([['Hello'], ['hello']], 'context'));
    }

    public function testInvokeWithDotPattern()
    {
        $this->assertTrue(($this->function)([['a'], ['.']], 'context'));
    }

    public function testInvokeWithStartAnchor()
    {
        $this->assertFalse(($this->function)([['hello world'], ['hello']], 'context'));
    }

    public function testInvokeWithEndAnchor()
    {
        $this->assertFalse(($this->function)([['hello world'], ['world']], 'context'));
    }

    public function testInvokeWithFullMatch()
    {
        $this->assertTrue(($this->function)([['hello world'], ['hello world']], 'context'));
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
        $this->assertTrue(($this->function)(['hello', 'hello'], 'context'));
    }

    public function testInvokeWithUnicodeString()
    {
        $this->assertTrue(($this->function)([['héllo'], ['héllo']], 'context'));
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
    }

    public function testValidateWithCorrectArgumentCount()
    {
        $this->function->validate('match', ['@.name', '"pattern"']);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidArgumentCountProvider')]
    public function testValidateThrowsExceptionWithIncorrectArgumentCount(array $args)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('the JsonPath function "match" requires exactly 2 argument(s).');

        $this->function->validate('match', $args);
    }

    public static function invalidArgumentCountProvider(): \Generator
    {
        yield 'no arguments' => [[]];
        yield 'one argument' => [['@.name']];
        yield 'three arguments' => [['@.name', '"pattern"', 'extra']];
    }
}
