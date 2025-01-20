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

class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
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
        yield ['How-is-this', PasswordStrength::STRENGTH_WEAK];
        yield ['Reasonable-pwd', PasswordStrength::STRENGTH_MEDIUM];
        yield ['This 1s a very g00d Pa55word! ;-)', PasswordStrength::STRENGTH_VERY_STRONG];
        yield ['pudding-smack-👌🏼-fox-😎', PasswordStrength::STRENGTH_VERY_STRONG];
        yield [new StringableValue('How-is-this'), PasswordStrength::STRENGTH_WEAK];
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
            '0',
        ];
        yield [
            new PasswordStrength(minScore: PasswordStrength::STRENGTH_VERY_STRONG),
            'Good password?',
            'The password strength is too low. Please use a stronger password.',
            PasswordStrength::PASSWORD_STRENGTH_ERROR,
            '1',
        ];
        yield [
            new PasswordStrength(message: 'This password should be strong.'),
            'password',
            'This password should be strong.',
            PasswordStrength::PASSWORD_STRENGTH_ERROR,
            '0',
        ];
    }

    /**
     * @dataProvider getPasswordValues
     */
    public function testStrengthEstimator(string $password, int $expectedStrength)
    {
        self::assertSame($expectedStrength, PasswordStrengthValidator::estimateStrength((string) $password));
    }

    public static function getPasswordValues(): iterable
    {
        yield ['How-is-this', PasswordStrength::STRENGTH_WEAK];
        yield ['Reasonable-pwd', PasswordStrength::STRENGTH_MEDIUM];
        yield ['This 1s a very g00d Pa55word! ;-)', PasswordStrength::STRENGTH_VERY_STRONG];
        yield ['pudding-smack-👌🏼-fox-😎', PasswordStrength::STRENGTH_VERY_STRONG];
    }
}
