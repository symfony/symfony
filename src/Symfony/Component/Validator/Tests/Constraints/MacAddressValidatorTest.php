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
        ];
    }

    /**
     * @dataProvider getValidMacsWithWhitespaces
     */
    public function testValidMacsWithWhitespaces($mac)
    {
        $this->validator->validate($mac, new MacAddress([
            'normalizer' => 'trim',
        ]));

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
        $constraint = new MacAddress([
            'message' => 'myMessage',
        ]);

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
