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
    protected function createValidator()
    {
        return new DivisibleByValidator();
    }

    protected function createConstraint(array $options = null): Constraint
    {
        return new DivisibleBy($options);
    }

    protected function getErrorCode(): ?string
    {
        return DivisibleBy::NOT_DIVISIBLE_BY;
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons(): array
    {
        return [
            [-7, 1],
            [0, 3.1415],
            [42, 42],
            [42, 21],
            [3.25, 0.25],
            ['100', '10'],
            [4.1, 0.1],
            [-4.1, 0.1],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisonsToPropertyPath(): array
    {
        return [
            [25],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons(): array
    {
        return [
            [1, '1', 2, '2', 'integer'],
            [10, '10', 3, '3', 'integer'],
            [10, '10', 0, '0', 'integer'],
            [42, '42', INF, 'INF', 'double'],
            [4.15, '4.15', 0.1, '0.1', 'double'],
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

    public function throwsOnNonNumericValuesProvider()
    {
        return [
            [\stdClass::class, 2, new \stdClass()],
            [\ArrayIterator::class, new \ArrayIterator(), 12],
        ];
    }
}
