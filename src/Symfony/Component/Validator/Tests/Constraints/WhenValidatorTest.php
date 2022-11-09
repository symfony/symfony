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

use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NegativeOrZero;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\When;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class WhenValidatorTest extends ConstraintValidatorTestCase
{
    public function testConstraintsAreExecuted()
    {
        $constraints = [
            new NotNull(),
            new NotBlank(),
        ];

        $this->expectValidateValue(0, 'Foo', $constraints);

        $this->validator->validate('Foo', new When([
            'expression' => 'true',
            'constraints' => $constraints,
        ]));
    }

    public function testConstraintsAreExecutedWithNull()
    {
        $constraints = [
            new NotNull(),
        ];

        $this->expectValidateValue(0, null, $constraints);

        $this->validator->validate(null, new When([
            'expression' => 'true',
            'constraints' => $constraints,
        ]));
    }

    public function testConstraintsAreExecutedWithObject()
    {
        $number = new \stdClass();
        $number->type = 'positive';
        $number->value = 1;

        $this->setObject($number);
        $this->setPropertyPath('value');

        $constraints = [
            new PositiveOrZero(),
        ];

        $this->expectValidateValue(0, $number->value, $constraints);

        $this->validator->validate($number->value, new When([
            'expression' => 'this.type === "positive"',
            'constraints' => $constraints,
        ]));
    }

    public function testConstraintsAreExecutedWithValue()
    {
        $constraints = [
            new Callback(),
        ];

        $this->expectValidateValue(0, 'foo', $constraints);

        $this->validator->validate('foo', new When([
            'expression' => 'value === "foo"',
            'constraints' => $constraints,
        ]));
    }

    public function testConstraintsAreExecutedWithExpressionValues()
    {
        $constraints = [
            new Callback(),
        ];

        $this->expectValidateValue(0, 'foo', $constraints);

        $this->validator->validate('foo', new When([
            'expression' => 'activated && value === compared_value',
            'constraints' => $constraints,
            'values' => [
                'activated' => true,
                'compared_value' => 'foo',
            ],
        ]));
    }

    public function testConstraintsNotExecuted()
    {
        $constraints = [
            new NotNull(),
            new NotBlank(),
        ];

        $this->expectNoValidate();

        $this->validator->validate('', new When([
            'expression' => 'false',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testConstraintsNotExecutedWithObject()
    {
        $number = new \stdClass();
        $number->type = 'positive';
        $number->value = 1;

        $this->setObject($number);
        $this->setPropertyPath('value');

        $constraints = [
            new NegativeOrZero(),
        ];

        $this->expectNoValidate();

        $this->validator->validate($number->value, new When([
            'expression' => 'this.type !== "positive"',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testConstraintsNotExecutedWithValue()
    {
        $constraints = [
            new Callback(),
        ];

        $this->expectNoValidate();

        $this->validator->validate('foo', new When([
            'expression' => 'value === null',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testConstraintsNotExecutedWithExpressionValues()
    {
        $constraints = [
            new Callback(),
        ];

        $this->expectNoValidate();

        $this->validator->validate('foo', new When([
            'expression' => 'activated && value === compared_value',
            'constraints' => $constraints,
            'values' => [
                'activated' => true,
                'compared_value' => 'bar',
            ],
        ]));

        $this->assertNoViolation();
    }

    public function testConstraintViolations()
    {
        $constraints = [
            new Blank([
                'message' => 'my_message',
            ]),
        ];
        $this->expectFailingValueValidation(
            0,
            'foo',
            $constraints,
            null,
            new ConstraintViolation(
                'my_message',
                'my_message',
                [
                    '{{ value }}' => 'foo',
                ],
                null,
                '',
                null,
                null,
                Blank::NOT_BLANK_ERROR
            ),
        );

        $this->validator->validate('foo', new When('true', $constraints));
    }

    protected function createValidator(): WhenValidator
    {
        return new WhenValidator();
    }
}
