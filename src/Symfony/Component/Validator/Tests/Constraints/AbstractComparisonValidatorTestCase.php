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
use Symfony\Component\Validator\Constraints\AbstractComparisonValidator;

/**
 * @author Daniel Holmes <daniel@danielholmes.org>
 */
abstract class AbstractComparisonValidatorTestCase extends \PHPUnit_Framework_TestCase
{
    private $validator;
    private $context;

    protected function setUp()
    {
        $this->validator = $this->createValidator();
        $this->context = $this->getMockBuilder('Symfony\Component\Validator\ExecutionContext')
            ->disableOriginalConstructor()
            ->getMock();
        $this->validator->initialize($this->context);
    }

    /**
     * @return AbstractComparisonValidator
     */
    abstract protected function createValidator();

    public function testThrowsConstraintExceptionIfNoValueOrProperty()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $comparison = $this->createConstraint(array());
        $this->validator->validate('some value', $comparison);
    }

    /**
     * @dataProvider provideValidComparisons
     * @param mixed $dirtyValue
     * @param mixed $comparisonValue
     */
    public function testValidComparisonToValue($dirtyValue, $comparisonValue)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = $this->createConstraint(array('value' => $comparisonValue));

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('property1'));

        $this->validator->validate($dirtyValue, $constraint);
    }

    /**
     * @return array
     */
    abstract public function provideValidComparisons();

    /**
     * @dataProvider provideInvalidComparisons
     * @param mixed  $dirtyValue
     * @param mixed  $comparedValue
     * @param mixed  $comparedValueString
     * @param string $comparedValueType
     */
    public function testInvalidComparisonToValue($dirtyValue, $comparedValue, $comparedValueString, $comparedValueType)
    {
        $constraint = $this->createConstraint(array('value' => $comparedValue));
        $constraint->message = 'Constraint Message';

        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('property1'));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('Constraint Message', array(
                '{{ value }}' => $comparedValueString,
                '{{ compared_value }}' => $comparedValueString,
                '{{ compared_value_type }}' => $comparedValueType
            ));

        $this->validator->validate($dirtyValue, $constraint);
    }

    /**
     * @return array
     */
    abstract public function provideInvalidComparisons();

    /**
     * @param  array      $options Options for the constraint
     * @return Constraint
     */
    abstract protected function createConstraint(array $options);
}
