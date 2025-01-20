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
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;
use Symfony\Component\Validator\Constraints\NegativeOrZero;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class LessThanOrEqualValidatorWithNegativeOrZeroConstraintTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): LessThanOrEqualValidator
    {
        return new LessThanOrEqualValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new NegativeOrZero($options);
    }

    public static function provideValidComparisons(): array
    {
        return [
            [0, 0],
            [-1, 0],
            [-2, 0],
            [-2.5, 0],
            [null, 0],
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
            [2, '2', 0, '0', 'int'],
            [2.5, '2.5', 0, '0', 'int'],
            [333, '333', 0, '0', 'int'],
        ];
    }

    public function testThrowsConstraintExceptionIfPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "propertyPath" option of the "Symfony\Component\Validator\Constraints\NegativeOrZero" constraint cannot be set.');

        return new NegativeOrZero(['propertyPath' => 'field']);
    }

    public function testThrowsConstraintExceptionIfValue()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "value" option of the "Symfony\Component\Validator\Constraints\NegativeOrZero" constraint cannot be set.');

        return new NegativeOrZero(['value' => 0]);
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->markTestSkipped('Value option always set for NegativeOrZero constraint');
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->markTestSkipped('Value option is set for NegativeOrZero constraint automatically');
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $this->markTestSkipped('PropertyPath option is not used in NegativeOrZero constraint');
    }

    public function testInvalidValuePath()
    {
        $this->markTestSkipped('PropertyPath option is not used in NegativeOrZero constraint');
    }

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        $this->markTestSkipped('PropertyPath option is not used in NegativeOrZero constraint');
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        $this->markTestSkipped('PropertyPath option is not used in NegativeOrZero constraint');
    }
}
