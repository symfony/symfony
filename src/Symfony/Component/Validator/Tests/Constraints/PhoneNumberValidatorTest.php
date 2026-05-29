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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Constraints\PhoneNumberValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
class PhoneNumberValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PhoneNumberValidator
    {
        return new PhoneNumberValidator();
    }

    public function testUnknownDefaultModeTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "defaultMode" parameter value is not valid.');

        new PhoneNumberValidator('Unknown Mode');
    }

    public function testNullIsValid()
    {
        $this->validate(null, new PhoneNumber());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validate('', new PhoneNumber());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validate(new \stdClass(), new PhoneNumber());
    }

    #[DataProvider('getValidPhoneNumbers')]
    public function testValidPhoneNumber($phoneNumber)
    {
        $this->validate($phoneNumber, new PhoneNumber());

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalidPhoneNumbers')]
    public function testInvalidPhoneNumber($phoneNumber)
    {
        $this->validate($phoneNumber, new PhoneNumber());

        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', '"'.$phoneNumber.'"')
            ->setCode(PhoneNumber::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function testNormalizerRunsBeforeValidation()
    {
        $constraint = new PhoneNumber(normalizer: static fn ($value) => str_replace([' ', '-'], '', $value));

        $this->validate('+1 415-555 2671', $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getValidStrictPhoneNumbers')]
    public function testValidStrictPhoneNumber($phoneNumber)
    {
        $this->validate($phoneNumber, new PhoneNumber(mode: PhoneNumber::MODE_STRICT));

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalidStrictPhoneNumbers')]
    public function testInvalidStrictPhoneNumber($phoneNumber)
    {
        $this->validate($phoneNumber, new PhoneNumber(mode: PhoneNumber::MODE_STRICT));

        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', '"'.$phoneNumber.'"')
            ->setCode(PhoneNumber::INVALID_PHONE_NUMBER_ERROR)
            ->assertRaised();
    }

    public function testStrictModeStillRejectsMalformedFormatWithFormatError()
    {
        $this->validate('14155552671', new PhoneNumber(mode: PhoneNumber::MODE_STRICT));

        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', '"14155552671"')
            ->setCode(PhoneNumber::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function testDefaultStrictMode()
    {
        $this->validator = new PhoneNumberValidator(PhoneNumber::MODE_STRICT);

        $this->validate('+11111111111', new PhoneNumber());

        $this->buildViolation('This value is not a valid phone number.')
            ->setParameter('{{ value }}', '"+11111111111"')
            ->setCode(PhoneNumber::INVALID_PHONE_NUMBER_ERROR)
            ->assertRaised();
    }

    public function testUnknownModesOnValidateTriggerException()
    {
        $constraint = new PhoneNumber();
        $constraint->mode = 'Unknown Mode';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\PhoneNumber::$mode" parameter value is not valid.');

        $this->validate('+14155552671', $constraint);
    }

    public static function getValidPhoneNumbers(): array
    {
        return [
            ['+14155552671'],
            ['+442071838750'],
            ['+861082015000'],
            ['+12'],
            ['+123456789012345'],
        ];
    }

    public static function getInvalidPhoneNumbers(): array
    {
        return [
            ['14155552671'],
            ['+0155552671'],
            ['+1 415 555 2671'],
            ['+1234567890123456'],
            ['+'],
            ['+abc'],
            ['+1-800-1234'],
        ];
    }

    public static function getValidStrictPhoneNumbers(): array
    {
        return [
            ['+14155552671'],
            ['+442071838750'],
        ];
    }

    public static function getInvalidStrictPhoneNumbers(): array
    {
        // Syntactically valid E.164 numbers that are not actually assignable.
        return [
            ['+11111111111'],
            ['+123456789012345'],
        ];
    }
}
