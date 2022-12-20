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
use Symfony\Component\Validator\Constraints\AbstractComparison;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class GreaterThanOrEqualValidatorWithPositiveOrZeroConstraintTest extends GreaterThanOrEqualValidatorTest
{
    protected function createConstraint(array $options = null): Constraint
    {
        return new PositiveOrZero();
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons(): array
    {
        return [
            [0, 0],
            [1, 0],
            [2, 0],
            [2.5, 0],
            ['0', '0'],
            ['333', '0'],
            [null, 0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons(): array
    {
        return [
            [-1, '-1', 0, '0', 'int'],
            [-2, '-2', 0, '0', 'int'],
            [-2.5, '-2.5', 0, '0', 'int'],
        ];
    }

    public function testThrowsConstraintExceptionIfPropertyPath()
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('The "propertyPath" option of the "Symfony\Component\Validator\Constraints\PositiveOrZero" constraint cannot be set.');

        return new PositiveOrZero(['propertyPath' => 'field']);
    }

    public function testThrowsConstraintExceptionIfValue()
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('The "value" option of the "Symfony\Component\Validator\Constraints\PositiveOrZero" constraint cannot be set.');

        return new PositiveOrZero(['value' => 0]);
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('requires either the "value" or "propertyPath" option to be set.');
        self::markTestSkipped('Value option always set for PositiveOrZero constraint');
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('requires only one of the "value" or "propertyPath" options to be set, not both.');
        self::markTestSkipped('Value option is set for PositiveOrZero constraint automatically');
    }

    public function testInvalidValuePath()
    {
        self::markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        self::markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }

    /**
     * @dataProvider throwsOnInvalidStringDatesProvider
     */
    public function testThrowsOnInvalidStringDates(AbstractComparison $constraint, $expectedMessage, $value)
    {
        self::markTestSkipped('The compared value cannot be an invalid string date because it is hardcoded to 0.');
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        self::markTestSkipped('PropertyPath option is not used in PositiveOrZero constraint');
    }
}
