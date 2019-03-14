<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\AbstractStringContains;
use Symfony\Component\Validator\Constraints\AbstractStringContainsValidator;
use Symfony\Component\Validator\Constraints\StringNotContains;
use Symfony\Component\Validator\Constraints\StringNotContainsValidator;

function expected_callback_string_not_contains()
{
    return 'bar';
}

class StringNotContainsValidatorTest extends AbstractStringContainsValidatorTestCase
{
    private const BASE = 'Lorem ipsum dolor sit amet.';

    protected function createConstraint(array $options): AbstractStringContains
    {
        return new StringNotContains($options);
    }

    protected function createValidator(): AbstractStringContainsValidator
    {
        return new StringNotContainsValidator();
    }

    public function provideValidCaseSensitiveComparisons(): iterable
    {
        yield [self::BASE, 'lorem'];
        yield 'half-word' => [self::BASE, 'Sum'];
        yield 'array input' => [self::BASE, ['lorem']];
        yield 'array-n input' => [self::BASE, ['lorem', 'Ipsum']];
    }

    public function provideValidCaseInsensitiveComparisons(): iterable
    {
        yield [self::BASE, 'foo'];
        yield 'array input' => [self::BASE, ['foo']];
        yield 'array-n input' => [self::BASE, ['foo', 'bar']];
    }

    public function provideInvalidCaseSensitiveComparisons(): iterable
    {
        yield [self::BASE, 'Lorem', StringNotContains::CONTAINS_ERROR];
        yield 'half-word' => [self::BASE, 'sum', StringNotContains::CONTAINS_ERROR];
        yield 'array input' => [self::BASE, ['Lorem'], StringNotContains::CONTAINS_ERROR];
        yield 'array-n input' => [self::BASE, ['foo', 'ipsum'], StringNotContains::CONTAINS_ERROR];
    }

    public function provideInvalidCaseInsensitiveComparisons(): iterable
    {
        yield [self::BASE, 'lorem', StringNotContains::CONTAINS_ERROR];
        yield 'array input' => [self::BASE, ['SUM'], StringNotContains::CONTAINS_ERROR];
        yield 'array-n input' => [self::BASE, ['lorem', 'SUM'], StringNotContains::CONTAINS_ERROR];
    }

    public function provideValidComparisonUsingCallbackWithoutContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithoutContext() as $callback) {
            yield ['an text', $callback];
        }
    }

    public function provideValidComparisonUsingCallbackWithContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithContext() as $callback) {
            yield ['an text', $callback];
        }
    }
}
