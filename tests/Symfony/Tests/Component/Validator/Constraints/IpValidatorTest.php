<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;

class IpValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new IpValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Ip()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Ip()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Ip());
    }

    /**
     * @dataProvider getValidIpsV4
     */
    public function testValidIpsV4($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Ip(array(
            'version' => Ip::V4,
        ))));
    }

    public function getValidIpsV4()
    {
        return array(
            array('0.0.0.0'),
            array('255.255.255.255'),
            array('123.45.67.178'),
        );
    }

    /**
     * @dataProvider getValidIpsV6
     */
    public function testValidIpsV6($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Ip(array(
            'version' => Ip::V6,
        ))));
    }

    public function getValidIpsV6()
    {
        return array(
            array('2001:0db8:85a3:0000:0000:8a2e:0370:7334'),
            array('2001:0DB8:85A3:0000:0000:8A2E:0370:7334'),
            array('2001:0Db8:85a3:0000:0000:8A2e:0370:7334'),
            array('fe80:0000:0000:0000:0202:b3ff:fe1e:8329'),
            array('fe80:0:0:0:202:b3ff:fe1e:8329'),
            array('fe80::202:b3ff:fe1e:8329'),
            array('0:0:0:0:0:0:0:0'),
            array('::'),
            array('0::'),
            array('::0'),
            array('0::0'),
            // IPv4 mapped to IPv6
            array('2001:0db8:85a3:0000:0000:8a2e:0.0.0.0'),
            array('::0.0.0.0'),
            array('::255.255.255.255'),
            array('::123.45.67.178'),
        );
    }

    /**
     * @dataProvider getValidIpsAll
     */
    public function testValidIpsAll($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Ip(array(
            'version' => Ip::ALL,
        ))));
    }

    public function getValidIpsAll()
    {
        return array_merge($this->getValidIpsV4(), $this->getValidIpsV6());
    }

    /**
     * @dataProvider getInvalidIpsV4
     */
    public function testInvalidIpsV4($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Ip(array(
            'version' => Ip::V4,
        ))));
    }

    public function getInvalidIpsV4()
    {
        return array(
            array('0'),
            array('0.0'),
            array('0.0.0'),
            array('256.0.0.0'),
            array('0.256.0.0'),
            array('0.0.256.0'),
            array('0.0.0.256'),
            array('-1.0.0.0'),
            array('foobar'),
        );
    }

    /**
     * @dataProvider getInvalidIpsV6
     */
    public function testInvalidIpsV6($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Ip(array(
            'version' => Ip::V6,
        ))));
    }

    public function getInvalidIpsV6()
    {
        return array(
            array('z001:0db8:85a3:0000:0000:8a2e:0370:7334'),
            array('fe80'),
            array('fe80:8329'),
            array('fe80:::202:b3ff:fe1e:8329'),
            array('fe80::202:b3ff::fe1e:8329'),
            // IPv4 mapped to IPv6
            array('2001:0db8:85a3:0000:0000:8a2e:0370:0.0.0.0'),
            array('::0.0'),
            array('::0.0.0'),
            array('::256.0.0.0'),
            array('::0.256.0.0'),
            array('::0.0.256.0'),
            array('::0.0.0.256'),
        );
    }

    /**
     * @dataProvider getInvalidIpsAll
     */
    public function testInvalidIpsAll($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Ip(array(
            'version' => Ip::ALL,
        ))));
    }

    public function getInvalidIpsAll()
    {
        return array_merge($this->getInvalidIpsV4(), $this->getInvalidIpsV6());
    }

    public function testMessageIsSet()
    {
        $constraint = new Ip(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}