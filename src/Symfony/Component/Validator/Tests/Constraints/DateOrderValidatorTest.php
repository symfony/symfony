<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\DateOrder;
use Symfony\Component\Validator\Constraints\DateOrderValidator;

class DateOrderValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new DateOrderValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testProperlyOrderedDatesIsValid() {

        $this->context->expects($this->never())
            ->method('addViolation');

        $constraint = new DateOrder(array(
            'message' => 'myMessage',
            'earlierDatePropertyPath' => 'earlierDate',
            'laterDatePropertyPath' => 'laterDate',
        ));

        $object = (object) array(
            'earlierDate' => new \DateTime(),
            'laterDate' => new \DateTime("+1day"),
        );

        $this->validator->validate($object, $constraint);
    }



    public function testNotProperlyOrderedDatesIsInvalid() {

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage');

        $constraint = new DateOrder(array(
            'message' => 'myMessage',
            'earlierDatePropertyPath' => 'earlierDate',
            'laterDatePropertyPath' => 'laterDate',
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
            ->with('myMessage');

        $constraint = new DateOrder(array(
            'message' => 'myMessage',
            'earlierDatePropertyPath' => 'earlierDate',
            'laterDatePropertyPath' => 'laterDate',
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

        $constraint = new DateOrder(array(
            'message' => 'myMessage',
            'allowEqualDates' => true,
            'earlierDatePropertyPath' => 'earlierDate',
            'laterDatePropertyPath' => 'laterDate',
        ));

        $sameDate = new \DateTime();
        $object = (object) array(
            'earlierDate' => $sameDate,
            'laterDate' => clone $sameDate,
        );

        $this->validator->validate($object, $constraint);
    }


}
