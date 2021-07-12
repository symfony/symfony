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
use Symfony\Component\Validator\Constraints\ExpressionLanguageSyntax;
use Symfony\Component\Validator\Constraints\ExpressionLanguageSyntaxValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ExpressionLanguageSyntaxValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new ExpressionLanguageSyntaxValidator(new ExpressionLanguage());
    }

    public function testExpressionValid()
    {
        $this->validator->validate('1 + 1', new ExpressionLanguageSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithoutNames()
    {
        $this->validator->validate('1 + 1', new ExpressionLanguageSyntax([
            'message' => 'myMessage',
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithAllowedVariableName()
    {
        $this->validator->validate('a + 1', new ExpressionLanguageSyntax([
            'message' => 'myMessage',
            'allowedVariables' => ['a'],
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionIsNotValid()
    {
        $this->validator->validate('a + 1', new ExpressionLanguageSyntax([
            'message' => 'myMessage',
            'allowedVariables' => [],
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ syntax_error }}', '"Variable "a" is not valid around position 1 for expression `a + 1`."')
            ->setInvalidValue('a + 1')
            ->setCode(ExpressionLanguageSyntax::EXPRESSION_LANGUAGE_SYNTAX_ERROR)
            ->assertRaised();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new ExpressionLanguageSyntax([
            'allowNullAndEmptyString' => true,
        ]));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     */
    public function testNullWithoutAllowOptionIsNotValid()
    {
        $this->expectExceptionMessage('Expected argument of type "string", "null" given');

        $this->validator->validate(null, new ExpressionLanguageSyntax());
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new ExpressionLanguageSyntax([
            'allowNullAndEmptyString' => true,
        ]));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     */
    public function testEmptyStringWithoutAllowOptionIsNotValid()
    {
        $this->validator->validate('', new ExpressionLanguageSyntax());

        $this->buildViolation('This value should be a valid expression.')
            ->setParameter('{{ syntax_error }}', '"Unexpected token "end of expression" of value "" around position 1."')
            ->setInvalidValue('')
            ->setCode(ExpressionLanguageSyntax::EXPRESSION_LANGUAGE_SYNTAX_ERROR)
            ->assertRaised();
    }
}
