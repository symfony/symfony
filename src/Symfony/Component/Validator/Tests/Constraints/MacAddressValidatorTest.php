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
use Symfony\Component\Validator\Constraints\MacAddress;
use Symfony\Component\Validator\Constraints\MacAddressValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Ninos Ego <me@ninosego.de>
 */
class MacAddressValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): MacAddressValidator
    {
        return new MacAddressValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new MacAddress());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new MacAddress());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new MacAddress());
    }

    public function testInvalidValidatorType()
    {
        $this->expectException(ConstraintDefinitionException::class);
        new MacAddress(type: 666);
    }

    #[DataProvider('getValidMacs')]
    public function testValidMac($mac)
    {
        $this->validator->validate($mac, new MacAddress());

        $this->assertNoViolation();
    }

    #[DataProvider('getNotValidMacs')]
    public function testNotValidMac($mac)
    {
        $this->validator->validate($mac, new MacAddress());

        $this->buildViolation('This value is not a valid MAC address.')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    public static function getValidMacs(): array
    {
        return [
            ['00:00:00:00:00:00'],
            ['00-00-00-00-00-00'],
            ['ff:ff:ff:ff:ff:ff'],
            ['ff-ff-ff-ff-ff-ff'],
            ['FF:FF:FF:FF:FF:FF'],
            ['FF-FF-FF-FF-FF-FF'],
            ['FFFF.FFFF.FFFF'],
        ];
    }

    public static function getNotValidMacs(): array
    {
        return [
            ['00:00:00:00:00'],
            ['00:00:00:00:00:0G'],
            ['GG:GG:GG:GG:GG:GG'],
            ['GG-GG-GG-GG-GG-GG'],
            ['GGGG.GGGG.GGGG'],
        ];
    }

    public static function getValidLocalUnicastMacs(): array
    {
        return [
            ['02:00:00:00:00:00'],
            ['16-00-00-00-00-00'],
            ['2a-00-00-00-00-00'],
            ['3e-00-00-00-00-00'],
            ['3E00.0000.0000'],
        ];
    }

    public static function getValidLocalMulticastMacs(): array
    {
        return [
            ['03:00:00:00:00:00'],
            ['17-00-00-00-00-00'],
            ['2b-00-00-00-00-00'],
            ['3f-00-00-00-00-00'],
            ['3F00.0000.0000'],
        ];
    }

    public static function getValidUniversalUnicastMacs(): array
    {
        return [
            ['00:00:00:00:00:00'],
            ['14-00-00-00-00-00'],
            ['28-00-00-00-00-00'],
            ['3c-00-00-00-00-00'],
            ['3C00.0000.0000'],
        ];
    }

    public static function getValidUniversalMulticastMacs(): array
    {
        return [
            ['01:00:00:00:00:00'],
            ['15-00-00-00-00-00'],
            ['29-00-00-00-00-00'],
            ['3d-00-00-00-00-00'],
            ['3D00.0000.0000'],
        ];
    }

    public static function getValidBroadcastMacs(): array
    {
        return [
            ['ff:ff:ff:ff:ff:ff'],
            ['FF-ff-FF-ff-FF-ff'],
            ['fFff.ffff.fffF'],
        ];
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testValidAllNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::ALL_NO_BROADCAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidBroadcastMacs')]
    public function testInvalidAllNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::ALL_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testValidLocalMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_ALL));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidLocalMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    public function testValidLocalNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_NO_BROADCAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testInvalidLocalNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    public function testValidLocalUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_UNICAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidLocalUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_UNICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testValidLocalMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_MULTICAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidLocalMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_MULTICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    public function testValidLocalMulticastNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_MULTICAST_NO_BROADCAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testInvalidLocalMulticastNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_MULTICAST_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testValidUniversalMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_ALL));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    public function testInvalidUniversalMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidUniversalUnicastMacs')]
    public function testValidUniversalUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_UNICAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidUniversalUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_UNICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testValidUniversalMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_MULTICAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    public function testInvalidUniversalMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_MULTICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    public function testUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNICAST_ALL));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNICAST_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::MULTICAST_ALL));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    public function testInvalidMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::MULTICAST_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testMulticastNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::MULTICAST_NO_BROADCAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidBroadcastMacs')]
    public function testInvalidMulticastNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::MULTICAST_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidBroadcastMacs')]
    public function testBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::BROADCAST));

        $this->assertNoViolation();
    }

    #[DataProvider('getValidLocalUnicastMacs')]
    #[DataProvider('getValidLocalMulticastMacs')]
    #[DataProvider('getValidUniversalUnicastMacs')]
    #[DataProvider('getValidUniversalMulticastMacs')]
    public function testInvalidBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValidMacsWithWhitespaces')]
    public function testValidMacsWithWhitespaces($mac)
    {
        $this->validator->validate($mac, new MacAddress(normalizer: 'trim'));

        $this->assertNoViolation();
    }

    public static function getValidMacsWithWhitespaces(): array
    {
        return [
            ["\x2000:00:00:00:00:00"],
            ["\x09\x0900-00-00-00-00-00"],
            ["ff:ff:ff:ff:ff:ff\x0A"],
            ["ff-ff-ff-ff-ff-ff\x0D\x0D"],
            ["\x00FF:FF:FF:FF:FF:FF\x00"],
            ["\x0B\x0BFF-FF-FF-FF-FF-FF\x0B\x0B"],
        ];
    }

    #[DataProvider('getInvalidMacs')]
    public function testInvalidMacs($mac)
    {
        $constraint = new MacAddress('myMessage');

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    public static function getInvalidMacs(): array
    {
        return [
            ['0'],
            ['00:00'],
            ['00:00:00'],
            ['00:00:00:00'],
            ['00:00:00:00:00'],
            ['00:00:00:00:00:000'],
            ['-00:00:00:00:00:00'],
            ['foobar'],
        ];
    }
}
