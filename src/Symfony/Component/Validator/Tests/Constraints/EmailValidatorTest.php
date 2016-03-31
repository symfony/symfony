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
     * @dataProvider getValidEmails
     */
    public function testValidEmails($email)
    {
        $this->validator->validate($email, new Email());

        $this->assertNoViolation();
    }

    public function getValidEmails()
    {
        return array(
            array('fabien@symfony.com'),
            array('example@example.co.uk'),
            array('fabien_potencier@example.fr'),
        );
    }

    /**
     * @dataProvider getInvalidEmails
     */
    public function testInvalidEmails($email)
    {
        $constraint = new Email(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($email, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$email.'"')
            ->assertRaised();
    }

    public function getInvalidEmails()
    {
        return array(
            array('example'),
            array('example@'),
            array('example@localhost'),
        );
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
                ->assertRaised();
        }
    }

    public function getDnsChecks()
    {
        return array(
            array('MX', false),
            array('MX', true),
            array('A', false),
            array('A', true),
            array('AAAA', false),
            array('AAAA', true),
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
