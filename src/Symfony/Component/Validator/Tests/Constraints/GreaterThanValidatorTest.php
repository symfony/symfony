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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class GreaterThanValidatorTest extends AbstractComparisonValidatorTestCase
{
    use IcuCompatibilityTrait;

    protected function createValidator(): GreaterThanValidator
    {
        return new GreaterThanValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new GreaterThan($options);
    }

    protected function getErrorCode(): ?string
    {
        return GreaterThan::TOO_LOW_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [2, 1],
            [new \DateTime('2005/01/01'), new \DateTime('2001/01/01')],
            [new \DateTime('2005/01/01'), '2001/01/01'],
            [new \DateTime('2005/01/01 UTC'), '2001/01/01 UTC'],
            [new ComparisonTest_Class(5), new ComparisonTest_Class(4)],
            ['333', '22'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [6],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'int'],
            [2, '2', 2, '2', 'int'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), new \DateTime('2005/01/01'), self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2000/01/01', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2005/01/01 UTC', self::normalizeIcuSpaces("Jan 1, 2005, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2000/01/01 UTC'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), '2000/01/01 UTC', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new ComparisonTest_Class(4), '4', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            [new ComparisonTest_Class(5), '5', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            ['22', '"22"', '333', '"333"', 'string'],
            ['22', '"22"', '22', '"22"', 'string'],
        ];
    }

    public static function provideComparisonsToNullValueAtPropertyPath(): array
    {
        return [
            [5, '5', true],
        ];
    }
}
