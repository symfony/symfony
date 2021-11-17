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

use Symfony\Component\Validator\Constraints\Conditionally;
use Symfony\Component\Validator\Constraints\ConditionallyValidator;
use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;

class ConditionallyValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConditionallyValidator
    {
        return new ConditionallyValidator();
    }

    public function testWalkThroughConstraintsWhenConditionIsNotFulfilledWithValidValue()
    {
        $constraints = [
            new Type('number'),
            new Range(['min' => 4]),
        ];

        $value = 6;

        $this->expectNoValidate();

        $this->validator->validate($value, new Conditionally([
            'condition' => 'false',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testWalkThroughConstraintsWhenConditionIsFulfilledWithValidValue()
    {
        $constraints = [
            new Type('number'),
            new Range(['min' => 4]),
        ];

        $value = 6;

        $this->expectValidateValue(0, $value, [$constraints[0]]);
        $this->expectValidateValue(1, $value, [$constraints[1]]);

        $this->validator->validate($value, new Conditionally([
            'condition' => 'true',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testWalkThroughConstraintsWhenConditionIsNotFulfilledWithInvalidValue()
    {
        $constraints = [
            new Type('int'),
            new Regex(['pattern' => '[a-z]']),
            new NotEqualTo('Foo'),
        ];

        $value = 'Foo';

        $this->expectNoValidate();

        $this->validator->validate($value, new Conditionally([
            'condition' => 'false',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testWalkThroughConstraintsWhenConditionIsFulfilledWithInvalidValue()
    {
        $constraints = [
            new Type('int'),
            new Regex(['pattern' => '[a-z]']),
            new NotEqualTo('Foo'),
        ];

        $value = 'Foo';

        $this->expectFailingValueValidation(0, $value, [$constraints[0]], null, new ConstraintViolation('type error', null, [], null, '', null, null, 'type'));
        $this->expectFailingValueValidation(1, $value, [$constraints[1]], null, new ConstraintViolation('regex error', null, [], null, '', null, null, 'regex'));
        $this->expectFailingValueValidation(2, $value, [$constraints[2]], null, new ConstraintViolation('notequal error', null, [], null, '', null, null, 'notequal'));

        $this->validator->validate($value, new Conditionally([
            'condition' => 'true',
            'constraints' => $constraints,
        ]));

        $this->assertCount(3, $this->context->getViolations());
    }

    public function testSimpleFalsyConditionEvaluation()
    {
        $this->expectNoValidate();

        $this->validator->validate(1, new Conditionally([
            'condition' => 'false',
            'constraints' => [new NotNull()],
        ]));

        $this->assertNoViolation();
    }

    public function testSimpleTruthyConditionEvaluation()
    {
        $value = 1;
        $constraints = [new NotNull()];

        $this->expectFailingValueValidation(0, $value, [$constraints[0]], null, new ConstraintViolation('notnull error', null, [], null, '', null, null, 'notnull'));

        $this->validator->validate($value, new Conditionally([
            'condition' => 'true',
            'constraints' => $constraints,
        ]));

        $this->assertCount(1, $this->context->getViolations());
    }

    public function testFalsyConditionEvaluationUsingValue()
    {
        $constraints = [new NotNull()];

        $this->expectNoValidate();

        $this->validator->validate(1, new Conditionally([
            'condition' => 'value == 0',
            'constraints' => $constraints,
        ]));

        $this->assertNoViolation();
    }

    public function testTruthyConditionEvaluationUsingValue()
    {
        $value = 1;
        $constraints = [new NotNull()];

        $this->expectFailingValueValidation(0, $value, [$constraints[0]], null, new ConstraintViolation('notnull error', null, [], null, '', null, null, 'notnull'));

        $this->validator->validate($value, new Conditionally([
            'condition' => 'value == 1',
            'constraints' => $constraints,
        ]));

        $this->assertCount(1, $this->context->getViolations());
    }

    public function testSimpleFalsyConditionEvaluationUsingObject()
    {
        $object = new Entity();
        $object->data = 1;

        $this->setObject($object);

        $this->expectNoValidate();

        $this->validator->validate($object, new Conditionally([
            'condition' => 'this.data == 0',
            'constraints' => [new NotNull()],
        ]));

        $this->assertNoViolation();
    }

    public function testSimpleTruthyConditionEvaluationUsingObject()
    {
        $object = new Entity();
        $object->data = 1;

        $constraints = [new NotNull()];

        $this->setObject($object);

        $this->expectFailingValueValidation(0, $object, [$constraints[0]], null, new ConstraintViolation('notnull error', null, [], null, '', null, null, 'notnull'));

        $this->validator->validate($object, new Conditionally([
            'condition' => 'this.data == 1',
            'constraints' => $constraints,
        ]));

        $this->assertCount(1, $this->context->getViolations());
    }
}
