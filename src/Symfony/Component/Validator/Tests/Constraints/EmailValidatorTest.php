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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @group dns-sensitive
 */
class EmailValidatorTest extends ConstraintValidatorTestCase
{
    use ExpectDeprecationTrait;

    protected function createValidator(): EmailValidator
    {
        return new EmailValidator(Email::VALIDATION_MODE_HTML5);
    }

    public function testUnknownDefaultModeTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "defaultMode" parameter value is not valid.');
        new EmailValidator('Unknown Mode');
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Email());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Email());

        $this->assertNoViolation();
    }

    public function testObjectEmptyStringIsValid()
    {
        $this->validator->validate(new EmptyEmailObject(), new Email());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Email());
    }

    /**
     * @dataProvider getValidEmails
     */
    public function testValidEmails($email)
    {
        $this->validator->validate($email, new Email());

        $this->assertNoViolation();
    }

    public static function getValidEmails()
    {
        return [
            ['fabien@symfony.com'],
            ['example@example.co.uk'],
            ['fabien_potencier@example.fr'],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider getValidEmails
     * @dataProvider getEmailsOnlyValidInLooseMode
     */
    public function testValidInLooseModeEmails($email)
    {
        $this->validator->validate($email, new Email(['mode' => Email::VALIDATION_MODE_LOOSE]));

        $this->assertNoViolation();
    }

    public static function getEmailsOnlyValidInLooseMode()
    {
        return [
            ['example@example.co..uk'],
            ['{}~!@!@£$%%^&*().!@£$%^&*()'],
            ['example@example.co..uk'],
            ['example@-example.com'],
            [sprintf('example@%s.com', str_repeat('a', 64))],
        ];
    }

    /**
     * @dataProvider getValidEmailsWithWhitespaces
     */
    public function testValidNormalizedEmails($email)
    {
        $this->validator->validate($email, new Email(['normalizer' => 'trim']));

        $this->assertNoViolation();
    }

    public static function getValidEmailsWithWhitespaces()
    {
        return [
            ["\x20example@example.co.uk\x20"],
            ["example@example.com\x0B\x0B"],
        ];
    }

    /**
     * @group legacy
     *
     * @dataProvider getValidEmailsWithWhitespaces
     * @dataProvider getEmailsWithWhitespacesOnlyValidInLooseMode
     */
    public function testValidNormalizedEmailsInLooseMode($email)
    {
        $this->validator->validate($email, new Email(['mode' => Email::VALIDATION_MODE_LOOSE, 'normalizer' => 'trim']));

        $this->assertNoViolation();
    }

    public static function getEmailsWithWhitespacesOnlyValidInLooseMode()
    {
        return [
            ["\x09\x09example@example.co..uk\x09\x09"],
            ["\x0A{}~!@!@£$%%^&*().!@£$%^&*()\x0A"],
            ["\x0D\x0Dexample@example.co..uk\x0D\x0D"],
            ["\x00example@-example.com"],
        ];
    }

    /**
     * @dataProvider getValidEmailsHtml5
     */
    public function testValidEmailsHtml5($email)
    {
        $this->validator->validate($email, new Email(['mode' => Email::VALIDATION_MODE_HTML5]));

        $this->assertNoViolation();
    }

    public static function getValidEmailsHtml5()
    {
        return [
            ['fabien@symfony.com'],
            ['example@example.co.uk'],
            ['fabien_potencier@example.fr'],
            ['{}~!@example.com'],
        ];
    }

    /**
     * @dataProvider getInvalidEmails
     */
    public function testInvalidEmails($email)
    {
        $constraint = new Email([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($email, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setCode(Email::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidEmails()
    {
        return [
            ['example'],
            ['example@'],
            ['example@localhost'],
            ['foo@example.com bar'],
        ];
    }

    /**
     * @dataProvider getInvalidHtml5Emails
     */
    public function testInvalidHtml5Emails($email)
    {
        $constraint = new Email([
            'message' => 'myMessage',
            'mode' => Email::VALIDATION_MODE_HTML5,
        ]);

        $this->validator->validate($email, $constraint);

        $this->buildViolation('myMessage')
             ->setParameter('{{ value }}', '"'.$email.'"')
             ->setCode(Email::INVALID_FORMAT_ERROR)
             ->assertRaised();
    }

    public static function getInvalidHtml5Emails()
    {
        return [
            ['example'],
            ['example@'],
            ['example@localhost'],
            ['example@example.co..uk'],
            ['foo@example.com bar'],
            ['example@example.'],
            ['example@.fr'],
            ['@example.com'],
            ['example@example.com;example@example.com'],
            ['example@.'],
            [' example@example.com'],
            ['example@ '],
            [' example@example.com '],
            [' example @example .com '],
            ['example@-example.com'],
            [sprintf('example@%s.com', str_repeat('a', 64))],
        ];
    }

    /**
     * @dataProvider getInvalidAllowNoTldEmails
     */
    public function testInvalidAllowNoTldEmails($email)
    {
        $constraint = new Email([
            'message' => 'myMessage',
            'mode' => Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD,
        ]);

        $this->validator->validate($email, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setCode(Email::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidAllowNoTldEmails()
    {
        return [
            ['example bar'],
            ['example@'],
            ['example@ bar'],
            ['example@localhost bar'],
            ['foo@example.com bar'],
        ];
    }

    public function testModeStrict()
    {
        $constraint = new Email(['mode' => Email::VALIDATION_MODE_STRICT]);

        $this->validator->validate('example@mywebsite.tld', $constraint);

        $this->assertNoViolation();
    }

    public function testModeHtml5()
    {
        $constraint = new Email(['mode' => Email::VALIDATION_MODE_HTML5]);

        $this->validator->validate('example@example..com', $constraint);

        $this->buildViolation('This value is not a valid email address.')
             ->setParameter('{{ value }}', '"example@example..com"')
             ->setCode(Email::INVALID_FORMAT_ERROR)
             ->assertRaised();
    }

    public function testModeHtml5AllowNoTld()
    {
        $constraint = new Email(['mode' => Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD]);

        $this->validator->validate('example@example', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     */
    public function testModeLoose()
    {
        $this->expectDeprecation('Since symfony/validator 6.2: The "loose" mode is deprecated. It will be removed in 7.0 and the default mode will be changed to "html5".');

        $constraint = new Email(['mode' => Email::VALIDATION_MODE_LOOSE]);

        $this->validator->validate('example@example..com', $constraint);

        $this->assertNoViolation();
    }

    public function testUnknownModesOnValidateTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Validator\Constraints\Email::$mode" parameter value is not valid.');
        $constraint = new Email();
        $constraint->mode = 'Unknown Mode';

        $this->validator->validate('example@example..com', $constraint);
    }

    /**
     * @dataProvider getInvalidEmailsForStrictChecks
     */
    public function testStrictWithInvalidEmails($email)
    {
        $constraint = new Email([
            'message' => 'myMessage',
            'mode' => Email::VALIDATION_MODE_STRICT,
        ]);

        $this->validator->validate($email, $constraint);

        $this
            ->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->setCode(Email::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    /**
     * @see https://github.com/egulias/EmailValidator/blob/1.2.8/tests/egulias/Tests/EmailValidator/EmailValidatorTest.php
     */
    public static function getInvalidEmailsForStrictChecks()
    {
        return [
            ['test@example.com test'],
            ['user  name@example.com'],
            ['user   name@example.com'],
            ['example.@example.co.uk'],
            ['example@example@example.co.uk'],
            ['(test_exampel@example.fr)'],
            ['example(example)example@example.co.uk'],
            ['.example@localhost'],
            ['ex\ample@localhost'],
            ['example@local\host'],
            ['example@localhost.'],
            ['user name@example.com'],
            ['username@ example . com'],
            ['example@(fake).com'],
            ['example@(fake.com'],
            ['username@example,com'],
            ['usern,ame@example.com'],
            ['user[na]me@example.com'],
            ['"""@iana.org'],
            ['"\"@iana.org'],
            ['"test"test@iana.org'],
            ['"test""test"@iana.org'],
            ['"test"."test"@iana.org'],
            ['"test".test@iana.org'],
            ['"test"'.\chr(0).'@iana.org'],
            ['"test\"@iana.org'],
            [\chr(226).'@iana.org'],
            ['test@'.\chr(226).'.org'],
            ['\r\ntest@iana.org'],
            ['\r\n test@iana.org'],
            ['\r\n \r\ntest@iana.org'],
            ['\r\n \r\ntest@iana.org'],
            ['\r\n \r\n test@iana.org'],
            ['test@iana.org \r\n'],
            ['test@iana.org \r\n '],
            ['test@iana.org \r\n \r\n'],
            ['test@iana.org \r\n\r\n'],
            ['test@iana.org  \r\n\r\n '],
            ['test@iana/icann.org'],
            ['test@foo;bar.com'],
            ['test;123@foobar.com'],
            ['test@example..com'],
            ['email.email@email."'],
            ['test@email>'],
            ['test@email<'],
            ['test@email{'],
            [str_repeat('x', 254).'@example.com'], // email with warnings
        ];
    }
}

class EmptyEmailObject
{
    public function __toString(): string
    {
        return '';
    }
}
