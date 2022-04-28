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

use Symfony\Component\Validator\Constraints\Password;
use Symfony\Component\Validator\Constraints\PasswordValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Jérémy Reynaud <jeremy@reynaud.io>
 */
class PasswordValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordValidator
    {
        return new PasswordValidator();
    }

    /** @dataProvider invalidPasswordProvider */
    public function testInvalidPassword(?string $value, Password $contraint, array $violations)
    {
        $this->validator->validate($value, $contraint);

        $assert = $this;
        $assertMethod = 'buildViolation';

        foreach ($violations as $code => $violation) {
            if (Password::MIN_ERROR === $code) {
                $assert = $assert->$assertMethod($contraint->minMessage)
                    ->setParameter('{{ min }}', $contraint->min)
                    ->setCode(Password::MIN_ERROR)
                    ->setInvalidValue($value)
                ;
            } else {
                $assert = $assert->$assertMethod($violation)
                    ->setCode($code)
                    ->setInvalidValue($value)
                ;
            }

            $assertMethod = 'buildNextViolation';
        }

        $assert->assertRaised();
    }

    public function invalidPasswordProvider(): \Generator
    {
        $passwordConstraint = new Password();

        yield [
            null,
            new Password(),
            [
                Password::MIN_ERROR => $passwordConstraint->minMessage,
            ],
        ];

        yield [
            '',
            new Password(
                mixedCase: true,
                numbers: true,
                symbols: true,
            ),
            [
                Password::MIN_ERROR => $passwordConstraint->minMessage,
                Password::MIXED_CASE_ERROR => $passwordConstraint->mixedCaseMessage,
                Password::SYMBOLS_ERROR => $passwordConstraint->symbolsMessage,
                Password::NUMBERS_ERROR => $passwordConstraint->numbersMessage,
            ],
        ];

        yield [
            'test',
            new Password(
                mixedCase: true,
                numbers: true,
                symbols: true,
            ),
            [
                Password::MIN_ERROR => $passwordConstraint->minMessage,
                Password::MIXED_CASE_ERROR => $passwordConstraint->mixedCaseMessage,
                Password::SYMBOLS_ERROR => $passwordConstraint->symbolsMessage,
                Password::NUMBERS_ERROR => $passwordConstraint->numbersMessage,
            ],
        ];

        yield [
            '0000',
            new Password(
                mixedCase: true,
                numbers: true,
                symbols: true,
            ),
            [
                Password::MIN_ERROR => $passwordConstraint->minMessage,
                Password::MIXED_CASE_ERROR => $passwordConstraint->mixedCaseMessage,
                Password::SYMBOLS_ERROR => $passwordConstraint->symbolsMessage,
            ],
        ];

        yield [
            'azertyuiop000',
            new Password(
                mixedCase: true,
                numbers: true,
                symbols: true,
            ),
            [
                Password::MIXED_CASE_ERROR => $passwordConstraint->mixedCaseMessage,
                Password::SYMBOLS_ERROR => $passwordConstraint->symbolsMessage,
            ],
        ];
    }

    /** @dataProvider validPasswordProvider */
    public function testValidPassword(string $value, Password $contraint)
    {
        $this->validator->validate($value, $contraint);
        $this->assertNoViolation();
    }

    public function validPasswordProvider(): \Generator
    {
        yield ['azertyuiop', new Password(min: 10)];
        yield ['Azertyuiop000@', new Password(mixedCase: true, numbers: true, symbols: true)];
        yield ['LaSa5KE2udBb3=$$6#?8', new Password(mixedCase: true, numbers: true, symbols: true)];
    }
}
