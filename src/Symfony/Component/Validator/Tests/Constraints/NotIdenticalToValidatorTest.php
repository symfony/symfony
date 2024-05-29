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
use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;
use Symfony\Component\Validator\Tests\IcuCompatibilityTrait;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
class NotIdenticalToValidatorTest extends AbstractComparisonValidatorTestCase
{
    use IcuCompatibilityTrait;

    protected function createValidator(): NotIdenticalToValidator
    {
        return new NotIdenticalToValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new NotIdenticalTo($options);
    }

    protected function getErrorCode(): ?string
    {
        return NotIdenticalTo::IS_IDENTICAL_ERROR;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [1, 2],
            ['2', 2],
            ['22', '333'],
            [new \DateTime('2001-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2000-01-01'), new \DateTime('2000-01-01')],
            [new \DateTime('2001-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01'), '2000-01-01'],
            [new \DateTime('2001-01-01'), '2000-01-01'],
            [new \DateTime('2000-01-01 UTC'), '2000-01-01 UTC'],
            [null, 1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [0],
        ];
    }

    public static function provideAllInvalidComparisons(): array
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        // Don't call addPhp5Dot5Comparisons() automatically, as it does
        // not take care of identical objects
        $comparisons = self::provideInvalidComparisons();

        date_default_timezone_set($timezone);

        return $comparisons;
    }

    public static function provideInvalidComparisons(): array
    {
        $date = new \DateTime('2000-01-01');
        $object = new ComparisonTest_Class(2);

        return [
            [3, '3', 3, '3', 'int'],
            ['a', '"a"', 'a', '"a"', 'string'],
            [$date, self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), $date, self::normalizeIcuSpaces("Jan 1, 2000, 12:00\u{202F}AM"), 'DateTime'],
            [$object, '2', $object, '2', __NAMESPACE__.'\ComparisonTest_Class'],
        ];
    }

    public static function provideComparisonsToNullValueAtPropertyPath(): array
    {
        return [
            [5, '5', true],
        ];
    }
}
