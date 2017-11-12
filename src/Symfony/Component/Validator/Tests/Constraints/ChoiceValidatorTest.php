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
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

function choice_callback()
{
    return array('foo', 'bar');
}

class ChoiceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new ChoiceValidator();
    }

    public static function staticCallback()
    {
        return array('foo', 'bar');
    }

    public function objectMethodCallback()
    {
        return array('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectArrayIfMultipleIsTrue(): void
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multiple' => true,
        ));

        $this->validator->validate('asdf', $constraint);
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(
            null,
            new Choice(
                array(
                    'choices' => array('foo', 'bar'),
                )
            )
        );

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testChoicesOrCallbackExpected(): void
    {
        $this->validator->validate('foobar', new Choice());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testValidCallbackExpected(): void
    {
        $this->validator->validate('foobar', new Choice(array('callback' => 'abcd')));
    }

    public function testValidChoiceArray(): void
    {
        $constraint = new Choice(array('choices' => array('foo', 'bar')));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackFunction(): void
    {
        $constraint = new Choice(array('callback' => __NAMESPACE__.'\choice_callback'));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackClosure(): void
    {
        $constraint = new Choice(
            array(
                'callback' => function () {
                    return array('foo', 'bar');
                },
            )
        );

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackStaticMethod(): void
    {
        $constraint = new Choice(array('callback' => array(__CLASS__, 'staticCallback')));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackContextMethod(): void
    {
        // search $this for "staticCallback"
        $this->setObject($this);

        $constraint = new Choice(array('callback' => 'staticCallback'));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackContextObjectMethod(): void
    {
        // search $this for "objectMethodCallback"
        $this->setObject($this);

        $constraint = new Choice(array('callback' => 'objectMethodCallback'));

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testMultipleChoices(): void
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar', 'baz'),
            'multiple' => true,
        ));

        $this->validator->validate(array('baz', 'bar'), $constraint);

        $this->assertNoViolation();
    }

    public function testInvalidChoice(): void
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'message' => 'myMessage',
        ));

        $this->validator->validate('baz', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testInvalidChoiceEmptyChoices(): void
    {
        $constraint = new Choice(array(
            // May happen when the choices are provided dynamically, e.g. from
            // the DB or the model
            'choices' => array(),
            'message' => 'myMessage',
        ));

        $this->validator->validate('baz', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testInvalidChoiceMultiple(): void
    {
        $constraint = new Choice(array(
            'choices' => array('foo', 'bar'),
            'multipleMessage' => 'myMessage',
            'multiple' => true,
        ));

        $this->validator->validate(array('foo', 'baz'), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setInvalidValue('baz')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testTooFewChoices(): void
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
            ->setCode(Choice::TOO_FEW_ERROR)
            ->assertRaised();
    }

    public function testTooManyChoices(): void
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
            ->setCode(Choice::TOO_MANY_ERROR)
            ->assertRaised();
    }

    public function testStrictAllowsExactValue(): void
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
        ));

        $this->validator->validate(2, $constraint);

        $this->assertNoViolation();
    }

    public function testStrictDisallowsDifferentType(): void
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2),
            'message' => 'myMessage',
        ));

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testStrictWithMultipleChoices(): void
    {
        $constraint = new Choice(array(
            'choices' => array(1, 2, 3),
            'multiple' => true,
            'multipleMessage' => 'myMessage',
        ));

        $this->validator->validate(array(2, '3'), $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"3"')
            ->setInvalidValue('3')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }
}
