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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

function choice_callback()
{
    return ['foo', 'bar'];
}

class ChoiceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ChoiceValidator
    {
        return new ChoiceValidator();
    }

    public static function staticCallback()
    {
        return ['foo', 'bar'];
    }

    public function objectMethodCallback()
    {
        return ['foo', 'bar'];
    }

    public function testExpectArrayIfMultipleIsTrue()
    {
        $this->expectException(UnexpectedValueException::class);
        $constraint = new Choice([
            'choices' => ['foo', 'bar'],
            'multiple' => true,
        ]);

        $this->validator->validate('asdf', $constraint);
    }

    public function testNullIsValid()
    {
        $this->validator->validate(
            null,
            new Choice([
                'choices' => ['foo', 'bar'],
            ])
        );

        $this->assertNoViolation();
    }

    public function testChoicesOrCallbackExpected()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foobar', new Choice());
    }

    public function testValidCallbackExpected()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('foobar', new Choice(['callback' => 'abcd']));
    }

    /**
     * @dataProvider provideConstraintsWithChoicesArray
     */
    public function testValidChoiceArray(Choice $constraint)
    {
        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public static function provideConstraintsWithChoicesArray(): iterable
    {
        yield 'Doctrine style' => [new Choice(['choices' => ['foo', 'bar']])];
        yield 'Doctrine default option' => [new Choice(['value' => ['foo', 'bar']])];
        yield 'first argument' => [new Choice(['foo', 'bar'])];
        yield 'named arguments' => [new Choice(choices: ['foo', 'bar'])];
    }

    /**
     * @dataProvider provideConstraintsWithCallbackFunction
     */
    public function testValidChoiceCallbackFunction(Choice $constraint)
    {
        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public static function provideConstraintsWithCallbackFunction(): iterable
    {
        yield 'doctrine style, namespaced function' => [new Choice(['callback' => __NAMESPACE__.'\choice_callback'])];
        yield 'doctrine style, closure' => [new Choice([
            'callback' => fn () => ['foo', 'bar'],
        ])];
        yield 'doctrine style, static method' => [new Choice(['callback' => [__CLASS__, 'staticCallback']])];
        yield 'named arguments, namespaced function' => [new Choice(callback: __NAMESPACE__.'\choice_callback')];
        yield 'named arguments, closure' => [new Choice(callback: fn () => ['foo', 'bar'])];
        yield 'named arguments, static method' => [new Choice(callback: [__CLASS__, 'staticCallback'])];
    }

    public function testValidChoiceCallbackContextMethod()
    {
        // search $this for "staticCallback"
        $this->setObject($this);

        $constraint = new Choice(['callback' => 'staticCallback']);

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    public function testValidChoiceCallbackContextObjectMethod()
    {
        // search $this for "objectMethodCallback"
        $this->setObject($this);

        $constraint = new Choice(['callback' => 'objectMethodCallback']);

        $this->validator->validate('bar', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideConstraintsWithMultipleTrue
     */
    public function testMultipleChoices(Choice $constraint)
    {
        $this->validator->validate(['baz', 'bar'], $constraint);

        $this->assertNoViolation();
    }

    public static function provideConstraintsWithMultipleTrue(): iterable
    {
        yield 'Doctrine style' => [new Choice([
            'choices' => ['foo', 'bar', 'baz'],
            'multiple' => true,
        ])];
        yield 'named arguments' => [new Choice(
            choices: ['foo', 'bar', 'baz'],
            multiple: true,
        )];
    }

    /**
     * @dataProvider provideConstraintsWithMessage
     */
    public function testInvalidChoice(Choice $constraint)
    {
        $this->validator->validate('baz', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setParameter('{{ choices }}', '"foo", "bar"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithMessage(): iterable
    {
        yield 'Doctrine style' => [new Choice(['choices' => ['foo', 'bar'], 'message' => 'myMessage'])];
        yield 'named arguments' => [new Choice(choices: ['foo', 'bar'], message: 'myMessage')];
    }

    public function testInvalidChoiceEmptyChoices()
    {
        $constraint = new Choice([
            // May happen when the choices are provided dynamically, e.g. from
            // the DB or the model
            'choices' => [],
            'message' => 'myMessage',
        ]);

        $this->validator->validate('baz', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setParameter('{{ choices }}', '')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider provideConstraintsWithMultipleMessage
     */
    public function testInvalidChoiceMultiple(Choice $constraint)
    {
        $this->validator->validate(['foo', 'baz'], $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"baz"')
            ->setParameter('{{ choices }}', '"foo", "bar"')
            ->setInvalidValue('baz')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithMultipleMessage(): iterable
    {
        yield 'Doctrine style' => [new Choice([
            'choices' => ['foo', 'bar'],
            'multipleMessage' => 'myMessage',
            'multiple' => true,
        ])];
        yield 'named arguments' => [new Choice(
            choices: ['foo', 'bar'],
            multipleMessage: 'myMessage',
            multiple: true,
        )];
    }

    /**
     * @dataProvider provideConstraintsWithMin
     */
    public function testTooFewChoices(Choice $constraint)
    {
        $value = ['foo'];

        $this->setValue($value);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->setCode(Choice::TOO_FEW_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithMin(): iterable
    {
        yield 'Doctrine style' => [new Choice([
            'choices' => ['foo', 'bar', 'moo', 'maa'],
            'multiple' => true,
            'min' => 2,
            'minMessage' => 'myMessage',
        ])];
        yield 'named arguments' => [new Choice(
            choices: ['foo', 'bar', 'moo', 'maa'],
            multiple: true,
            min: 2,
            minMessage: 'myMessage',
        )];
    }

    /**
     * @dataProvider provideConstraintsWithMax
     */
    public function testTooManyChoices(Choice $constraint)
    {
        $value = ['foo', 'bar', 'moo'];

        $this->setValue($value);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->setCode(Choice::TOO_MANY_ERROR)
            ->assertRaised();
    }

    public static function provideConstraintsWithMax(): iterable
    {
        yield 'Doctrine style' => [new Choice([
            'choices' => ['foo', 'bar', 'moo', 'maa'],
            'multiple' => true,
            'max' => 2,
            'maxMessage' => 'myMessage',
        ])];
        yield 'named arguments' => [new Choice(
            choices: ['foo', 'bar', 'moo', 'maa'],
            multiple: true,
            max: 2,
            maxMessage: 'myMessage',
        )];
    }

    public function testStrictAllowsExactValue()
    {
        $constraint = new Choice([
            'choices' => [1, 2],
        ]);

        $this->validator->validate(2, $constraint);

        $this->assertNoViolation();
    }

    public function testStrictDisallowsDifferentType()
    {
        $constraint = new Choice([
            'choices' => [1, 2],
            'message' => 'myMessage',
        ]);

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"2"')
            ->setParameter('{{ choices }}', '1, 2')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testStrictWithMultipleChoices()
    {
        $constraint = new Choice([
            'choices' => [1, 2, 3],
            'multiple' => true,
            'multipleMessage' => 'myMessage',
        ]);

        $this->validator->validate([2, '3'], $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"3"')
            ->setParameter('{{ choices }}', '1, 2, 3')
            ->setInvalidValue('3')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testMatchFalse()
    {
        $this->validator->validate('foo', new Choice([
            'choices' => ['foo', 'bar'],
            'match' => false,
        ]));

        $this->buildViolation('The value you selected is not a valid choice.')
            ->setParameter('{{ value }}', '"foo"')
            ->setParameter('{{ choices }}', '"foo", "bar"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->assertRaised();
    }

    public function testMatchFalseWithMultiple()
    {
        $this->validator->validate(['ccc', 'bar', 'zzz'], new Choice([
            'choices' => ['foo', 'bar'],
            'multiple' => true,
            'match' => false,
        ]));

        $this->buildViolation('One or more of the given values is invalid.')
            ->setParameter('{{ value }}', '"bar"')
            ->setParameter('{{ choices }}', '"foo", "bar"')
            ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
            ->setInvalidValue('bar')
            ->assertRaised();
    }
}
