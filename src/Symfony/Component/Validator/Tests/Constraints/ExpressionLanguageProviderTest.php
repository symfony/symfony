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
    /**
     * @dataProvider dataProviderCompile
     */
    public function testCompile(string $expression, array $names, string $expected)
    {
        $provider = new ExpressionLanguageProvider();

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider($provider);

        $result = $expressionLanguage->compile($expression, $names);

        $this->assertSame($expected, $result);
    }

    public function dataProviderCompile(): array
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

    /**
     * @dataProvider dataProviderEvaluate
     */
    public function testEvaluate(bool $expected, int $errorsCount)
    {
        $constraints = [new NotNull(), new Range(['min' => 2])];

        $violationList = $this->createMock(ConstraintViolationListInterface::class);
        $violationList->expects($this->once())
            ->method('count')
            ->willReturn($errorsCount);

        $contextualValidator = $this->createMock(ContextualValidatorInterface::class);
        $contextualValidator->expects($this->once())
            ->method('validate')
            ->with('foo', $constraints)
            ->willReturnSelf();
        $contextualValidator->expects($this->once())
            ->method('getViolations')
            ->willReturn($violationList);

        $validator = $this->createMock(ValidatorInterface::class);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('getValidator')
            ->willReturn($validator);

        $validator->expects($this->once())
            ->method('inContext')
            ->with($context)
            ->willReturn($contextualValidator);

        $provider = new ExpressionLanguageProvider();

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider($provider);

        $this->assertSame(
            $expected,
            $expressionLanguage->evaluate('is_valid("foo", a)', ['a' => $constraints, 'context' => $context])
        );
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
