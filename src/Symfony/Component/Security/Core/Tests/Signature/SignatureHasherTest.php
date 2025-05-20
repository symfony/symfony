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
use Symfony\Component\Security\Core\Tests\Fixtures\DummyHasher;
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
    public function testComputeSignatureHash(mixed $arbitraryValue, array $signatureProperties, bool $useDummyHasher, string $expectedHash)
    {
        $user = new DummyUserWithProperties(self::USER_IDENTIFIER, $arbitraryValue);

        $signatureHasher = new SignatureHasher(
            new PropertyAccessor(),
            $signatureProperties,
            self::SECRET,
            ...($useDummyHasher ? ['hasher' => new DummyHasher()] : []),
        );

        $actualHash = $signatureHasher->computeSignatureHash($user, self::EXPIRES);
        $this->assertSame($expectedHash, $actualHash);
    }

    public function providerComputeSignatureHash(): array
    {
        return [
            // test with dummy hasher
            ['someValue', [], true, 'HMAC(HASH():1234567890:username,s3cr3t)HASH()'],
            ['someValue', ['identifier'], true, 'HMAC(HASH(:username):1234567890:username,s3cr3t)HASH(:username)'],
            ['someValue', ['arbitraryValue'], true, 'HMAC(HASH(:someValue):1234567890:username,s3cr3t)HASH(:someValue)'],
            ['someValue', ['identifier', 'arbitraryValue'], true, 'HMAC(HASH(:username:someValue):1234567890:username,s3cr3t)HASH(:username:someValue)'],
            [null, ['arbitraryValue'], true, 'HMAC(HASH(:):1234567890:username,s3cr3t)HASH(:)'],
            [false, ['arbitraryValue'], true, 'HMAC(HASH(:):1234567890:username,s3cr3t)HASH(:)'],
            [true, ['arbitraryValue'], true, 'HMAC(HASH(:1):1234567890:username,s3cr3t)HASH(:1)'],
            [123, ['arbitraryValue'], true, 'HMAC(HASH(:123):1234567890:username,s3cr3t)HASH(:123)'],
            [123.456, ['arbitraryValue'], true, 'HMAC(HASH(:123.456):1234567890:username,s3cr3t)HASH(:123.456)'],
            [['foo', 'bar', 'baz'], ['arbitraryValue[0]', 'arbitraryValue[1]'], true, 'HMAC(HASH(:foo:bar):1234567890:username,s3cr3t)HASH(:foo:bar)'],
            [['a' => 'foo', 'b' => 'bar', 'c' => 'baz'], ['arbitraryValue[b]', 'arbitraryValue[c]'], true, 'HMAC(HASH(:bar:baz):1234567890:username,s3cr3t)HASH(:bar:baz)'],
            [(object) ['a' => 'foo', 'b' => 'bar', 'c' => 'baz'], ['arbitraryValue.c', 'arbitraryValue.a', 'arbitraryValue.b'], true, 'HMAC(HASH(:baz:foo:bar):1234567890:username,s3cr3t)HASH(:baz:foo:bar)'],
            [IntBackedEnum::FOO, ['arbitraryValue'], true, 'HMAC(HASH(:0):1234567890:username,s3cr3t)HASH(:0)'],
            [IntBackedEnum::BAR, ['arbitraryValue'], true, 'HMAC(HASH(:1):1234567890:username,s3cr3t)HASH(:1)'],
            [StringBackedEnum::FOO, ['arbitraryValue'], true, 'HMAC(HASH(:Foo):1234567890:username,s3cr3t)HASH(:Foo)'],
            [StringBackedEnum::BAR, ['arbitraryValue'], true, 'HMAC(HASH(:Bar):1234567890:username,s3cr3t)HASH(:Bar)'],
            // test with actual hasher
            ['someValue', ['identifier'], false, 'a5YdlgWCmg7usTIoClr2uFFOEfO_XY1f7rCAoswmstE~blXCO2vRdOtJA_aCJPBaNpRpsaS957uvosMktnrI6wY~'],
            ['someValue', ['identifier', 'arbitraryValue'], false, 'myxvvho8WkMuOcMMeuRlZQFe58TNDQFgDrVFb8SZ50g~iJ4d_Agaa0AaCHZinVr_zZCgR2nSZgokvXIkv7ne1b4~'],
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
