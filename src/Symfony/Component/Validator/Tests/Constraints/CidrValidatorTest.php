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

use Symfony\Component\Validator\Constraints\Cidr;
use Symfony\Component\Validator\Constraints\CidrValidator;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class CidrValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CidrValidator
    {
        return new CidrValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Cidr());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Cidr());

        $this->assertNoViolation();
    }

    public function testInvalidConstraint()
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('neko', new NotNull());
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(123456, new Cidr());
    }

    /**
     * @dataProvider getWithInvalidNetmask
     */
    public function testInvalidNetmask(string $cidr)
    {
        $this->validator->validate($cidr, new Cidr());

        $this
            ->buildViolation('This value is not a valid CIDR notation.')
            ->setCode(Cidr::INVALID_CIDR_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getWithInvalidIps
     */
    public function testInvalidIpValue(string $cidr)
    {
        $this->validator->validate($cidr, new Cidr());

        $this
            ->buildViolation('This value is not a valid CIDR notation.')
            ->setCode(Cidr::INVALID_CIDR_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValid
     */
    public function testValidCidr(string $cidr, string $version)
    {
        $this->validator->validate($cidr, new Cidr(['version' => $version]));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getWithInvalidMasksAndIps
     */
    public function testInvalidIpAddressAndNetmask(string $cidr)
    {
        $this->validator->validate($cidr, new Cidr());
        $this
            ->buildViolation('This value is not a valid CIDR notation.')
            ->setCode(Cidr::INVALID_CIDR_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getOutOfRangeNetmask
     */
    public function testOutOfRangeNetmask(string $cidr, string $version = null, int $min = null, int $max = null)
    {
        $cidrConstraint = new Cidr([
            'version' => $version,
            'netmaskMin' => $min,
            'netmaskMax' => $max,
        ]);
        $this->validator->validate($cidr, $cidrConstraint);

        $this
            ->buildViolation('The value of the netmask should be between {{ min }} and {{ max }}.')
            ->setParameter('{{ min }}', $cidrConstraint->netmaskMin)
            ->setParameter('{{ max }}', $cidrConstraint->netmaskMax)
            ->setCode(Cidr::OUT_OF_RANGE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getWithWrongVersion
     */
    public function testWrongVersion(string $cidr, string $version)
    {
        $this->validator->validate($cidr, new Cidr(['version' => $version]));

        $this
            ->buildViolation('This value is not a valid CIDR notation.')
            ->setCode(Cidr::INVALID_CIDR_ERROR)
            ->assertRaised();
    }

    public static function getWithInvalidIps(): array
    {
        return [
            ['0/20'],
            ['0.0/20'],
            ['0.0.0/20'],
            ['256.0.0.0/20'],
            ['0.256.0.0/21'],
            ['0.0.256.0/22'],
            ['0.0.0.256/30'],
            ['-1.0.0.0/15'],
            ['foobar/10'],
            ['z001:0db8:85a3:0000:0000:8a2e:0370:7334/20'],
            ['fe80/100'],
            ['fe80:8329/15'],
            ['fe80:::202:b3ff:fe1e:8329/128'],
            ['fe80::202:b3ff::fe1e:8329/48'],
            ['2001:0db8:85a3:0000:0000:8a2e:0370:0.0.0.0/32'],
            ['::0.0/32'],
            ['::0.0.0/32'],
            ['::256.0.0.0/32'],
            ['::0.256.0.0/32'],
            ['::0.0.256.0/32'],
            ['::0.0.0.256/32'],
            ['/32'],
            ['/128'],
        ];
    }

    public static function getValid(): array
    {
        return [
            ['127.0.0.0/32', Ip::ALL],
            ['0.0.0.0/32', Ip::V4],
            ['10.0.0.0/24', Ip::V4],
            ['123.45.67.178/20', Ip::V4],
            ['172.16.0.0/12', Ip::V4],
            ['192.168.1.0/25', Ip::V4],
            ['224.0.0.1/10', Ip::V4],
            ['255.255.255.255/20', Ip::V4],
            ['127.0.0.0/32', Ip::V4],
            ['2001:0db8:85a3:0000:0000:8a2e:0370:7334/128', Ip::V6],
            ['2001:0DB8:85A3:0000:0000:8A2E:0370:7334/128', Ip::V6],
            ['2001:0Db8:85a3:0000:0000:8A2e:0370:7334/32', Ip::V6],
            ['fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c/28', Ip::V6],
            ['fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c/55', Ip::V6],
            ['fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334/60', Ip::V6],
            ['fe80:0000:0000:0000:0202:b3ff:fe1e:8329/20', Ip::V6],
            ['fe80:0:0:0:202:b3ff:fe1e:8329/4', Ip::V6],
            ['fe80::202:b3ff:fe1e:8329/0', Ip::V6],
            ['0:0:0:0:0:0:0:0/1', Ip::V6],
            ['::/20', Ip::V6],
            ['0::/120', Ip::V6],
            ['::0/128', Ip::V6],
            ['0::0/56', Ip::V6],
            ['2001:0db8:85a3:0000:0000:8a2e:0.0.0.0/128', Ip::V6],
            ['::0.0.0.0/128', Ip::V6],
            ['::255.255.255.255/32', Ip::V6],
            ['::123.45.67.178/120', Ip::V6],
            ['::123.45.67.178/120', Ip::ALL],
        ];
    }

    public static function getWithInvalidNetmask(): array
    {
        return [
            ['192.168.1.0/-1'],
            ['0.0.0.0/foobar'],
            ['10.0.0.0/128'],
            ['123.45.67.178/aaa'],
            ['172.16.0.0//'],
            ['255.255.255.255/1/4'],
            ['224.0.0.1'],
            ['127.0.0.0/28c'],
            ['2001:0Db8:85a3:0000:0000:8A2e:0370:7334/28a'],
            ['fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c/neko'],
            ['fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c/-8amba'],
            ['fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334/-1aa'],
            ['fe80:0000:0000:0000:0202:b3ff:fe1e:8329/11*'],
        ];
    }

    public static function getWithInvalidMasksAndIps(): array
    {
        return [
            ['0.0.0.0/foobar'],
            ['10.0.0.0/128'],
            ['123.45.67.178/aaa'],
            ['172.16.0.0//'],
            ['172.16.0.0/a/'],
            ['172.16.0.0/1/'],
            ['fe80/neko'],
            ['fe80:8329/-8'],
            ['fe80:::202:b3ff:fe1e:8329//'],
            ['fe80::202:b3ff::fe1e:8329/1/'],
            ['::0.0.0/a/'],
            ['::256.0.0.0/-1aa'],
            ['::0.256.0.0/1b'],
        ];
    }

    public static function getOutOfRangeNetmask(): array
    {
        return [
            ['10.0.0.0/24', Ip::V4, 10, 20],
            ['2001:0DB8:85A3:0000:0000:8A2E:0370:7334/24', Ip::V6, 10, 20],
        ];
    }

    public static function getWithWrongVersion(): array
    {
        return [
            ['2001:0db8:85a3:0000:0000:8a2e:0370:7334/12', Ip::V4],
            ['0.0.0.0/31', Ip::V6],
            ['10.0.0.0/24', Ip::V6],
            ['2001:0db8:85a3:0000:0000:8a2e:0370:7334/13', Ip::V4],
        ];
    }
}
