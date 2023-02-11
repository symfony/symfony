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
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class LessThanValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): LessThanValidator
    {
        return new LessThanValidator();
    }

    protected static function createConstraint(array $options = null): Constraint
    {
        return new LessThan($options);
    }

    protected function getErrorCode(): ?string
    {
        return LessThan::TOO_HIGH_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [1, 2],
            [new \DateTime('2000-01-01'), new \DateTime('2010-01-01')],
            [new \DateTime('2000-01-01'), '2010-01-01'],
            [new \DateTime('2000-01-01 UTC'), '2010-01-01 UTC'],
            [new ComparisonTest_Class(4), new ComparisonTest_Class(5)],
            ['22', '333'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [4],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [3, '3', 2, '2', 'int'],
            [2, '2', 2, '2', 'int'],
            [new \DateTime('2010-01-01'), 'Jan 1, 2010, 12:00 AM', new \DateTime('2000-01-01'), 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2000-01-01'), 'Jan 1, 2000, 12:00 AM', new \DateTime('2000-01-01'), 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2010-01-01'), 'Jan 1, 2010, 12:00 AM', '2000-01-01', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2000-01-01'), 'Jan 1, 2000, 12:00 AM', '2000-01-01', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2010-01-01 UTC'), 'Jan 1, 2010, 12:00 AM', '2000-01-01 UTC', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new \DateTime('2000-01-01 UTC'), 'Jan 1, 2000, 12:00 AM', '2000-01-01 UTC', 'Jan 1, 2000, 12:00 AM', 'DateTime'],
            [new ComparisonTest_Class(5), '5', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            [new ComparisonTest_Class(6), '6', new ComparisonTest_Class(5), '5', __NAMESPACE__.'\ComparisonTest_Class'],
            ['333', '"333"', '22', '"22"', 'string'],
        ];
    }

    public static function provideComparisonsToNullValueAtPropertyPath()
    {
        return [
            [5, '5', true],
        ];
    }
}
