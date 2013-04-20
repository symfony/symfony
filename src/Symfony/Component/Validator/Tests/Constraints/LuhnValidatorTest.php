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

use Symfony\Component\Validator\Constraints\Luhn;
use Symfony\Component\Validator\Constraints\LuhnValidator;

class LuhnValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new LuhnValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Luhn());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Luhn());
    }

    /**
     * @dataProvider getValidNumbers
     */
    public function testValidNumbers($number)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($number, new Luhn());
    }

    public function getValidNumbers()
    {
        return array(
            array('42424242424242424242'),
            array('378282246310005'),
            array('371449635398431'),
            array('378734493671000'),
            array('5610591081018250'),
            array('30569309025904'),
            array('38520000023237'),
            array('6011111111111117'),
            array('6011000990139424'),
            array('3530111333300000'),
            array('3566002020360505'),
            array('5555555555554444'),
            array('5105105105105100'),
            array('4111111111111111'),
            array('4012888888881881'),
            array('4222222222222'),
            array('5019717010103742'),
            array('6331101999990016'),
        );
    }

    /**
     * @dataProvider getInvalidNumbers
     */
    public function testInvalidNumbers($number)
    {
        $constraint = new Luhn();

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with($constraint->message);

        $this->validator->validate($number, $constraint);
    }

    public function getInvalidNumbers()
    {
        return array(
            array('1234567812345678'),
            array('4222222222222222'),
            array('0000000000000000'),
            array(0),
        );
    }
}
