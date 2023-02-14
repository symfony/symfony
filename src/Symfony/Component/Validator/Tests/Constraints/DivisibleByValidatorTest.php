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
use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\DivisibleByValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class DivisibleByValidatorTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): DivisibleByValidator
    {
        return new DivisibleByValidator();
    }

    protected static function createConstraint(array $options = null): Constraint
    {
        return new DivisibleBy($options);
    }

    protected function getErrorCode(): ?string
    {
        return DivisibleBy::NOT_DIVISIBLE_BY;
    }

    public static function provideValidComparisons(): array
    {
        return [
            [-7, 1],
            [0, 3.1415],
            [42, 42],
            [42, 21],
            [10.12, 0.01],
            [10.12, 0.001],
            [1.133, 0.001],
            [1.1331, 0.0001],
            [1.13331, 0.00001],
            [1.13331, 0.000001],
            [1, 0.1],
            [1, 0.01],
            [1, 0.001],
            [1, 0.0001],
            [1, 0.00001],
            [1, 0.000001],
            [3.25, 0.25],
            ['100', '10'],
            [4.1, 0.1],
            [-4.1, 0.1],
        ];
    }

    public static function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [25],
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'int'],
            [10, '10', 3, '3', 'int'],
            [10, '10', 0, '0', 'int'],
            [42, '42', \INF, 'INF', 'float'],
            [4.15, '4.15', 0.1, '0.1', 'float'],
            [10.123, '10.123', 0.01, '0.01', 'float'],
            ['22', '"22"', '10', '"10"', 'string'],
        ];
    }

    /**
     * @dataProvider throwsOnNonNumericValuesProvider
     */
    public function testThrowsOnNonNumericValues(string $expectedGivenType, $value, $comparedValue)
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('Expected argument of type "numeric", "%s" given', $expectedGivenType));

        $this->validator->validate($value, $this->createConstraint([
            'value' => $comparedValue,
        ]));
    }

    public static function throwsOnNonNumericValuesProvider()
    {
        return [
            [\stdClass::class, 2, new \stdClass()],
            [\ArrayIterator::class, new \ArrayIterator(), 12],
        ];
    }

    public static function provideComparisonsToNullValueAtPropertyPath()
    {
        self::markTestSkipped('DivisibleByValidator rejects null values.');
    }
}
