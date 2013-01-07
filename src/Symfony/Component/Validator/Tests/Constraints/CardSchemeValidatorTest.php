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

use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Constraints\CardSchemeValidator;

class CardSchemeValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new CardSchemeValidator();
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

        $this->validator->validate(null, new CardScheme(array('schemes' => array())));
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new CardScheme(array('schemes' => array())));
    }

    /**
     * @dataProvider getValidNumbers
     */
    public function testValidNumbers($scheme, $number)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($number, new CardScheme(array('schemes' => $scheme)));
    }

    /**
     * @dataProvider getInvalidNumbers
     */
    public function testInvalidNumbers($scheme, $number)
    {
        $this->context->expects($this->once())
            ->method('addViolation');

        $this->validator->validate($number, new CardScheme(array('schemes' => $scheme)));
    }

    public function getValidNumbers()
    {
        return array(
            array('AMEX', '378282246310005'),
            array('AMEX', '371449635398431'),
            array('AMEX', '378734493671000'),
            array('DINERS', '30569309025904'),
            array('DISCOVER', '6011111111111117'),
            array('DISCOVER', '6011000990139424'),
            array('JCB', '3530111333300000'),
            array('JCB', '3566002020360505'),
            array('MASTERCARD', '5555555555554444'),
            array('MASTERCARD', '5105105105105100'),
            array('VISA', '4111111111111111'),
            array('VISA', '4012888888881881'),
            array('VISA', '4222222222222'),
            array('VISA', '42424242424242424242'),
            array(array('AMEX', 'VISA'), '42424242424242424242'),
            array(array('AMEX', 'VISA'), '378282246310005'),
            array(array('JCB', 'MASTERCARD'), '5105105105105100'),
            array(array('VISA', 'MASTERCARD'), '5105105105105100'),
        );
    }

    public function getInvalidNumbers()
    {
        return array(
            array('AMEX', '30569309025904'), // DINERS number
            array('AMEX', 'invalid'), // A string
            array('AMEX', '0'), // a lone number
            array('DINERS', '3056930'), // only first part of the number
            array('DISCOVER', '1117'), // only last 4 digits
        );
    }
}
