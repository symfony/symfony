<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\PropertiesInequality;
use Symfony\Component\Validator\Constraints\PropertiesInequalityValidator;

class PropertiesInequalityValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new PropertiesInequalityValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

	// Tests for numeric values
	
	public function testProperlyOrderedNumbersIsValid() {

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new PropertiesInequality(array(
            'message' => 'myMessage',
            'lessValuePropertyPath' => 'smallNumber',
            'greaterValuePropertyPath' => 'bigNumber',
        ));

        $object = (object) array(
            'smallNumber' => 10,
            'bigNumber' => 10000.0,
        );

        $this->validator->validate($object, $constraint);
    }
    
	public function testUnproperlyOrderedNumbersIsInvalid() {

        $this->context->expects($this->once())
            ->method('addViolation')
	        ->with('myMessage', array(
	            '{{ lessWord }}' => 'less',
	            '{{ lessProperty }}' => 'smallNumber',
	            '{{ greaterProperty }}' => 'bigNumber',
	        ));


        $constraint = new PropertiesInequality(array(
	        'message' => 'myMessage',
			'lessWord' => 'less',
            'lessValuePropertyPath' => 'smallNumber',
            'greaterValuePropertyPath' => 'bigNumber',
        ));

        $object = (object) array(
            'smallNumber' => 300.0,
            'bigNumber' => -20,
        );

        $this->validator->validate($object, $constraint);
    }

    public function testEqualNumbersIsInvalidWhenNotAllowed() {

        $this->context->expects($this->once())
            ->method('addViolation')
	        ->with('myMessage', array(
	            '{{ lessWord }}' => 'less',
	            '{{ lessProperty }}' => 'smallNumber',
	            '{{ greaterProperty }}' => 'bigNumber',
	        ));


        $constraint = new PropertiesInequality(array(
	        'message' => 'myMessage',
			'lessWord' => 'less',
            'lessValuePropertyPath' => 'smallNumber',
            'greaterValuePropertyPath' => 'bigNumber',
        ));

        $object = (object) array(
            'smallNumber' => 50.0,
            'bigNumber' => 50,
        );

        $this->validator->validate($object, $constraint);
    }


	public function testEqualNumbersIsValidWhenAllowed() {

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new PropertiesInequality(array(
            'message' => 'myMessage',
            'strict' => false,
            'lessValuePropertyPath' => 'smallNumber',
            'greaterValuePropertyPath' => 'bigNumber',
        ));

        $object = (object) array(
            'smallNumber' => 50,
            'bigNumber' => 50.0,
        );

        $this->validator->validate($object, $constraint);
    }


    
	// Test for dates

    public function testProperlyOrderedDatesIsValid() {

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new PropertiesInequality(array(
            'message' => 'myMessage',
            'lessValuePropertyPath' => 'earlierDate',
            'greaterValuePropertyPath' => 'laterDate',
        ));

        $object = (object) array(
            'earlierDate' => new \DateTime(),
            'laterDate' => new \DateTime("+1day"),
        );

        $this->validator->validate($object, $constraint);
    }



    public function testUnproperlyOrderedDatesIsInvalid() {

        $this->context->expects($this->once())
            ->method('addViolation')
	        ->with('myMessage', array(
	            '{{ lessWord }}' => 'earlier',
	            '{{ lessProperty }}' => 'earlierDate',
	            '{{ greaterProperty }}' => 'laterDate',
	        ));


        $constraint = new PropertiesInequality(array(
	        'message' => 'myMessage',
			'lessWord' => 'earlier',
            'lessValuePropertyPath' => 'earlierDate',
            'greaterValuePropertyPath' => 'laterDate',
        ));

        $object = (object) array(
            'earlierDate' => new \DateTime("+1day"),
            'laterDate' => new \DateTime(),
        );

        $this->validator->validate($object, $constraint);
    }


    public function testEqualDatesIsInvalidWhenNotAllowed() {

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ lessWord }}' => 'earlier',
                '{{ lessProperty }}' => 'earlierDate',
                '{{ greaterProperty }}' => 'laterDate',
            ));

        $constraint = new PropertiesInequality(array(
	        'message' => 'myMessage',
			'lessWord' => 'earlier',
            'lessValuePropertyPath' => 'earlierDate',
            'greaterValuePropertyPath' => 'laterDate',
        ));

        $sameDate = new \DateTime();
        $object = (object) array(
            'earlierDate' => $sameDate,
            'laterDate' => clone $sameDate,
        );

        $this->validator->validate($object, $constraint);
    }

    public function testEqualDatesIsValidWhenAllowed() {

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new PropertiesInequality(array(
	        'message' => 'myMessage',
            'strict' => false,
            'lessValuePropertyPath' => 'earlierDate',
            'greaterValuePropertyPath' => 'laterDate',
        ));

        $sameDate = new \DateTime();
        $object = (object) array(
            'earlierDate' => $sameDate,
            'laterDate' => clone $sameDate,
        );

        $this->validator->validate($object, $constraint);
    }


}
