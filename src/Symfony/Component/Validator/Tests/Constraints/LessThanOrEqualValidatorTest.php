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
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class LessThanOrEqualValidatorTest extends AbstractComparisonValidatorTestCase
{
    use CompareWithNullValueAtPropertyAtTestTrait;
    use IcuCompatibilityTrait;
    use InvalidComparisonToValueTestTrait;
    use ThrowsOnInvalidStringDatesTestTrait;
    use ValidComparisonToValueTrait;

    protected function createValidator(): LessThanOrEqualValidator
    {
        return new LessThanOrEqualValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new LessThanOrEqual($options);
    }

    protected function getErrorCode(): ?string
    {
        return LessThanOrEqual::TOO_HIGH_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [1, 2],
            [1, 1],
            [new \DateTime('2000-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2000-01-01'), new \DateTime('2020-01-01')],
            [new \DateTime('2000-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01'), '2020-01-01'],
            [new \DateTime('2000-01-01 UTC'), '2000-01-01 UTC'],
            [new \DateTime('2000-01-01 UTC'), '2020-01-01 UTC'],
            [new ComparisonTest_Class(4), new ComparisonTest_Class(5)],
            [new ComparisonTest_Class(5), new ComparisonTest_Class(5)],
            ['a', 'a'],
            ['a', 'z'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [4],
            [5],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [2, '2', 1, '1', 'int'],
            [new \DateTime('2010-01-01'), self::normalizeIcuSpaces("Jan 1, 2010, 12:00\u{202F}AM"), new \DateTime('2000-01-01'), self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2010-01-01'), self::normalizeIcuSpaces("Jan 1, 2010, 12:00\u{202F}AM"), '2000-01-01', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new \DateTime('2010-01-01 UTC'), self::normalizeIcuSpaces("Jan 1, 2010, 12:00\u{202F}AM"), '2000-01-01 UTC', self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [new ComparisonTest_Class(5), '5', new ComparisonTest_Class(4), '4', __NAMESPACE__.'\ComparisonTest_Class'],
            ['c', '"c"', 'b', '"b"', 'string'],
        ];
    }
}
