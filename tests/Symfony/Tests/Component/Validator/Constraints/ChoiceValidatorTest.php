<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\ChoiceValidator;

function choice_callback()
{
    return array('foo', 'bar');
}

class ChoiceValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    public static function staticCallback()
    {
        return array('foo', 'bar');
    }

    protected function setUp()
    {
        $walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $factory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $context = new ExecutionContext('root', $walker, $factory);
        $context->setCurrentClass(__CLASS__);
        $this->validator = new ChoiceValidator();
        $this->validator->initialize($context);
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testExpectArrayIfMultipleIsTrue()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multiple' => true,
        ));

        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid('asdf', $constraint);
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Choice(array('choices' => array('foo', 'bar')))));
    }

    public function testChoicesOrCallbackExpected()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid('foobar', new Choice());
    }

    public function testValidCallbackExpected()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->validator->isValid('foobar', new Choice(array('callback' => 'abcd')));
    }

    public function testValidChoiceArray()
    {
        $constraint = new Choice(array('choices' => array('foo', 'bar')));

        $this->assertTrue($this->validator->isValid('bar', $constraint));
    }

    public function testValidChoiceCallbackFunction()
    {
        $constraint = new Choice(array('callback' => __NAMESPACE__.'\choice_callback'));

        $this->assertTrue($this->validator->isValid('bar', $constraint));
    }

    public function testValidChoiceCallbackClosure()
    {
        $constraint = new Choice(array('callback' => function() {
            return array('foo', 'bar');
        }));

        $this->assertTrue($this->validator->isValid('bar', $constraint));
    }

    public function testValidChoiceCallbackStaticMethod()
    {
        $constraint = new Choice(array('callback' => array(__CLASS__, 'staticCallback')));

        $this->assertTrue($this->validator->isValid('bar', $constraint));
    }

    public function testValidChoiceCallbackContextMethod()
    {
        $constraint = new Choice(array('callback' => 'staticCallback'));

        $this->assertTrue($this->validator->isValid('bar', $constraint));
    }

    public function testMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'baz'),
            'multiple' => true,
        ));

        $this->assertTrue($this->validator->isValid(array('baz', 'bar'), $constraint));
    }

    public function testInvalidChoice()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'message' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid('baz', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'baz',
        ));
    }

    public function testInvalidChoiceMultiple()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multipleMessage' => 'myMessage',
            'multiple' => true,
        ));

        $this->assertFalse($this->validator->isValid(array('foo', 'baz'), $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'baz',
        ));
    }

    public function testTooFewChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'min' => 2,
            'minMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid(array('foo'), $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ limit }}' => 2,
        ));
    }

    public function testTooManyChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'max' => 2,
            'maxMessage' => 'myMessage',
        ));

        $this->assertFalse($this->validator->isValid(array('foo', 'bar', 'moo'), $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ limit }}' => 2,
        ));
    }

    public function testStrictIsFalse()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'strict' => false,
        ));

        $this->assertTrue($this->validator->isValid('2', $constraint));
        $this->assertTrue($this->validator->isValid(2, $constraint));
    }

    public function testStrictIsTrue()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'strict' => true,
        ));

        $this->assertTrue($this->validator->isValid(2, $constraint));
        $this->assertFalse($this->validator->isValid('2', $constraint));
    }

    public function testStrictIsFalseWhenMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => false
        ));

        $this->assertTrue($this->validator->isValid(array('2', 3), $constraint));
    }

    public function testStrictIsTrueWhenMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => true
        ));

        $this->assertFalse($this->validator->isValid(array(2, '3'), $constraint));
    }
}
