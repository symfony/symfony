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
use Symfony\Component\Validator\Constraints\ContainsWord;
use Symfony\Component\Validator\Constraints\ContainsWordValidator;

class ContainsWordValidatorTest extends AbstractStringContainsValidatorTestCase
{
    private const BASE = 'Lorem ipsum dolor sit amet, consectetur-adipiscing elit.';

    protected function createConstraint(array $options): AbstractStringContains
    {
        return new ContainsWord($options);
    }

    protected function createValidator(): AbstractStringContainsValidator
    {
        return new ContainsWordValidator();
    }

    public function provideValidCaseSensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'ipsum'];
        yield 'in the beginning' => [self::BASE, 'Lorem'];
        yield 'with comma' => [self::BASE, 'amet'];
        yield 'with dot' => [self::BASE, 'elit'];
        yield 'hyphenated' => [self::BASE, 'consectetur-adipiscing'];
        yield 'array input' => [self::BASE, ['dolor']];
        yield 'array-n input' => [self::BASE, ['Lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur-adipiscing', 'elit']];
    }

    public function provideValidCaseInsensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'IPSUM'];
        yield 'in the beginning' => [self::BASE, 'lorem'];
        yield 'with comma' => [self::BASE, 'AMET'];
        yield 'with dot' => [self::BASE, 'ELIT'];
        yield 'left' => [self::BASE, 'Consectetur-Adipiscing'];
        yield 'hyphenated right' => [self::BASE, 'consectetur'];
        yield 'hyphenated left' => [self::BASE, 'adipiscing'];
        yield 'array input' => [self::BASE, ['DOLOR']];
        yield 'array-n input' => [self::BASE, ['lorem', 'IPSUM', 'DOLOR', 'SIT', 'AMET', 'Consectetur-Adipiscing', 'ELIT']];
    }

    public function provideInvalidCaseSensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'IPSUM', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'in the beginning' => [self::BASE, 'lorem', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'with comma' => [self::BASE, 'AMET', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'with dot' => [self::BASE, 'ELIT', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'hyphenated' => [self::BASE, 'Consectetur-Adipiscing', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'array input' => [self::BASE, ['DOLOR'], ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'array-n input' => [self::BASE, ['lorem', 'IPSUM', 'DOLOR', 'SIT', 'AMET', 'Consectetur-Adipiscing', 'ELIT'], ContainsWord::NOT_CONTAINS_WORD_ERROR];
    }

    public function provideInvalidCaseInsensitiveComparisons(): iterable
    {
        yield 'isolated' => [self::BASE, 'foo', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'half word' => [self::BASE, 'sum', ContainsWord::NOT_CONTAINS_WORD_ERROR];
        yield 'array input' => [self::BASE, ['foo'], ContainsWord::NOT_CONTAINS_WORD_ERROR];
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
