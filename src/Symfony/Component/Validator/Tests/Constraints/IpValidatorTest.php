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

use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('getValidIpsV4')]
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

    #[DataProvider('getValidIpsV4WithWhitespaces')]
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

    #[DataProvider('getValidIpsV6')]
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

    #[DataProvider('getValidIpsAll')]
    public function testValidIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::ALL));

        $this->assertNoViolation();
    }

    public static function getValidIpsAll()
    {
        return array_merge(self::getValidIpsV4(), self::getValidIpsV6());
    }

    #[DataProvider('getValidPublicIpsV4')]
    public function testValidPublicIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_ONLY_PUBLIC));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidPublicIpsV4')]
    public function testValidNonPrivateIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_NO_PRIV));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidPublicIpsV4')]
    public function testValidNonReservedIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_NO_RES));

        $this->assertNoViolation();
    }

    public static function getValidPublicIpsV4()
    {
        return [
            ['8.8.8.8'],
            ['1.1.1.1'],
            ['123.45.67.178'],
            ['224.0.0.1'],
            // just outside the non-public subnets
            ['100.63.255.255'],
            ['100.128.0.1'],
            ['172.32.0.1'],
            ['198.17.255.255'],
            ['198.20.0.1'],
        ];
    }

    #[DataProvider('getValidPublicIpsV6')]
    public function testValidPublicIpsV6($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V6_ONLY_PUBLIC));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidPublicIpsAll')]
    public function testValidPublicIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::ALL_ONLY_PUBLIC));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidPublicIpsAll')]
    public function testValidNonPrivateIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::ALL_NO_PRIV));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidPublicIpsAll')]
    public function testValidNonReservedIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::ALL_NO_RES));

        $this->assertNoViolation();
    }

    public static function getValidPublicIpsV6()
    {
        return [
            ['2a01:198::1'],
            ['2606:4700:4700::1111'],
            ['2001:4860:4860::8888'],
            // just outside the non-public subnets, including both edges of the
            // ones whose prefix length is not a multiple of 8
            ['fbff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['fe00::1'],
            ['fe7f:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['fec0::1'],
            ['::1:0:0:0'],
            ['2001:1::1'],
            ['2001:1:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['2001:2:1::1'],
            ['2001:db7:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['2001:db9::1'],
            ['2001:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['2003::1'],
            ['64:ff9b::1:0:0'],
            ['64:ff9b:2::1'],
        ];
    }

    public static function getValidPublicIpsAll()
    {
        return array_merge(self::getValidPublicIpsV4(), self::getValidPublicIpsV6());
    }

    #[DataProvider('getInvalidIpsV4')]
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

    #[DataProvider('getValidPublicIpsV4')]
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

    #[DataProvider('getValidPrivateIpsV4')]
    public function testValidPrivateIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_ONLY_PRIVATE));

        $this->assertNoViolation();
    }

    #[DataProvider('getNonPublicIpsV4')]
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

    #[DataProvider('getInvalidPrivateIpsV4')]
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

    public static function getNonPublicIpsV4()
    {
        return [
            // 0.0.0.0/8 "this" network, including the unspecified address
            ['0.0.0.0'],
            ['0.1.2.3'],
            ['0.255.255.255'],
            // 10.0.0.0/8 private use
            ['10.0.0.0'],
            ['10.255.255.254'],
            // 100.64.0.0/10 shared address space
            ['100.64.0.1'],
            ['100.127.255.254'],
            // 127.0.0.0/8 loopback
            ['127.0.0.1'],
            ['127.255.255.254'],
            // 169.254.0.0/16 link local
            ['169.254.0.1'],
            ['169.254.255.254'],
            // 172.16.0.0/12 private use
            ['172.16.0.0'],
            ['172.31.255.254'],
            // 192.0.2.0/24 documentation TEST-NET-1
            ['192.0.2.1'],
            ['192.0.2.254'],
            // 192.168.0.0/16 private use
            ['192.168.1.0'],
            ['192.168.255.254'],
            // 198.18.0.0/15 benchmarking
            ['198.18.0.1'],
            ['198.19.255.254'],
            // 198.51.100.0/24 documentation TEST-NET-2
            ['198.51.100.1'],
            ['198.51.100.254'],
            // 203.0.113.0/24 documentation TEST-NET-3
            ['203.0.113.1'],
            ['203.0.113.254'],
            // 240.0.0.0/4 reserved, including the broadcast address
            ['240.0.0.1'],
            ['255.255.255.255'],
        ];
    }

    public static function getInvalidPrivateIpsV4()
    {
        return array_merge(self::getValidPublicIpsV4(), self::getValidReservedIpsV4());
    }

    #[DataProvider('getValidReservedIpsV4')]
    public function testValidReservedIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(version: Ip::V4_ONLY_RESERVED));

        $this->assertNoViolation();
    }

    #[DataProvider('getNonPublicIpsV4')]
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

    #[DataProvider('getInvalidReservedIpsV4')]
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

    #[DataProvider('getInvalidPublicIpsV4')]
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
        return self::getNonPublicIpsV4();
    }

    #[DataProvider('getInvalidIpsV6')]
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

    #[DataProvider('getInvalidPrivateIpsV6')]
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
        return array_merge([
            ['fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'],
            ['fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'],
        ], self::getNonPublicIpsV6());
    }

    public static function getNonPublicIpsV6()
    {
        return [
            // ::1/128 loopback
            ['::1'],
            ['0:0:0:0:0:0:0:1'],
            // fc00::/7 unique local address, both edges included
            ['fc00::'],
            ['fc00::1'],
            ['fd00::1'],
            ['fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            // fe80::/10 link local address, both edges included
            ['fe80::'],
            ['fe80::1'],
            ['febf::1'],
            ['febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            ['fe80:0000:0000:0000:0202:b3ff:fe1e:8329'],
            // ::ffff:0:0/96 IPv4-mapped
            ['::ffff:127.0.0.1'],
            ['::ffff:7f00:1'],
            ['::ffff:10.0.0.1'],
            ['::ffff:0a00:1'],
            ['::ffff:172.16.0.1'],
            ['::ffff:192.168.1.1'],
            ['::ffff:8.8.8.8'],
            ['::ffff:0808:0808'],
            ['0:0:0:0:0:ffff:192.168.1.1'],
            ['0000:0000:0000:0000:0000:ffff:c0a8:0101'],
            ['::ffff:ffff:ffff'],
            // ::/96 IPv4-compatible, including the unspecified address
            ['::'],
            ['::127.0.0.1'],
            ['::7f00:1'],
            ['::10.0.0.1'],
            ['::0a00:1'],
            ['::8.8.8.8'],
            ['::0808:0808'],
            ['::ffff:ffff'],
            // 2002::/16 6to4
            ['2002::'],
            ['2002:7f00:1::'],
            ['2002:808:808::1'],
            ['2002:ffff:ffff:ffff:ffff:ffff:ffff:ffff'],
            // 2001::/32 Teredo
            ['2001::'],
            ['2001:0:1::'],
            ['2001:0000:4136:e378:8000:63bf:3fff:fdd2'],
            ['2001:0:ffff:ffff:ffff:ffff:ffff:ffff'],
            // 2001:db8::/32 documentation
            ['2001:db8::1'],
            ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            ['2001:db8:ffff:ffff:ffff:ffff:ffff:ffff'],
            // 2001:2::/48 benchmarking
            ['2001:2::1'],
            ['2001:0002:0000:1234::1'],
            ['2001:2:0:ffff:ffff:ffff:ffff:ffff'],
            // 64:ff9b::/96 NAT64 well-known prefix
            ['64:ff9b::7f00:1'],
            ['64:ff9b::127.0.0.1'],
            ['64:ff9b::8.8.8.8'],
            ['64:ff9b::ffff:ffff'],
            // 64:ff9b:1::/48 NAT64 local-use prefix
            ['64:ff9b:1::1'],
            ['64:ff9b:1:1234::1'],
            ['64:ff9b:1:ffff:ffff:ffff:ffff:ffff'],
        ];
    }

    #[DataProvider('getInvalidReservedIpsV6')]
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
        return array_merge(self::getInvalidIpsV6(), self::getNonPublicIpsV6());
    }

    #[DataProvider('getInvalidPublicIpsV6')]
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

    #[DataProvider('getInvalidIpsAll')]
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

    #[DataProvider('getInvalidPrivateIpsAll')]
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

    #[DataProvider('getInvalidReservedIpsAll')]
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

    #[DataProvider('getInvalidPublicIpsAll')]
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
