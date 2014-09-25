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
use Symfony\Component\Validator\Validation;

class CardSchemeValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new CardSchemeValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new CardScheme(array('schemes' => array())));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new CardScheme(array('schemes' => array())));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidNumbers
     */
    public function testValidNumbers($scheme, $number)
    {
        $this->validator->validate($number, new CardScheme(array('schemes' => $scheme)));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidNumbers
     */
    public function testInvalidNumbers($scheme, $number)
    {
        $constraint = new CardScheme(array(
            'schemes' => $scheme,
            'message' => 'myMessage',
        ));

        $this->validator->validate($number, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', is_string($number) ? '"'.$number.'"' : $number)
            ->assertRaised();
    }

    public function getValidNumbers()
    {
        return array(
            array('AMEX', '378282246310005'),
            array('AMEX', '371449635398431'),
            array('AMEX', '378734493671000'),
            array('AMEX', '347298508610146'),
            array('CHINA_UNIONPAY', '6228888888888888'),
            array('CHINA_UNIONPAY', '62288888888888888'),
            array('CHINA_UNIONPAY', '622888888888888888'),
            array('CHINA_UNIONPAY', '6228888888888888888'),
            array('DINERS', '30569309025904'),
            array('DINERS', '36088894118515'),
            array('DINERS', '38520000023237'),
            array('DISCOVER', '6011111111111117'),
            array('DISCOVER', '6011000990139424'),
            array('INSTAPAYMENT', '6372476031350068'),
            array('INSTAPAYMENT', '6385537775789749'),
            array('INSTAPAYMENT', '6393440808445746'),
            array('JCB', '3530111333300000'),
            array('JCB', '3566002020360505'),
            array('JCB', '213112345678901'),
            array('JCB', '180012345678901'),
            array('LASER', '6304678107004080'),
            array('LASER', '6706440607428128629'),
            array('LASER', '6771656738314582216'),
            array('MAESTRO', '6759744069209'),
            array('MAESTRO', '5020507657408074712'),
            array('MAESTRO', '6759744069209'),
            array('MAESTRO', '6759744069209'),
            array('MASTERCARD', '5555555555554444'),
            array('MASTERCARD', '5105105105105100'),
            array('VISA', '4111111111111111'),
            array('VISA', '4012888888881881'),
            array('VISA', '4222222222222'),
            array(array('AMEX', 'VISA'), '4111111111111111'),
            array(array('AMEX', 'VISA'), '378282246310005'),
            array(array('JCB', 'MASTERCARD'), '5105105105105100'),
            array(array('VISA', 'MASTERCARD'), '5105105105105100'),
        );
    }

    public function getInvalidNumbers()
    {
        return array(
            array('VISA', '42424242424242424242'),
            array('AMEX', '357298508610146'),
            array('DINERS', '31569309025904'),
            array('DINERS', '37088894118515'),
            array('INSTAPAYMENT', '6313440808445746'),
            array('CHINA_UNIONPAY', '622888888888888'),
            array('CHINA_UNIONPAY', '62288888888888888888'),
            array('AMEX', '30569309025904'), // DINERS number
            array('AMEX', 'invalid'), // A string
            array('AMEX', 0), // a lone number
            array('AMEX', '0'), // a lone number
            array('AMEX', '000000000000'), // a lone number
            array('DINERS', '3056930'), // only first part of the number
            array('DISCOVER', '1117'), // only last 4 digits
        );
    }
}
