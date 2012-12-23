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

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\OneOf;
use Symfony\Component\Validator\Constraints\OneOfValidator;

function oneof_callback()
{
    return array('foo', 'bar');
}

class OneOfValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    public static function staticCallback()
    {
        return array('foo', 'bar');
    }

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new OneOfValidator();
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getCurrentClass')
            ->will($this->returnValue(__CLASS__));
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectArrayIfMultipleIsTrue()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar'),
            'multiple' => true,
        ));

        $this->validator->validate('asdf', $constraint);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new OneOf(array('choices' => array('foo', 'bar'))));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testChoicesOrCallbackExpected()
    {
        $this->validator->validate('foobar', new OneOf());
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testValidCallbackExpected()
    {
        $this->validator->validate('foobar', new OneOf(array('callback' => 'abcd')));
    }

    public function testValidOneOfChoicesArray()
    {
        $constraint = new OneOf(array('choices' => array('foo', 'bar')));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('bar', $constraint);
    }

    public function testValidOneOfCallbackFunction()
    {
        $constraint = new OneOf(array('callback' => __NAMESPACE__.'\oneof_callback'));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('bar', $constraint);
    }

    public function testValidOneOfCallbackClosure()
    {
        $constraint = new OneOf(array('callback' => function() {
            return array('foo', 'bar');
        }));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('bar', $constraint);
    }

    public function testValidOneOfCallbackStaticMethod()
    {
        $constraint = new OneOf(array('callback' => array(__CLASS__, 'staticCallback')));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('bar', $constraint);
    }

    public function testValidOneOfCallbackContextMethod()
    {
        $constraint = new OneOf(array('callback' => 'staticCallback'));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('bar', $constraint);
    }

    public function testMultipleOneOfChoices()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar', 'baz'),
            'multiple' => true,
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(array('baz', 'bar'), $constraint);
    }

    public function testInvalidOneOf()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar'),
            'message' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => 'baz',
            ), null, null);

        $this->validator->validate('baz', $constraint);
    }

    public function testInvalidOneOfMultiple()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar'),
            'multipleMessage' => 'myMessage',
            'multiple' => true,
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => 'baz',
            ));

        $this->validator->validate(array('foo', 'baz'), $constraint);
    }

    public function testTooFewOneOfChoices()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'min' => 2,
            'minMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}' => 2,
            ), null, 2);

        $this->validator->validate(array('foo'), $constraint);
    }

    public function testTooManyOneOfChoices()
    {
        $constraint = new OneOf(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'max' => 2,
            'maxMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ limit }}' => 2,
            ), null, 2);

        $this->validator->validate(array('foo', 'bar', 'moo'), $constraint);
    }

    public function testNonStrict()
    {
        $constraint = new OneOf(array(
            'choices' => array(1, 2),
            'strict' => false,
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('2', $constraint);
        $this->validator->validate(2, $constraint);
    }

    public function testStrictAllowsExactValue()
    {
        $constraint = new OneOf(array(
            'choices' => array(1, 2),
            'strict' => true,
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(2, $constraint);
    }

    public function testStrictDisallowsDifferentType()
    {
        $constraint = new OneOf(array(
            'choices' => array(1, 2),
            'strict' => true,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => '2',
            ));

        $this->validator->validate('2', $constraint);
    }

    public function testNonStrictOneOfWithMultipleChoices()
    {
        $constraint = new OneOf(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => false
        ));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(array('2', 3), $constraint);
    }

    public function testStrictOneOfWithMultipleChoices()
    {
        $constraint = new OneOf(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => true,
            'multipleMessage' => 'myMessage',
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => '3',
            ));

        $this->validator->validate(array(2, '3'), $constraint);
    }
}
