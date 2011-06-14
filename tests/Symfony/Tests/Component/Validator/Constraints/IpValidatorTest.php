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

use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;

class IpValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new IpValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
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

    public function testInvalidValidatorVersion()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $ip = new Ip(array(
            'version' => 666,
        ));
    }

    /**
     * @dataProvider getValidIpsV4
     */
    public function testValidIpsV4($ip)
    {
        $this->assertTrue($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V4,
        ))));
    }

    public function getValidIpsV4()
    {
        return array(
            array('0.0.0.0'),
            array('10.0.0.0'),
            array('123.45.67.178'),
            array('172.16.0.0'),
            array('192.168.1.0'),
            array('224.0.0.1'),
            array('255.255.255.255'),
            array('127.0.0.0'),
        );
    }

    /**
     * @dataProvider getValidIpsV6
     */
    public function testValidIpsV6($ip)
    {
        $this->assertTrue($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V6,
        ))));
    }

    public function getValidIpsV6()
    {
        return array(
            array('2001:0db8:85a3:0000:0000:8a2e:0370:7334'),
            array('2001:0DB8:85A3:0000:0000:8A2E:0370:7334'),
            array('2001:0Db8:85a3:0000:0000:8A2e:0370:7334'),
            array('fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'),
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
    public function testValidIpsAll($ip)
    {
        $this->assertTrue($this->validator->isValid($ip, new Ip(array(
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
    public function testInvalidIpsV4($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
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
     * @dataProvider getInvalidPrivateIpsV4
     */
    public function testInvalidPrivateIpsV4($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V4_NO_PRIV,
        ))));
    }

    public function getInvalidPrivateIpsV4()
    {
        return array(
            array('10.0.0.0'),
            array('172.16.0.0'),
            array('192.168.1.0'),
        );
    }

    /**
     * @dataProvider getInvalidReservedIpsV4
     */
    public function testInvalidReservedIpsV4($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V4_NO_RES,
        ))));
    }

    public function getInvalidReservedIpsV4()
    {
        return array(
            array('0.0.0.0'),
            array('224.0.0.1'),
            array('255.255.255.255'),
        );
    }

    /**
     * @dataProvider getInvalidPublicIpsV4
     */
    public function testInvalidPublicIpsV4($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V4_ONLY_PUBLIC,
        ))));
    }

    public function getInvalidPublicIpsV4()
    {
        return array_merge($this->getInvalidPrivateIpsV4(), $this->getInvalidReservedIpsV4());
    }

    /**
     * @dataProvider getInvalidIpsV6
     */
    public function testInvalidIpsV6($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
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
     * @dataProvider getInvalidPrivateIpsV6
     */
    public function testInvalidPrivateIpsV6($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V6_NO_PRIV,
        ))));
    }

    public function getInvalidPrivateIpsV6()
    {
        return array(
            array('fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'),
        );
    }

    /**
     * @dataProvider getInvalidReservedIpsV6
     */
    public function testInvalidReservedIpsV6($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V6_NO_RES,
        ))));
    }

    public function getInvalidReservedIpsV6()
    {
        // Quoting after official filter documentation:
        // "FILTER_FLAG_NO_RES_RANGE = This flag does not apply to IPv6 addresses."
        // Full description: http://php.net/manual/en/filter.filters.flags.php
        return $this->getInvalidIpsV6();
    }

    /**
     * @dataProvider getInvalidPublicIpsV6
     */
    public function testInvalidPublicIpsV6($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::V6_ONLY_PUBLIC,
        ))));
    }

    public function getInvalidPublicIpsV6()
    {
        return array_merge($this->getInvalidPrivateIpsV6(), $this->getInvalidReservedIpsV6());
    }

    /**
     * @dataProvider getInvalidIpsAll
     */
    public function testInvalidIpsAll($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::ALL,
        ))));
    }

    public function getInvalidIpsAll()
    {
        return array_merge($this->getInvalidIpsV4(), $this->getInvalidIpsV6());
    }

    /**
     * @dataProvider getInvalidPrivateIpsAll
     */
    public function testInvalidPrivateIpsAll($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::ALL_NO_PRIV,
        ))));
    }

    public function getInvalidPrivateIpsAll()
    {
        return array_merge($this->getInvalidPrivateIpsV4(), $this->getInvalidPrivateIpsV6());
    }

    /**
     * @dataProvider getInvalidReservedIpsAll
     */
    public function testInvalidReservedIpsAll($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::ALL_NO_RES,
        ))));
    }

    public function getInvalidReservedIpsAll()
    {
        return array_merge($this->getInvalidReservedIpsV4(), $this->getInvalidReservedIpsV6());
    }

    /**
     * @dataProvider getInvalidPublicIpsAll
     */
    public function testInvalidPublicIpsAll($ip)
    {
        $this->assertFalse($this->validator->isValid($ip, new Ip(array(
            'version' => Ip::ALL_ONLY_PUBLIC,
        ))));
    }

    public function getInvalidPublicIpsAll()
    {
        return array_merge($this->getInvalidPublicIpsV4(), $this->getInvalidPublicIpsV6());
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
