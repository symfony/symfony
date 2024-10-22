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

use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IpValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): IpValidator
    {
        return new IpValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Ip());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Ip());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Ip());
    }

    public function testInvalidValidatorVersion()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new Ip(version: 666);
    }

    /**
     * @dataProvider getValidIpsV4
     */
    public function testValidIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4));

        $this->assertNoViolation();
    }

    public static function getValidIpsV4()
    {
        return [
            ['0.0.0.0'],
            ['10.0.0.0'],
            ['123.45.67.178'],
            ['172.16.0.0'],
            ['192.168.1.0'],
            ['224.0.0.1'],
            ['255.255.255.255'],
            ['127.0.0.0'],
        ];
    }

    /**
     * @dataProvider getValidIpsV4WithWhitespaces
     */
    public function testValidIpsV4WithWhitespaces($ip)
    {
        $this->validator->validate($ip, new Ip(
            version: Ip::V4,
            normalizer: 'trim',
        ));

        $this->assertNoViolation();
    }

    public function testValidIpV6WithWhitespacesNamed()
    {
        $this->validator->validate(
            "\n\t2001:0db8:85a3:0000:0000:8a2e:0370:7334\r\n",
            new Ip(version: Ip::V6, normalizer: 'trim')
        );

        $this->assertNoViolation();
    }

    public static function getValidIpsV4WithWhitespaces()
    {
        return [
            ["\x200.0.0.0"],
            ["\x09\x0910.0.0.0"],
            ["123.45.67.178\x0A"],
            ["172.16.0.0\x0D\x0D"],
            ["\x00192.168.1.0\x00"],
            ["\x0B\x0B224.0.0.1\x0B\x0B"],
        ];
    }

    /**
     * @dataProvider getValidIpsV6
     */
    public function testValidIpsV6($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V6));

        $this->assertNoViolation();
    }

    public static function getValidIpsV6()
    {
        return [
            ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            ['2001:0DB8:85A3:0000:0000:8A2E:0370:7334'],
            ['2001:0Db8:85a3:0000:0000:8A2e:0370:7334'],
            ['fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'],
            ['fe80:0000:0000:0000:0202:b3ff:fe1e:8329'],
            ['fe80:0:0:0:202:b3ff:fe1e:8329'],
            ['fe80::202:b3ff:fe1e:8329'],
            ['0:0:0:0:0:0:0:0'],
            ['::'],
            ['0::'],
            ['::0'],
            ['0::0'],
            // IPv4 mapped to IPv6
            ['2001:0db8:85a3:0000:0000:8a2e:0.0.0.0'],
            ['::0.0.0.0'],
            ['::255.255.255.255'],
            ['::123.45.67.178'],
        ];
    }

    /**
     * @dataProvider getValidIpsAll
     */
    public function testValidIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::ALL));

        $this->assertNoViolation();
    }

    public static function getValidIpsAll()
    {
        return array_merge(self::getValidIpsV4(), self::getValidIpsV6());
    }

    /**
     * @dataProvider getInvalidIpsV4
     */
    public function testInvalidIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidPublicIpsV4
     */
    public function testInvalidNoPublicIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_NO_PUBLIC,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getValidPublicIpsV4()
    {
        return [
            ['8.0.0.0'],
            ['90.0.0.0'],
            ['110.0.0.110'],
        ];
    }

    public static function getInvalidIpsV4()
    {
        return [
            ['0'],
            ['0.0'],
            ['0.0.0'],
            ['256.0.0.0'],
            ['0.256.0.0'],
            ['0.0.256.0'],
            ['0.0.0.256'],
            ['-1.0.0.0'],
            ['foobar'],
        ];
    }

    /**
     * @dataProvider getValidPrivateIpsV4
     */
    public function testValidPrivateIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_ONLY_PRIVATE));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidPrivateIpsV4
     */
    public function testInvalidPrivateIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_NO_PRIVATE,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidPrivateIpsV4
     */
    public function testInvalidOnlyPrivateIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_ONLY_PRIVATE,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getValidPrivateIpsV4()
    {
        return [
            ['10.0.0.0'],
            ['172.16.0.0'],
            ['192.168.1.0'],
        ];
    }

    public static function getInvalidPrivateIpsV4()
    {
        return array_merge(self::getValidPublicIpsV4(), self::getValidReservedIpsV4());
    }

    /**
     * @dataProvider getValidReservedIpsV4
     */
    public function testValidReservedIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_ONLY_RESERVED));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidReservedIpsV4
     */
    public function testInvalidReservedIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_NO_RESERVED,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidReservedIpsV4
     */
    public function testInvalidOnlyReservedIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_ONLY_RESERVED,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getValidReservedIpsV4()
    {
        return [
            ['0.0.0.0'],
            ['240.0.0.1'],
            ['255.255.255.255'],
        ];
    }

    public static function getInvalidReservedIpsV4()
    {
        return array_merge(self::getValidPublicIpsV4(), self::getValidPrivateIpsV4());
    }

    /**
     * @dataProvider getInvalidPublicIpsV4
     */
    public function testInvalidPublicIpsV4($ip)
    {
        $constraint = new Ip(
            version: Ip::V4_ONLY_PUBLIC,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsV4()
    {
        return array_merge(self::getValidPrivateIpsV4(), self::getValidReservedIpsV4());
    }

    /**
     * @dataProvider getInvalidIpsV6
     */
    public function testInvalidIpsV6($ip)
    {
        $constraint = new Ip(
            version: Ip::V6,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidIpsV6()
    {
        return [
            ['z001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            ['fe80'],
            ['fe80:8329'],
            ['fe80:::202:b3ff:fe1e:8329'],
            ['fe80::202:b3ff::fe1e:8329'],
            // IPv4 mapped to IPv6
            ['2001:0db8:85a3:0000:0000:8a2e:0370:0.0.0.0'],
            ['::0.0'],
            ['::0.0.0'],
            ['::256.0.0.0'],
            ['::0.256.0.0'],
            ['::0.0.256.0'],
            ['::0.0.0.256'],
        ];
    }

    /**
     * @dataProvider getInvalidPrivateIpsV6
     */
    public function testInvalidPrivateIpsV6($ip)
    {
        $constraint = new Ip(
            version: Ip::V6_NO_PRIVATE,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPrivateIpsV6()
    {
        return [
            ['fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'],
        ];
    }

    /**
     * @dataProvider getInvalidReservedIpsV6
     */
    public function testInvalidReservedIpsV6($ip)
    {
        $constraint = new Ip(
            version: Ip::V6_NO_RESERVED,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidReservedIpsV6()
    {
        // Quoting after official filter documentation:
        // "FILTER_FLAG_NO_RES_RANGE = This flag does not apply to IPv6 addresses."
        // Full description: https://php.net/filter.filters.flags
        return self::getInvalidIpsV6();
    }

    /**
     * @dataProvider getInvalidPublicIpsV6
     */
    public function testInvalidPublicIpsV6($ip)
    {
        $constraint = new Ip(
            version: Ip::V6_ONLY_PUBLIC,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsV6()
    {
        return array_merge(self::getInvalidPrivateIpsV6(), self::getInvalidReservedIpsV6());
    }

    /**
     * @dataProvider getInvalidIpsAll
     */
    public function testInvalidIpsAll($ip)
    {
        $constraint = new Ip(
            version: Ip::ALL,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidIpsAll()
    {
        return array_merge(self::getInvalidIpsV4(), self::getInvalidIpsV6());
    }

    /**
     * @dataProvider getInvalidPrivateIpsAll
     */
    public function testInvalidPrivateIpsAll($ip)
    {
        $constraint = new Ip(
            version: Ip::ALL_NO_PRIVATE,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPrivateIpsAll()
    {
        return array_merge(self::getValidPrivateIpsV4(), self::getInvalidPrivateIpsV6());
    }

    /**
     * @dataProvider getInvalidReservedIpsAll
     */
    public function testInvalidReservedIpsAll($ip)
    {
        $constraint = new Ip(
            version: Ip::ALL_NO_RESERVED,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidReservedIpsAll()
    {
        return array_merge(self::getValidReservedIpsV4(), self::getInvalidReservedIpsV6());
    }

    /**
     * @dataProvider getInvalidPublicIpsAll
     */
    public function testInvalidPublicIpsAll($ip)
    {
        $constraint = new Ip(
            version: Ip::ALL_ONLY_PUBLIC,
            message: 'myMessage',
        );

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsAll()
    {
        return array_merge(self::getInvalidPublicIpsV4(), self::getInvalidPublicIpsV6());
    }
}
