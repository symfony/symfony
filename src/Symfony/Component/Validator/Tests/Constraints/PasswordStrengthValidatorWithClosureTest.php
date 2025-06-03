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

use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Constraints\PasswordStrengthValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\StringableValue;

class PasswordStrengthValidatorWithClosureTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator(static function (string $value) {
            $length = \strlen($value);

            return match (true) {
                $length < 6 => PasswordStrength::STRENGTH_VERY_WEAK,
                $length < 10 => PasswordStrength::STRENGTH_WEAK,
                $length < 15 => PasswordStrength::STRENGTH_MEDIUM,
                $length < 20 => PasswordStrength::STRENGTH_STRONG,
                default => PasswordStrength::STRENGTH_VERY_STRONG,
            };
        });
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues(string|\Stringable $value, int $expectedStrength)
    {
        $this->validator->validate($value, new PasswordStrength(minScore: $expectedStrength));

        $this->assertNoViolation();

        if (PasswordStrength::STRENGTH_VERY_STRONG === $expectedStrength) {
            return;
        }

        $this->validator->validate($value, new PasswordStrength(minScore: $expectedStrength + 1));

        $this->buildViolation('The password strength is too low. Please use a stronger password.')
            ->setCode(PasswordStrength::PASSWORD_STRENGTH_ERROR)
            ->setParameter('{{ strength }}', $expectedStrength)
            ->assertRaised();
    }

    public static function getValidValues(): iterable
    {
        yield ['az34tyu', PasswordStrength::STRENGTH_WEAK];
        yield ['A med1um one', PasswordStrength::STRENGTH_MEDIUM];
        yield ['a str0ng3r one doh', PasswordStrength::STRENGTH_STRONG];
        yield [new StringableValue('HeloW0rld'), PasswordStrength::STRENGTH_WEAK];
    }

    /**
     * @dataProvider provideInvalidConstraints
     */
    public function testThePasswordIsWeak(PasswordStrength $constraint, string $password, string $expectedMessage, string $expectedCode, string $strength)
    {
        $this->validator->validate($password, $constraint);

        $this->buildViolation($expectedMessage)
            ->setCode($expectedCode)
            ->setParameters([
                '{{ strength }}' => $strength,
            ])
            ->assertRaised();
    }

    public static function provideInvalidConstraints(): iterable
    {
        yield [
            new PasswordStrength(),
            'password',
            'The password strength is too low. Please use a stronger password.',
            PasswordStrength::PASSWORD_STRENGTH_ERROR,
            (string) PasswordStrength::STRENGTH_WEAK,
        ];
        yield [
            new PasswordStrength(minScore: PasswordStrength::STRENGTH_VERY_STRONG),
            'Good password?',
            'The password strength is too low. Please use a stronger password.',
            PasswordStrength::PASSWORD_STRENGTH_ERROR,
            (string) PasswordStrength::STRENGTH_MEDIUM,
        ];
        yield [
            new PasswordStrength(message: 'This password should be strong.'),
            'password',
            'This password should be strong.',
            PasswordStrength::PASSWORD_STRENGTH_ERROR,
            (string) PasswordStrength::STRENGTH_WEAK,
        ];
    }
}
