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

    /**
     * @dataProvider getValidMacs
     */
    public function testValidMac($mac)
    {
        $this->validator->validate($mac, new MacAddress());

        $this->assertNoViolation();
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

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testValidAllNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::ALL_NO_BROADCAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidBroadcastMacs
     */
    public function testInvalidAllNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::ALL_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testValidLocalMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_ALL));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidLocalMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     */
    public function testValidLocalNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_NO_BROADCAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testInvalidLocalNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     */
    public function testValidLocalUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_UNICAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidLocalUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_UNICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testValidLocalMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_MULTICAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidLocalMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_MULTICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     */
    public function testValidLocalMulticastNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::LOCAL_MULTICAST_NO_BROADCAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testInvalidLocalMulticastNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::LOCAL_MULTICAST_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testValidUniversalMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_ALL));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     */
    public function testInvalidUniversalMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidUniversalUnicastMacs
     */
    public function testValidUniversalUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_UNICAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidUniversalUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_UNICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testValidUniversalMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNIVERSAL_MULTICAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalUnicastMacs
     */
    public function testInvalidUniversalMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNIVERSAL_MULTICAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidUniversalUnicastMacs
     */
    public function testUnicastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::UNICAST_ALL));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidUnicastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::UNICAST_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalMulticastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testMulticastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::MULTICAST_ALL));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidUniversalUnicastMacs
     */
    public function testInvalidMulticastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::MULTICAST_ALL);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testMulticastNoBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::MULTICAST_NO_BROADCAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidBroadcastMacs
     */
    public function testInvalidMulticastNoBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::MULTICAST_NO_BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidBroadcastMacs
     */
    public function testBroadcastMacs($mac)
    {
        $this->validator->validate($mac, new MacAddress(type: MacAddress::BROADCAST));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocalUnicastMacs
     * @dataProvider getValidLocalMulticastMacs
     * @dataProvider getValidUniversalUnicastMacs
     * @dataProvider getValidUniversalMulticastMacs
     */
    public function testInvalidBroadcastMacs($mac)
    {
        $constraint = new MacAddress('myMessage', type: MacAddress::BROADCAST);

        $this->validator->validate($mac, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$mac.'"')
            ->setCode(MacAddress::INVALID_MAC_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidMacsWithWhitespaces
     */
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

    /**
     * @dataProvider getInvalidMacs
     */
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
