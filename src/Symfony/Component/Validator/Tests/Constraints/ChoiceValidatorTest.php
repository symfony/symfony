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

use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\ChoiceValidator;

function choice_callback()
{
    return array('foo', 'bar');
}

class ChoiceValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new ChoiceValidator();
    }

    public static function staticCallback()
    {
        return array('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectArrayIfMultipleIsTrue()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multiple' => true,
        ));

        $this->validator->validate('asdf', $constraint);
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Choice(array('choices' => array('foo', 'bar'))));

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testChoicesOrCallbackExpected()
    {
        $this->validator->validate('foobar', new Choice());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testValidCallbackExpected()
    {
        $this->validator->validate('foobar', new Choice(array('callback' => 'abcd')));
    }

    public function testValidChoiceArray()
    {
        $constraint = new Choice(array('choices' => array('foo', 'bar')));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackFunction()
    {
        $constraint = new Choice(array('callback' => __NAMESPACE__.'\choice_callback'));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackClosure()
    {
        $constraint = new Choice(array('callback' => function () {
            return array('foo', 'bar');
        }, ));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackStaticMethod()
    {
        $constraint = new Choice(array('callback' => array(__CLASS__, 'staticCallback')));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackContextMethod()
    {
        // search $this for "staticCallback"
        $this->setObject($this);

        $constraint = new Choice(array('callback' => 'staticCallback'));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'baz'),
            'multiple' => true,
        ));

        $this->validator->validate(array('baz', 'bar'), $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidChoice()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'message' => 'myMessage',
        ));

        $this->validator->validate('baz', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->assertRaised();
    }

    public function testInvalidChoiceMultiple()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multipleMessage' => 'myMessage',
            'multiple' => true,
        ));

        $this->validator->validate(array('foo', 'baz'), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->assertRaised();
    }

    public function testTooFewChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'min' => 2,
            'minMessage' => 'myMessage',
        ));

        $value = array('foo');

        $this->setValue($value);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->assertRaised();
    }

    public function testTooManyChoices()
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'moo', 'maa'),
            'multiple' => true,
            'max' => 2,
            'maxMessage' => 'myMessage',
        ));

        $value = array('foo', 'bar', 'moo');

        $this->setValue($value);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->assertRaised();
    }

    public function testNonStrict()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'strict' => false,
        ));

        $this->validator->validate('2', $constraint);
        $this->validator->validate(2, $constraint);

        $this->assertNoViolation();
    }

    public function testStrictAllowsExactValue()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'strict' => true,
        ));

        $this->validator->validate(2, $constraint);

        $this->assertNoViolation();
    }

    public function testStrictDisallowsDifferentType()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'strict' => true,
            'message' => 'myMessage',
        ));

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"2"')
            ->assertRaised();
    }

    public function testNonStrictWithMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => false,
        ));

        $this->validator->validate(array('2', 3), $constraint);

        $this->assertNoViolation();
    }

    public function testStrictWithMultipleChoices()
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'strict' => true,
            'multipleMessage' => 'myMessage',
        ));

        $this->validator->validate(array(2, '3'), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"3"')
            ->assertRaised();
    }
}
