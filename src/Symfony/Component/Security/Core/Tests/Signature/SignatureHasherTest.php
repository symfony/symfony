<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Security\Core\Tests\Signature;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\Tests\Fixtures\DummyUserWithProperties;
use Symfony\Component\Security\Core\Tests\Fixtures\Enum\IntBackedEnum;
use Symfony\Component\Security\Core\Tests\Fixtures\Enum\NonBackedEnum;
use Symfony\Component\Security\Core\Tests\Fixtures\Enum\StringBackedEnum;

class SignatureHasherTest extends TestCase
{
    private const SECRET = 's3cr3t';
    private const EXPIRES = 1234567890;
    private const USER_IDENTIFIER = 'username';

    /**
     * @dataProvider providerComputeSignatureHash
     */
    public function testComputeSignatureHash(mixed $arbitraryValue, array $signatureProperties, string $expectedHash)
    {
        $user = new DummyUserWithProperties(self::USER_IDENTIFIER, $arbitraryValue);

        $signatureHasher = new SignatureHasher(
            new PropertyAccessor(),
            $signatureProperties,
            self::SECRET,
        );

        $actualHash = $signatureHasher->computeSignatureHash($user, self::EXPIRES);
        $this->assertSame($expectedHash, $actualHash);
    }

    public function providerComputeSignatureHash(): array
    {
        return [
            ['someValue', [], 'G8FxuQ7xlU0L132MkzxZu5KRob7AQBcxzpaDAUC6b54~47DEQpj8HBSa-_TImW-5JCeuQeRkm5NMpJWZG3hSuFU~'],
            ['someValue', ['identifier'], 'a5YdlgWCmg7usTIoClr2uFFOEfO_XY1f7rCAoswmstE~blXCO2vRdOtJA_aCJPBaNpRpsaS957uvosMktnrI6wY~'],
            ['someValue', ['arbitraryValue'], 'kfMzZgYYD1oeqSSW7m0k94VuRvS7LeHcKq-PKU8WD7k~0nuV8X2IlHqxDdPRNOLP-wp_v2KdVL9dNYJ0_557fGc~'],
            ['someValue', ['identifier', 'arbitraryValue'], 'myxvvho8WkMuOcMMeuRlZQFe58TNDQFgDrVFb8SZ50g~iJ4d_Agaa0AaCHZinVr_zZCgR2nSZgokvXIkv7ne1b4~'],
            [null, ['arbitraryValue'], 'RMzJFvIb5BMTbyJb_VZwuKEchdxH8bA00ci1kYVJgEc~56wHhmaOD_DwK2K9BPRf9jb9gttjsRBGAcl13ABfOmc~'],
            [false, ['arbitraryValue'], 'RMzJFvIb5BMTbyJb_VZwuKEchdxH8bA00ci1kYVJgEc~56wHhmaOD_DwK2K9BPRf9jb9gttjsRBGAcl13ABfOmc~'],
            [true, ['arbitraryValue'], 'otQtMUGvEkdfOynddQ5WvoRldq8honHbEb1HcM8UR8I~D60nAF03Qti0aU2B2Z5nVMOl_evP1uYHUVXRtHzgea0~'],
            [123, ['arbitraryValue'], 'FTEV8ag4ndPfukNSOgsQtT7M7V0_Ab0Q6xnpqbWNhZ0~wWwB3p8Bp_5t2VDeCwTuCFHI-3gDxIPgP-C9ZZHEzaY~'],
            [123.456, ['arbitraryValue'], 'bmSC3nku_rZA6KjVLJgEZhfx7GOhrQDfxaAubuncdII~nriT5yCE-wjOnuk-yycrgCtch4raCAhuuVeja7X6N7k~'],
            [['foo', 'bar', 'baz'], ['arbitraryValue[0]', 'arbitraryValue[1]'], 'RRujHUR7iidZDEMkSHXEGvyaTCA5C4m0n5H200gqLxw~Zt46jI-2GYxtNTzeTcOoq2_jxow7h7PuI2C0qp7-H28~'],
            [['a' => 'foo', 'b' => 'bar', 'c' => 'baz'], ['arbitraryValue[b]', 'arbitraryValue[c]'], 'J6hgo51Cax5NBrtIH1JpZSuLgNXZ0G24dN1v7WGFyqg~fePV3ZmKYu5tz49IF6nlmAwhchNOkkGMCEIFapsOVYw~'],
            [(object) ['a' => 'foo', 'b' => 'bar', 'c' => 'baz'], ['arbitraryValue.c', 'arbitraryValue.a', 'arbitraryValue.b'], 'sXS_9yKjlog_OhI6oI5I0oG-M-A8TCaHhE7yhuUfhQU~6bdXP2SGmxK_WmYcg_mBySv40I_aKpbySb78NfJLQVA~'],
            [IntBackedEnum::FOO, ['arbitraryValue'], '3CtZMZJ-YGRX6xUInO9pn3Re0oyojM57L-7CDWzY51k~BtLamVEszxpZWe7HwvgW9MB2XJfepVW7yEWydNHdr2k~'],
            [IntBackedEnum::BAR, ['arbitraryValue'], 'otQtMUGvEkdfOynddQ5WvoRldq8honHbEb1HcM8UR8I~D60nAF03Qti0aU2B2Z5nVMOl_evP1uYHUVXRtHzgea0~'],
            [StringBackedEnum::FOO, ['arbitraryValue'], 'H0kSG0c8UDJswEoMdkvpPvksK5yL7XO-UOPT93H-1Xo~JFWxxE-9gSaxRbu2nBFRqYShfEIp87D6nFTv3Qcidas~'],
            [StringBackedEnum::BAR, ['arbitraryValue'], '5UzmukUROyA6-v0VEac9Tc2Wz1HiV_nbqbWraFXbIu0~ZYCW1TBi_AyktUQPgz9tP3utfjTx-lv2Ea45T-0o4w8~'],
        ];
    }

    /**
     * @dataProvider providerComputeSignatureHashFailure
     */
    public function testComputeSignatureHashFailure(mixed $arbitraryValue, array $signatureProperties, string $expectedException, string $expectedExceptionMessage)
    {
        $user = new DummyUserWithProperties(self::USER_IDENTIFIER, $arbitraryValue);

        $signatureHasher = new SignatureHasher(
            new PropertyAccessor(),
            $signatureProperties,
            self::SECRET,
        );

        $this->expectException($expectedException);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $signatureHasher->computeSignatureHash($user, self::EXPIRES);
    }

    public function providerComputeSignatureHashFailure(): array
    {
        return [
            [
                NonBackedEnum::FOO,
                ['arbitraryValue'],
                InvalidArgumentException::class,
                'The property path "arbitraryValue" on the user object "'.DummyUserWithProperties::class.'" ' .
                'must return a value that can be cast to a string, but "'.NonBackedEnum::class.'" was returned.',
            ], [
                (object) ['foo' => 'bar'],
                ['arbitraryValue.bar'],
                NoSuchPropertyException::class,
                'Can\'t get a way to read the property "bar" in class "stdClass"',
            ],
        ];
    }
}
