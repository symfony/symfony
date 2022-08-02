<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Constraints;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ExpressionLanguageProviderTest extends TestCase
{
    public function testCompile(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "is_valid" function cannot be compiled.');

        $context = $this->createMock(ExecutionContextInterface::class);

        $provider = new ExpressionLanguageProvider($context);

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider($provider);

        $expressionLanguage->compile('is_valid()');
    }

    /**
     * @dataProvider dataProviderEvaluate
     */
    public function testEvaluate(bool $expected, int $errorsCount): void
    {
        $constraints = [new NotNull(), new Range(['min' => 2])];

        $violationList = $this->getMockBuilder(ConstraintViolationListInterface::class)
            ->onlyMethods(['count'])
            ->getMockForAbstractClass();
        $violationList->expects($this->once())
            ->method('count')
            ->willReturn($errorsCount);

        $contextualValidator = $this->getMockBuilder(ContextualValidatorInterface::class)
            ->onlyMethods(['getViolations', 'validate'])
            ->getMockForAbstractClass();
        $contextualValidator->expects($this->once())
            ->method('validate')
            ->with('foo', $constraints)
            ->willReturnSelf();
        $contextualValidator->expects($this->once())
            ->method('getViolations')
            ->willReturn($violationList);

        $validator = $this->getMockBuilder(ValidatorInterface::class)
            ->onlyMethods(['inContext'])
            ->getMockForAbstractClass();

        $context = $this->getMockBuilder(ExecutionContextInterface::class)
            ->onlyMethods(['getValidator'])
            ->getMockForAbstractClass();
        $context->expects($this->once())
            ->method('getValidator')
            ->willReturn($validator);

        $validator->expects($this->once())
            ->method('inContext')
            ->with($context)
            ->willReturn($contextualValidator);

        $provider = new ExpressionLanguageProvider($context);

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider($provider);

        $this->assertSame($expected, $expressionLanguage->evaluate('is_valid("foo", a)', ['a' => $constraints]));
    }

    public function dataProviderEvaluate(): array
    {
        return [
            [true, 0],
            [false, 1],
            [false, 12],
        ];
    }
}
