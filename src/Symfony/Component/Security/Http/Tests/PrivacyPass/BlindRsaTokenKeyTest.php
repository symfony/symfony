<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenKey;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\PrivateToken;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class BlindRsaTokenKeyTest extends TestCase
{
    public function testTheIdentifierMatchesTheTestVectors()
    {
        $tokenKey = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));

        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($tokenKey->getId()));
    }

    public function testTheTokenKeyParameterMatchesTheTestVectors()
    {
        $tokenKey = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));

        $this->assertSame(PrivacyPassTestVectors::HEADER_TOKEN_KEY_BASE64URL, $tokenKey->toBase64Url());
    }

    #[DataProvider('provideTestVectors')]
    public function testItVerifiesTheAuthenticatorsOfTheTestVectors(string $token)
    {
        $token = PrivateToken::fromBinary(hex2bin($token), new BlindRsaTokenType());
        $tokenKey = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));

        $this->assertTrue($tokenKey->verify($token->getAuthenticatorInput(), $token->authenticator));
    }

    #[DataProvider('provideTestVectors')]
    public function testAnotherKeyDoesNotVerifyTheAuthenticatorsOfTheTestVectors(string $token)
    {
        $token = PrivateToken::fromBinary(hex2bin($token), new BlindRsaTokenType());
        $tokenKey = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::OTHER_TOKEN_KEY));

        $this->assertFalse($tokenKey->verify($token->getAuthenticatorInput(), $token->authenticator));
    }

    public static function provideTestVectors(): iterable
    {
        foreach (PrivacyPassTestVectors::TOKENS as $i => $vector) {
            yield 'RFC 9578 test vector '.($i + 1) => [$vector['token']];
        }
    }

    public function testItRejectsAnAuthenticatorOfAnotherInput()
    {
        $tokenKey = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));
        $first = PrivateToken::fromBinary(hex2bin(PrivacyPassTestVectors::TOKENS[0]['token']), new BlindRsaTokenType());
        $second = PrivateToken::fromBinary(hex2bin(PrivacyPassTestVectors::TOKENS[1]['token']), new BlindRsaTokenType());

        $this->assertFalse($tokenKey->verify($first->getAuthenticatorInput(), $second->authenticator));
    }

    #[DataProvider('provideInvalidKeys')]
    public function testItRejectsAKeyNoTokenCanBeVerifiedWith(string $expectedMessage, string $tokenKey)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new BlindRsaTokenKey($tokenKey);
    }

    public static function provideInvalidKeys(): iterable
    {
        yield 'empty' => ['The token key must be a DER-encoded SubjectPublicKeyInfo.', ''];
        yield 'not DER' => ['The token key must be a DER-encoded SubjectPublicKeyInfo.', 'MIIBUjA9BgkqhkiG9w0BAQow'];
        yield 'a PEM-encoded key' => ['The token key must be a DER-encoded SubjectPublicKeyInfo.', "-----BEGIN PUBLIC KEY-----\n"];
        yield 'the id-rsaEncryption object identifier' => ['The token key must use the id-RSASSA-PSS object identifier.', hex2bin(PrivacyPassTestVectors::TOKEN_KEY_RSA_ENCRYPTION)];
        yield 'a 3072-bit modulus' => ['A Blind RSA token is signed with a 2048-bit key, 3072 given.', hex2bin(PrivacyPassTestVectors::TOKEN_KEY_3072)];
    }
}
