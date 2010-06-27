<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\Validator;
use Symfony\Components\Validator\ConstraintViolation;
use Symfony\Components\Validator\ConstraintViolationList;
use Symfony\Components\Validator\Mapping\Metadata;
use Symfony\Components\Validator\Specification\PropertySpecification;
use Symfony\Components\Validator\Specification\ClassSpecification;
use Symfony\Components\Validator\Specification\Specification;


class ValidatorTest_Class
{
    public $firstName = 'Bernhard';

    public $reference;

    public function getLastName()
    {
        return 'Schussek';
    }

    public function isAustralian()
    {
        return false;
    }
}


class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function testValidatePropertyConstraint()
    {
        /*
        $subject = new ValidatorTest_Class();
        $subjectClass = get_class($subject);

        $constraint = new Constraint();
        $property = new PropertySpecification($subjectClass, 'firstName', array($constraint));
        $class = new ClassSpecification($subjectClass, array($property));
        $specification = new Specification(array($class));
        $metadata = new Metadata($specification);

        $validatorMock = $this->getMock('Symfony\Components\Validator\ConstraintValidatorInterface');
        $validatorMock->expects($this->once())
                                    ->method('isValid')
                                    ->with($this->equalTo('Bernhard'), $this->equalTo($constraint))
                                    ->will($this->returnValue(false));
        $validatorMock->expects($this->atLeastOnce())
                                    ->method('getMessageTemplate')
                                    ->will($this->returnValue('message'));
        $validatorMock->expects($this->atLeastOnce())
                                    ->method('getMessageParameters')
                                    ->will($this->returnValue(array('param' => 'value')));

        $factoryMock = $this->getMock('Symfony\Components\Validator\ConstraintValidatorFactoryInterface');
        $factoryMock->expects($this->once())
                                ->method('getInstance')
                                ->with($this->equalTo($constraint->validatedBy()))
                                ->will($this->returnValue($validatorMock));

        $validator = new Validator($metadata, $factoryMock);

        $builder = new PropertyPathBuilder();
        $expected = new ConstraintViolationList();
        $expected->add(new ConstraintViolation(
            'message',
            array('param' => 'value'),
            $subjectClass,
            $builder->atProperty('firstName')->getPropertyPath(),
            'Bernhard'
        ));

        $this->assertEquals($expected, $validator->validateProperty($subject, 'firstName'));
        */
    }
}