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
use Symfony\Component\Validator\Constraints\LessThanValidator;
use Symfony\Component\Validator\Constraints\Negative;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class LessThanValidatorWithNegativeConstraintTest extends AbstractComparisonValidatorTestCase
{
    protected function createValidator(): LessThanValidator
    {
        return new LessThanValidator();
    }

    protected static function createConstraint(?array $options = null): Constraint
    {
        return new Negative($options);
    }

    public static function provideValidComparisons(): array
    {
        return [
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
        ];
    }

    public static function provideInvalidComparisons(): array
    {
        return [
            [0, '0', 0, '0', 'int'],
            [2, '2', 0, '0', 'int'],
            [2.5, '2.5', 0, '0', 'int'],
            [333, '333', 0, '0', 'int'],
        ];
    }

    public function testThrowsConstraintExceptionIfPropertyPath()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "propertyPath" option of the "Symfony\Component\Validator\Constraints\Negative" constraint cannot be set.');

        return new Negative(['propertyPath' => 'field']);
    }

    public function testThrowsConstraintExceptionIfValue()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->expectExceptionMessage('The "value" option of the "Symfony\Component\Validator\Constraints\Negative" constraint cannot be set.');

        return new Negative(['value' => 0]);
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->markTestSkipped('Value option always set for Negative constraint');
    }

    public function testThrowsConstraintExceptionIfBothValueAndPropertyPath()
    {
        $this->markTestSkipped('Value option is set for Negative constraint automatically');
    }

    public function testNoViolationOnNullObjectWithPropertyPath()
    {
        $this->markTestSkipped('PropertyPath option is not used in Negative constraint');
    }

    public function testInvalidValuePath()
    {
        $this->markTestSkipped('PropertyPath option is not used in Negative constraint');
    }

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPath($comparedValue)
    {
        $this->markTestSkipped('PropertyPath option is not used in Negative constraint');
    }

    public function testInvalidComparisonToPropertyPathAddsPathAsParameter()
    {
        $this->markTestSkipped('PropertyPath option is not used in Negative constraint');
    }
}
