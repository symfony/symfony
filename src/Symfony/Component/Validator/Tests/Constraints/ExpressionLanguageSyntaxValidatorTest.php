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
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @group legacy
 */
class ExpressionLanguageSyntaxValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ConstraintValidatorInterface
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
}
