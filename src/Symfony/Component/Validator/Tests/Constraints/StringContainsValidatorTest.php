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
use Symfony\Component\Validator\Constraints\StringContains;
use Symfony\Component\Validator\Constraints\StringContainsValidator;

class StringContainsValidatorTest extends AbstractStringContainsValidatorTestCase
{
    private const BASE = 'Lorem ipsum dolor sit amet.';

    protected function createConstraint(array $options): AbstractStringContains
    {
        return new StringContains($options);
    }

    protected function createValidator(): AbstractStringContainsValidator
    {
        return new StringContainsValidator();
    }

    public function provideValidCaseSensitiveComparisons(): iterable
    {
        yield 'word' => [self::BASE, 'ipsum'];
        yield 'words' => [self::BASE, 'Lorem ipsum'];
        yield 'half word' => [self::BASE, 'sum'];
        yield 'whole sentence' => [self::BASE, 'Lorem ipsum dolor sit amet.'];
        yield 'array input' => [self::BASE, ['ipsum']];
        yield 'array-n input' => [self::BASE, ['ipsum', 'sit amet']];
    }

    public function provideValidCaseInsensitiveComparisons(): iterable
    {
        yield 'word' => [self::BASE, 'IPSUM'];
        yield 'words' => [self::BASE, 'lorem ipsum'];
        yield 'half word' => [self::BASE, 'SUM'];
        yield 'whole sentence' => [self::BASE, 'lorem ipsum dolor sit AMET.'];
        yield 'array input' => [self::BASE, ['Ipsum']];
        yield 'array-n input' => [self::BASE, ['IPSUM', 'SIT amet']];
    }

    public function provideInvalidCaseSensitiveComparisons(): iterable
    {
        yield [self::BASE, 'foo', StringContains::NOT_CONTAINS_ERROR];
        yield 'array input' => [self::BASE, ['foo'], StringContains::NOT_CONTAINS_ERROR];
        yield 'array-n input' => [self::BASE, ['Lorem', 'foo'], StringContains::NOT_CONTAINS_ERROR];
    }

    public function provideInvalidCaseInsensitiveComparisons(): iterable
    {
        yield [self::BASE, 'foo', StringContains::NOT_CONTAINS_ERROR];
        yield 'array input' => [self::BASE, ['foo'], StringContains::NOT_CONTAINS_ERROR];
        yield 'array input' => [self::BASE, ['foo', 'bar'], StringContains::NOT_CONTAINS_ERROR];
    }

    public function provideValidComparisonUsingCallbackWithoutContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithoutContext() as $callback) {
            yield ['an foo text', $callback];
        }
    }

    public function provideValidComparisonUsingCallbackWithContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithContext() as $callback) {
            yield ['an foo text', $callback];
        }
    }
}
