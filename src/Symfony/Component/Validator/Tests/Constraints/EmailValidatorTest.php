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

use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @group dns-sensitive
 */
class EmailValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new EmailValidator(Email::VALIDATION_MODE_LOOSE);
    }

    /**
     * @expectedDeprecation Calling `new Symfony\Component\Validator\Constraints\EmailValidator(true)` is deprecated since Symfony 4.1, use `new Symfony\Component\Validator\Constraints\EmailValidator("strict")` instead.
     * @group legacy
     */
    public function testLegacyValidatorConstructorStrict()
    {
        $this->validator = new EmailValidator(true);
        $this->validator->initialize($this->context);
        $this->validator->validate('example@localhost', new Email());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The "defaultMode" parameter value is not valid.
     */
    public function testUnknownDefaultModeTriggerException()
    {
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

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
     */
    public function testExpectsStringCompatibleType()
    {
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

    public function getValidEmails()
    {
        return [
            ['fabien@symfony.com'],
            ['example@example.co.uk'],
            ['fabien_potencier@example.fr'],
            ['example@example.co..uk'],
            ['{}~!@!@£$%%^&*().!@£$%^&*()'],
            ['example@example.co..uk'],
            ['example@-example.com'],
            [sprintf('example@%s.com', str_repeat('a', 64))],
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

    public function getValidEmailsHtml5()
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

    public function getInvalidEmails()
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

    public function getInvalidHtml5Emails()
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

    public function testModeStrict()
    {
        $constraint = new Email(['mode' => Email::VALIDATION_MODE_STRICT]);

        $this->validator->validate('example@localhost', $constraint);

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

    public function testModeLoose()
    {
        $constraint = new Email(['mode' => Email::VALIDATION_MODE_LOOSE]);

        $this->validator->validate('example@example..com', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The Symfony\Component\Validator\Constraints\Email::$mode parameter value is not valid.
     */
    public function testUnknownModesOnValidateTriggerException()
    {
        $constraint = new Email();
        $constraint->mode = 'Unknown Mode';

        $this->validator->validate('example@example..com', $constraint);
    }

    /**
     * @expectedDeprecation The "strict" property is deprecated since Symfony 4.1. Use "mode"=>"strict" instead.
     * @expectedDeprecation The Symfony\Component\Validator\Constraints\Email::$strict property is deprecated since Symfony 4.1. Use Symfony\Component\Validator\Constraints\Email::mode="strict" instead.
     * @group legacy
     */
    public function testStrict()
    {
        $constraint = new Email(['strict' => true]);

        $this->validator->validate('example@localhost', $constraint);

        $this->assertNoViolation();
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
    public function getInvalidEmailsForStrictChecks()
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
            [str_repeat('x', 254).'@example.com'], //email with warnings
        ];
    }

    /**
     * @dataProvider getDnsChecks
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     * @group legacy
     */
    public function testDnsChecks($type, $violation)
    {
        DnsMock::withMockedHosts(['example.com' => [['type' => $violation ? false : $type]]]);

        $constraint = new Email([
            'message' => 'myMessage',
            'MX' === $type ? 'checkMX' : 'checkHost' => true,
        ]);

        $this->validator->validate('foo@example.com', $constraint);

        if (!$violation) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('myMessage')
                ->setParameter('{{ value }}', '"foo@example.com"')
                ->setCode($violation)
                ->assertRaised();
        }
    }

    public function getDnsChecks()
    {
        return [
            ['MX', false],
            ['MX', Email::MX_CHECK_FAILED_ERROR],
            ['A', false],
            ['A', Email::HOST_CHECK_FAILED_ERROR],
            ['AAAA', false],
            ['AAAA', Email::HOST_CHECK_FAILED_ERROR],
        ];
    }

    /**
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     * @group legacy
     */
    public function testHostnameIsProperlyParsed()
    {
        DnsMock::withMockedHosts(['baz.com' => [['type' => 'MX']]]);

        $this->validator->validate(
            '"foo@bar"@baz.com',
            new Email(['checkMX' => true])
        );

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideCheckTypes
     * @group legacy
     */
    public function testEmptyHostIsNotValid($checkType, $violation)
    {
        $this->validator->validate(
            'foo@bar.fr@',
            new Email([
                'message' => 'myMessage',
                $checkType => true,
            ])
        );

        $this
            ->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"foo@bar.fr@"')
            ->setCode($violation)
            ->assertRaised();
    }

    public function provideCheckTypes()
    {
        return [
            ['checkMX', Email::MX_CHECK_FAILED_ERROR],
            ['checkHost', Email::HOST_CHECK_FAILED_ERROR],
        ];
    }
}
