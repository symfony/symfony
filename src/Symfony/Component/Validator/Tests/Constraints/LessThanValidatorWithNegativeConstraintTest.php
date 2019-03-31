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

use Symfony\Component\Validator\Constraints\Negative;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class LessThanValidatorWithNegativeConstraintTest extends LessThanValidatorTest
{
    protected function createConstraint(array $options = null)
    {
        return new Negative();
    }

    /**
     * {@inheritdoc}
     */
    public function provideValidComparisons()
    {
        return [
            [-1, 0],
            [-2, 0],
            [-2.5, 0],
            [null, 0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function provideInvalidComparisons()
    {
        return [
            [0, '0', 0, '0', 'integer'],
            [2, '2', 0, '0', 'integer'],
            [2.5, '2.5', 0, '0', 'integer'],
            [333, '333', 0, '0', 'integer'],
        ];
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage The "propertyPath" option of the "Symfony\Component\Validator\Constraints\Negative" constraint cannot be set.
     */
    public function testThrowsConstraintExceptionIfPropertyPath()
    {
        return new Negative(['propertyPath' => 'field']);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage The "value" option of the "Symfony\Component\Validator\Constraints\Negative" constraint cannot be set.
     */
    public function testThrowsConstraintExceptionIfValue()
    {
        return new Negative(['value' => 0]);
    }

    /**
     * @dataProvider provideInvalidConstraintOptions
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage requires either the "value" or "propertyPath" option to be set.
     */
    public function testThrowsConstraintExceptionIfNoValueOrPropertyPath($options)
    {
        $this->markTestSkipped('Value option always set for Negative constraint');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage requires only one of the "value" or "propertyPath" options to be set, not both.
     */
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

    /**
     * @dataProvider provideValidComparisonsToPropertyPath
     */
    public function testValidComparisonToPropertyPathOnArray($comparedValue)
    {
        $this->markTestSkipped('PropertyPath option is not used in Positive constraint');
    }
}
