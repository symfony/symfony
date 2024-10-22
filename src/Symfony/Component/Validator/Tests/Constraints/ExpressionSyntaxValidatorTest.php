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
use Symfony\Component\Validator\Constraints\ExpressionSyntax;
use Symfony\Component\Validator\Constraints\ExpressionSyntaxValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

class ExpressionSyntaxValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ExpressionSyntaxValidator
    {
        return new ExpressionSyntaxValidator(new ExpressionLanguage());
    }

    public static function staticCallback()
    {
        return ['foo', 'bar'];
    }

    public function objectMethodCallback()
    {
        return ['foo', 'bar'];
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new ExpressionSyntax());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new ExpressionSyntax());

        $this->assertNoViolation();
    }

    public function testExpressionValid()
    {
        $this->validator->validate('1 + 1', new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->assertNoViolation();
    }

    public function testStringableExpressionValid()
    {
        $this->validator->validate(new StringableValue('1 + 1'), new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithoutNames()
    {
        $this->validator->validate('1 + 1', new ExpressionSyntax([
            'message' => 'myMessage',
        ], null, null, []));

        $this->assertNoViolation();
    }

    public function testExpressionWithAllowedVariableName()
    {
        $this->validator->validate('a + 1', new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariables' => ['a'],
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionIsNotValid()
    {
        $this->validator->validate('a + 1', new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ syntax_error }}', '"Variable "a" is not valid around position 1 for expression `a + 1`."')
            ->setInvalidValue('a + 1')
            ->setCode(ExpressionSyntax::EXPRESSION_SYNTAX_ERROR)
            ->assertRaised();
    }

    public function testStringableExpressionIsNotValid()
    {
        $this->validator->validate(new StringableValue('a + 1'), new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ syntax_error }}', '"Variable "a" is not valid around position 1 for expression `a + 1`."')
            ->setInvalidValue('a + 1')
            ->setCode(ExpressionSyntax::EXPRESSION_SYNTAX_ERROR)
            ->assertRaised();
    }

    public function testExpressionWithCallbackContextMethod()
    {
        // search $this for "staticCallback"
        $this->setObject($this);

        $this->validator->validate('foo + 1', new ExpressionSyntax([
            'allowedVariablesCallback' => 'staticCallback',
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithCallbackContextObjectMethod()
    {
        // search $this for "objectMethodCallback"
        $this->setObject($this);

        $this->validator->validate('bar + 1', new ExpressionSyntax([
            'allowedVariablesCallback' => 'objectMethodCallback',
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithAllowedVariableCallbackIsNotValid()
    {
        // search $this for "staticCallback"
        $this->setObject($this);

        $this->validator->validate('bor + 1', new ExpressionSyntax([
            'message' => 'myMessage',
            'allowedVariablesCallback' => 'staticCallback',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ syntax_error }}', '"Variable "bor" is not valid around position 1 for expression `bor + 1`. Did you mean "bar"?"')
            ->setInvalidValue('bor + 1')
            ->setCode(ExpressionSyntax::EXPRESSION_SYNTAX_ERROR)
            ->assertRaised();
    }

    public function testExpressionWithAllowedVariableCallbackInvalid()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('a + 1', new ExpressionSyntax([
            'allowedVariablesCallback' => 'invalidCallback',
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionSetVariablesAndCallbackIsNotValid()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $this->validator->validate('a + 1', new ExpressionSyntax([
            'allowedVariables' => ['a'],
            'allowedVariablesCallback' => 'callback',
        ]));
    }
}
