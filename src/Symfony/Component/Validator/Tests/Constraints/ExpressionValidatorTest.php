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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ToString;

class ExpressionValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ExpressionValidator
    {
        return new ExpressionValidator();
    }

    public function testExpressionIsEvaluatedWithNullValue()
    {
        $constraint = new Expression(
            expression: 'false',
            message: 'myMessage',
        );

        $this->validator->validate(null, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'null')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionIsEvaluatedWithEmptyStringValue()
    {
        $constraint = new Expression(
            expression: 'false',
            message: 'myMessage',
        );

        $this->validator->validate('', $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '""')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevel()
    {
        $constraint = new Expression('this.data == 1');

        $object = new Entity();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevel()
    {
        $constraint = new Expression(
            expression: 'this.data == 1',
            message: 'myMessage',
        );

        $object = new Entity();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'object')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtObjectLevelWithToString()
    {
        $constraint = new Expression('this.data == 1');

        $object = new ToString();
        $object->data = '1';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtObjectLevelWithToString()
    {
        $constraint = new Expression(
            expression: 'this.data == 1',
            message: 'myMessage',
        );

        $object = new ToString();
        $object->data = '2';

        $this->setObject($object);

        $this->validator->validate($object, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', 'toString')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtPropertyLevel()
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtPropertyLevel()
    {
        $constraint = new Expression(
            expression: 'value == this.data',
            message: 'myMessage',
        );

        $object = new Entity();
        $object->data = '1';

        $this->setRoot($object);
        $this->setPropertyPath('data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testSucceedingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression('value == this.data');

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    public function testFailingExpressionAtNestedPropertyLevel()
    {
        $constraint = new Expression(
            expression: 'value == this.data',
            message: 'myMessage',
        );

        $object = new Entity();
        $object->data = '1';

        $root = new Entity();
        $root->reference = $object;

        $this->setRoot($root);
        $this->setPropertyPath('reference.data');
        $this->setProperty($object, 'data');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('reference.data')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testSucceedingExpressionAtPropertyLevelWithoutRoot()
    {
        $constraint = new Expression('value == "1"');

        $this->setRoot('1');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('1', $constraint);

        $this->assertNoViolation();
    }

    /**
     * When validatePropertyValue() is called with a class name
     * https://github.com/symfony/symfony/pull/11498.
     */
    public function testFailingExpressionAtPropertyLevelWithoutRoot()
    {
        $constraint = new Expression(
            expression: 'value == "1"',
            message: 'myMessage',
        );

        $this->setRoot('2');
        $this->setPropertyPath('');
        $this->setProperty(null, 'property');

        $this->validator->validate('2', $constraint);

        $this->buildViolation('myMessage')
            ->atPath('')
            ->setParameter('{{ value }}', '"2"')
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testExpressionLanguageUsage()
    {
        $constraint = new Expression(expression: 'false');

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);

        $used = false;

        $expressionLanguage->method('evaluate')
            ->willReturnCallback(function () use (&$used) {
                $used = true;

                return true;
            });

        $validator = new ExpressionValidator($expressionLanguage);
        $validator->initialize($this->createContext());
        $validator->validate(null, $constraint);

        $this->assertTrue($used, 'Failed asserting that custom ExpressionLanguage instance is used.');
    }

    public function testPassingCustomValues()
    {
        $constraint = new Expression(
            expression: 'value + custom == 2',
            values: [
                'custom' => 1,
            ],
        );

        $this->validator->validate(1, $constraint);

        $this->assertNoViolation();
    }

    public function testViolationOnPass()
    {
        $constraint = new Expression(
            expression: 'value + custom != 2',
            values: [
                'custom' => 1,
            ],
            negate: false,
        );

        $this->validator->validate(2, $constraint);

        $this->buildViolation('This value is not valid.')
            ->atPath('property.path')
            ->setParameter('{{ value }}', 2)
            ->setCode(Expression::EXPRESSION_FAILED_ERROR)
            ->assertRaised();
    }

    public function testIsValidExpression()
    {
        $constraints = [new NotNull(), new Range(min: 2)];

        $constraint = new Expression(
            expression: 'is_valid(this.data, a)',
            values: ['a' => $constraints],
        );

        $object = new Entity();
        $object->data = 7;

        $this->setObject($object);

        $this->expectValidateValue(0, $object->data, $constraints);

        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    public function testIsValidExpressionInvalid()
    {
        $constraints = [new Range(min: 2, max: 5)];

        $constraint = new Expression(
            expression: 'is_valid(this.data, a)',
            values: ['a' => $constraints],
        );

        $object = new Entity();
        $object->data = 7;

        $this->setObject($object);

        $this->expectFailingValueValidation(
            0,
            7,
            $constraints,
            null,
            new ConstraintViolation('error_range', '', [], '', '', 7, null, 'range')
        );

        $this->validator->validate($object, $constraint);

        $this->assertCount(2, $this->context->getViolations());
    }

    /**
     * @dataProvider provideCompileIsValid
     */
    public function testCompileIsValid(string $expression, array $names, string $expected)
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ExpressionLanguageProvider());

        $result = $expressionLanguage->compile($expression, $names);

        $this->assertSame($expected, $result);
    }

    public static function provideCompileIsValid(): array
    {
        return [
            [
                'is_valid("foo", constraints)',
                ['constraints'],
                '0 === $context->getValidator()->inContext($context)->validate("foo", $constraints)->getViolations()->count()',
            ],
            [
                'is_valid(this.data, constraints, groups)',
                ['this', 'constraints', 'groups'],
                '0 === $context->getValidator()->inContext($context)->validate($this->data, $constraints, $groups)->getViolations()->count()',
            ],
        ];
    }
}
