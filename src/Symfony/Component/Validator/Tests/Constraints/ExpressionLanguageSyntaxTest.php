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

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Validator\Constraints\ExpressionLanguageSyntax;
use Symfony\Component\Validator\Constraints\ExpressionLanguageSyntaxValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ExpressionLanguageSyntaxTest extends ConstraintValidatorTestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ExpressionLanguage
     */
    protected $expressionLanguage;

    protected function createValidator()
    {
        return new ExpressionLanguageSyntaxValidator($this->expressionLanguage);
    }

    protected function setUp(): void
    {
        $this->expressionLanguage = $this->createExpressionLanguage();

        parent::setUp();
    }

    public function testExpressionValid(): void
    {
        $this->expressionLanguage->expects($this->once())
            ->method('lint')
            ->with($this->value, []);

        $this->validator->validate($this->value, new ExpressionLanguageSyntax([
            'message' => 'myMessage',
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionWithoutNames(): void
    {
        $this->expressionLanguage->expects($this->once())
            ->method('lint')
            ->with($this->value, null);

        $this->validator->validate($this->value, new ExpressionLanguageSyntax([
            'message' => 'myMessage',
            'validateNames' => false,
        ]));

        $this->assertNoViolation();
    }

    public function testExpressionIsNotValid(): void
    {
        $this->expressionLanguage->expects($this->once())
            ->method('lint')
            ->with($this->value, [])
            ->willThrowException(new SyntaxError('Test exception', 42));

        $this->validator->validate($this->value, new ExpressionLanguageSyntax([
            'message' => 'myMessage',
        ]));

        $this->buildViolation('myMessage')
            ->setParameter('{{ syntax_error }}', '"Test exception around position 42."')
            ->setCode(ExpressionLanguageSyntax::EXPRESSION_LANGUAGE_SYNTAX_ERROR)
            ->assertRaised();
    }

    protected function createExpressionLanguage(): MockObject
    {
        return $this->getMockBuilder('\Symfony\Component\ExpressionLanguage\ExpressionLanguage')->getMock();
    }
}
