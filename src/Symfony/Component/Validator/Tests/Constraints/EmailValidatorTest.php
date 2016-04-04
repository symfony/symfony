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

/**
 * @group dns-sensitive
 */
class EmailValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new EmailValidator(false);
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
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Email());
    }

    /**
     * @dataProvider provideEmailAddresses
     *
     * @param array $emailData
     */
    public function testBasicValidationProfile($emailData)
    {
        $this->runValidationProfileTest(Email::PROFILE_BASIC_REGX, $emailData);
    }

    /**
     * @dataProvider provideEmailAddresses
     *
     * @param array $emailData
     */
    public function testHtml5ValidationProfile($emailData)
    {
        $this->runValidationProfileTest(Email::PROFILE_HTML5_REGX, $emailData);
    }

    /**
     * @dataProvider provideEmailAddresses
     *
     * @param array $emailData
     */
    public function testRfcValidationProfile($emailData)
    {
        $this->runValidationProfileTest(Email::PROFILE_RFC_ALLOW_WARNINGS, $emailData);
    }

    /**
     * @dataProvider provideEmailAddresses
     *
     * @param array $emailData
     */
    public function testRfcNoWarnValidationProfile($emailData)
    {
        $this->runValidationProfileTest(Email::PROFILE_RFC_DISALLOW_WARNINGS, $emailData);
    }

    /**
     * @param string $validationProfile
     * @param array  $emailData
     */
    protected function runValidationProfileTest($validationProfile, $emailData)
    {
        $emailAddress = $emailData[0];
        $isValidForProfile = $emailData[1][$validationProfile];

        $constraint = new Email(array(
            'profile' => $validationProfile,
            'message' => 'error message',
        ));

        $this->validator->validate($emailAddress, $constraint);

        if ($isValidForProfile) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('error message')
                ->setParameter('{{ value }}', '"'.$emailAddress.'"')
                ->setCode(Email::INVALID_FORMAT_ERROR)
                ->assertRaised();
        }
    }

    /**
     * @return array
     */
    public function provideEmailAddresses()
    {
        return array(
            // Valid for all validation profiles.
            $this->buildEmailData('hello@world.com',   true,  true,  true,  true),
            $this->buildEmailData('gday@mate.co.uk',   true,  true,  true,  true),
            $this->buildEmailData('bon@jour.fr',       true,  true,  true,  true),
            $this->buildEmailData('aa+@bb.com',        true,  true,  true,  true),
            $this->buildEmailData('aa+b@cc.com',       true,  true,  true,  true),
            $this->buildEmailData('aa.bb-cc@dd.com',   true,  true,  true,  true),
            // Invalid for all validation profiles.
            $this->buildEmailData('test',              false, false, false, false),
            $this->buildEmailData('test@',             false, false, false, false),
            $this->buildEmailData('foo@bar.com baz',   false, false, false, false),
            $this->buildEmailData('aa@local\host',     false, false, false, false),
            $this->buildEmailData('aa@localhost.',     false, false, false, false),
            $this->buildEmailData('aa@bb.com test',    false, false, false, false),
            $this->buildEmailData('aa@ bb . com',      false, false, false, false),
            $this->buildEmailData('aa@bb,com',         false, false, false, false),
            $this->buildEmailData('test@email<',       false, false, false, false),
            // Validity depends on the chosen validation profile.
            $this->buildEmailData('test@localhost',    false, true,  true,  true),
            $this->buildEmailData('test@email&',       false, false, true,  true),
            $this->buildEmailData('.abc@localhost',    false, true,  false, false),
            $this->buildEmailData('example.@aa.co.uk', true,  true,  false, false),
            $this->buildEmailData("fab'ien@test.com",  true,  false, true,  true),
            $this->buildEmailData('fab\ ien@test.com', true,  false, true,  false),
            $this->buildEmailData('aa((bb))@cc.co.uk', true,  false, true,  false),
            $this->buildEmailData('aa@bb(cc).co.uk',   true,  false, true,  false),
            $this->buildEmailData('инфо@письмо.рф',    true,  false, true,  true),
            $this->buildEmailData('"aa@bb"@cc.com',    true,  false, true,  false),
            $this->buildEmailData('"\""@iana.org',     true,  false, true,  false),
            $this->buildEmailData('""@iana.org',       true,  false, true,  false),
            $this->buildEmailData('"aa\ bb"@cc.org',   true,  false, true,  false),
            $this->buildEmailData('aa@(bb).com',       true,  false, false, false),
            $this->buildEmailData('aa@(bb.com',        true,  false, false, false),
            $this->buildEmailData('usern,ame@cc.com',  true,  false, false, false),
            $this->buildEmailData('user[na]me@cc.com', true,  false, false, false),
            $this->buildEmailData('"""@iana.org',      true,  false, false, false),
            $this->buildEmailData('"\"@iana.org',      true,  false, false, false),
            $this->buildEmailData('"aa"bb@cc.org',     true,  false, false, false),
            $this->buildEmailData('"aa""bb"@cc.org',   true,  false, false, false),
            $this->buildEmailData('"aa"."bb"@cc.org',  true,  false, false, false),
            $this->buildEmailData('"aa".bb@cc.org',    true,  false, false, false),
            $this->buildEmailData('aa@bb@cc.co.uk',    true,  false, false, false),
            $this->buildEmailData('(aa@bb.cc)',        true,  false, false, false),
            $this->buildEmailData('aa(bb)cc@dd.co.uk', true,  false, false, false),
            $this->buildEmailData('user name@aa.com',  true,  false, false, false),
            $this->buildEmailData('user  name@aa.com', true,  false, false, false),
            $this->buildEmailData('test@aa/bb.org',    true,  false, false, false),
            $this->buildEmailData('test@foo;bar.com',  true,  false, false, false),
            $this->buildEmailData('test;123@bb.com',   true,  false, false, false),
            $this->buildEmailData('test@example..com', true,  false, false, false),
            $this->buildEmailData('aa.bb@cc."',        true,  false, false, false),
            $this->buildEmailData('test@ema[il.com',   true,  false, false, false),
        );
    }

    /**
     * @param string $email     The email address to validate.
     * @param bool   $basic     Whether the email is valid per the 'basic' profile.
     * @param bool   $html5     Whether the email is valid per the 'html5' profile.
     * @param bool   $rfc       Whether the email is valid per the 'rfc' profile.
     * @param bool   $rfcNoWarn Whether the email is valid per the 'rfc-no-warn' profile.
     *
     * @return array
     */
    protected function buildEmailData($email, $basic, $html5, $rfc, $rfcNoWarn)
    {
        return array(
            array(
                $email,
                array(
                    Email::PROFILE_BASIC_REGX => $basic,
                    Email::PROFILE_HTML5_REGX => $html5,
                    Email::PROFILE_RFC_ALLOW_WARNINGS => $rfc,
                    Email::PROFILE_RFC_DISALLOW_WARNINGS => $rfcNoWarn,
                ),
            ),
        );
    }

    /**
     * @deprecated
     */
    public function testStrict()
    {
        $constraint = new Email(array('strict' => true));

        $this->validator->validate('example@localhost', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getDnsChecks
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     */
    public function testDnsChecks($type, $violation)
    {
        DnsMock::withMockedHosts(array('example.com' => array(array('type' => $violation ? false : $type))));

        $constraint = new Email(array(
            'message' => 'myMessage',
            'MX' === $type ? 'checkMX' : 'checkHost' => true,
        ));

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
        return array(
            array('MX', false),
            array('MX', Email::MX_CHECK_FAILED_ERROR),
            array('A', false),
            array('A', Email::HOST_CHECK_FAILED_ERROR),
            array('AAAA', false),
            array('AAAA', Email::HOST_CHECK_FAILED_ERROR),
        );
    }

    /**
     * @requires function Symfony\Bridge\PhpUnit\DnsMock::withMockedHosts
     */
    public function testHostnameIsProperlyParsed()
    {
        DnsMock::withMockedHosts(array('baz.com' => array(array('type' => 'MX'))));

        $this->validator->validate(
            '"foo@bar"@baz.com',
            new Email(array('checkMX' => true))
        );

        $this->assertNoViolation();
    }
}
