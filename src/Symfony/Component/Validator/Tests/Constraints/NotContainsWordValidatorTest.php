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
use Symfony\Component\Validator\Constraints\NotContainsWord;
use Symfony\Component\Validator\Constraints\NotContainsWordValidator;

class NotContainsWordValidatorTest extends AbstractStringContainsValidatorTestCase
{
    private const BASE = 'Lorem ipsum dolor sit amet, consectetur-adipiscing elit.';

    protected function createConstraint(array $options): AbstractStringContains
    {
        return new NotContainsWord($options);
    }

    protected function createValidator(): AbstractStringContainsValidator
    {
        return new NotContainsWordValidator();
    }

    public function provideValidCaseSensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'IPSUM'];
        yield 'in the beginning' => [self::BASE, 'lorem'];
        yield 'with comma' => [self::BASE, 'AMET'];
        yield 'with dot' => [self::BASE, 'ELIT'];
        yield 'hyphenated' => [self::BASE, 'Consectetur-Adipiscing'];
        yield 'hyphenated right' => [self::BASE, 'Consectetur'];
        yield 'hyphenated left' => [self::BASE, 'Adipiscing'];
        yield 'array input' => [self::BASE, ['DOLOR']];
        yield 'array-n input' => [self::BASE, ['lorem', 'IPSUM', 'DOLOR', 'SIT', 'AMET', 'Consectetur-Adipiscing', 'ELIT']];
    }

    public function provideValidCaseInsensitiveComparisons(): iterable
    {
        yield 'simple' => [self::BASE, 'foo'];
        yield 'half word' => [self::BASE, 'sum'];
        yield 'array input' => [self::BASE, ['foo', 'bar']];
    }

    public function provideInvalidCaseSensitiveComparisons(): iterable
    {
        yield 'simple' => [self::BASE, 'ipsum', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'array input' => [self::BASE, ['ipsum', 'sit'], NotContainsWord::CONTAINS_WORD_ERROR];
    }

    public function provideInvalidCaseInsensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'IPSUM', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'in the beginning' => [self::BASE, 'lorem', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'with comma' => [self::BASE, 'AMET', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'with dot' => [self::BASE, 'ELIT', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'hyphenated' => [self::BASE, 'Consectetur-Adipiscing', NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'array input' => [self::BASE, ['DOLOR'], NotContainsWord::CONTAINS_WORD_ERROR];
        yield 'array-n input' => [self::BASE, ['lorem', 'IPSUM', 'DOLOR', 'SIT', 'AMET', 'Consectetur-Adipiscing', 'ELIT'], NotContainsWord::CONTAINS_WORD_ERROR];
    }

    public function provideValidComparisonUsingCallbackWithoutContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithoutContext() as $callback) {
            yield ['a bar text', $callback];
        }
    }

    public function provideValidComparisonUsingCallbackWithContext(): iterable
    {
        foreach ($this->alwaysFooCallbacksWithContext() as $callback) {
            yield ['a bar text', $callback];
        }
    }
}
