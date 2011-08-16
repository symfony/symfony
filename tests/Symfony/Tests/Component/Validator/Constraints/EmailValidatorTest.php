<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EmailValidator;

class EmailValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new EmailValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Email()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Email()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Email());
    }

    /**
     * @dataProvider getValidEmailsSingle
     */
    public function testValidEmailsSingle($email)
    {
        $this->assertTrue($this->validator->isValid($email, new Email()));
    }

    public function getValidEmailsSingle()
    {
        return array(
            array('fabien@symfony.com'),
            array('example@example.co.uk'),
            array('fabien_potencier@example.fr'),
        );
    }

    /**
     * @dataProvider getInvalidEmailsSingle
     */
    public function testInvalidEmailsSingle($email)
    {
        $this->assertFalse($this->validator->isValid($email, new Email()));
    }

    public function getInvalidEmailsSingle()
    {
        return array(
            array('example'),
            array('example@'),
            array('example@localhost'),
            array('example@example.com@example.com'),
            array('fabien@symfony.com,example@example.co.uk'),
            array('fabien@symfony.com,example'),
        );
    }

    /**
     * @dataProvider getValidEmailsMultiple
     */
    public function testValidEmailsMultiple($email)
    {
        $constraint = new Email(array(
            'multiple' => true,
        ));
        $this->assertTrue($this->validator->isValid($email, $constraint));
    }

    public function getValidEmailsMultiple()
    {
        return array(
            array('fabien@symfony.com'),
            array('example@example.co.uk'),
            array('fabien_potencier@example.fr'),
            array('fabien@symfony.com,fabien_potencier@example.fr'),
            array('example@example.co.uk,fabien@symfony.com'),
            array('fabien_potencier@example.fr,example@example.co.uk'),
        );
    }

    /**
     * @dataProvider getInvalidEmailsMultiple
     */
    public function testInvalidEmailsMultiple($email)
    {
        $constraint = new Email(array(
            'multiple' => true,
        ));
        $this->assertFalse($this->validator->isValid($email, $constraint));
    }

    public function getInvalidEmailsMultiple()
    {
        return array(
            array('example'),
            array('example@'),
            array('example@localhost'),
            array('example@localhost,example@example.co.uk'),
            array('example@example.com@example.com'),
            array('fabien@symfony.com,example'),
            array('fabien_potencier@example.fr,example@example.co.uk,'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Email(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
