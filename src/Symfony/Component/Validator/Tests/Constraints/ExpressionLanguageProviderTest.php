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

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\Constraints\ExpressionValidator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class ExpressionLanguageProviderTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new ExpressionValidator();
    }

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

    public function testEvaluateValid()
    {
        $constraints = [new Length(['min' => 2, 'max' => 12])];

        $this->expectValidateValue(0, 'foo', $constraints);

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ExpressionLanguageProvider());

        $this->assertTrue($expressionLanguage->evaluate('is_valid("foo", a)', ['a' => $constraints, 'context' => $this->context]));
    }

    public function testEvaluateInvalid()
    {
        $constraints = [new Length(['min' => 7, 'max' => 12])];

        $this->expectFailingValueValidation(
            0,
            'foo',
            $constraints,
            null,
            new ConstraintViolation('error_length', '', [], '', '', 'foo', null, 'range')
        );

        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ExpressionLanguageProvider());

        $this->assertFalse($expressionLanguage->evaluate('is_valid("foo", a)', ['a' => $constraints, 'context' => $this->context]));
    }
}
