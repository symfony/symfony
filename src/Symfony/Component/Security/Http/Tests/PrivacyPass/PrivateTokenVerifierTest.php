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
use Symfony\Component\Security\Http\PrivacyPass\Exception\InvalidPrivateTokenException;
use Symfony\Component\Security\Http\PrivacyPass\PrivateToken;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenVerifier;
use Symfony\Component\Security\Http\PrivacyPass\TokenChallenge;
use Symfony\Component\Security\Http\PrivacyPass\TokenKeyInterface;
use Symfony\Component\Security\Http\PrivacyPass\TokenTypeInterface;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class PrivateTokenVerifierTest extends TestCase
{
    #[DataProvider('provideTestVectors')]
    public function testItVerifiesTheTokensOfTheTestVectors(TokenChallenge $challenge, string $binary)
    {
        $this->assertSame($challenge, (new PrivateTokenVerifier())->verify(self::parse($binary), [$challenge], [self::tokenKey()]));
    }

    #[DataProvider('provideTestVectors')]
    public function testItFindsTheChallengeTheTokenAnswersAmongTheOnesItAccepts(TokenChallenge $challenge, string $binary)
    {
        $challenges = [new TokenChallenge('issuer.example', new BlindRsaTokenType(), str_repeat("\x00", 32)), $challenge];

        $this->assertSame($challenge, (new PrivateTokenVerifier())->verify(self::parse($binary), $challenges, [self::tokenKey()]));
    }

    #[DataProvider('provideTestVectors')]
    public function testItRejectsATamperedAuthenticator(TokenChallenge $challenge, string $binary)
    {
        $binary = substr_replace($binary, $binary[353] ^ "\x01", 353, 1);

        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token carries an invalid authenticator.');

        (new PrivateTokenVerifier())->verify(self::parse($binary), [$challenge], [self::tokenKey()]);
    }

    #[DataProvider('provideTestVectors')]
    public function testItRejectsATamperedNonce(TokenChallenge $challenge, string $binary)
    {
        $binary = substr_replace($binary, $binary[2] ^ "\x01", 2, 1);

        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token carries an invalid authenticator.');

        (new PrivateTokenVerifier())->verify(self::parse($binary), [$challenge], [self::tokenKey()]);
    }

    #[DataProvider('provideTestVectors')]
    public function testItRejectsATokenAnsweringAChallengeThisOriginDoesNotAccept(TokenChallenge $challenge, string $binary)
    {
        $other = new TokenChallenge('issuer.example', new BlindRsaTokenType(), str_repeat("\x00", 32));

        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token answers no challenge this origin accepts.');

        (new PrivateTokenVerifier())->verify(self::parse($binary), [$other], [self::tokenKey()]);
    }

    #[DataProvider('provideTestVectors')]
    public function testItRejectsATokenNamingAKeyThisOriginDoesNotAccept(TokenChallenge $challenge, string $binary)
    {
        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token names a key this origin does not accept.');

        (new PrivateTokenVerifier())->verify(self::parse($binary), [$challenge], [new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::OTHER_TOKEN_KEY))]);
    }

    #[DataProvider('provideTestVectors')]
    public function testItRejectsATokenWhenThisOriginAcceptsNoKey(TokenChallenge $challenge, string $binary)
    {
        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token names a key this origin does not accept.');

        (new PrivateTokenVerifier())->verify(self::parse($binary), [$challenge], []);
    }

    public static function provideTestVectors(): iterable
    {
        foreach (PrivacyPassTestVectors::TOKENS as $i => $vector) {
            $challenge = new TokenChallenge($vector['issuer_name'], new BlindRsaTokenType(), hex2bin($vector['redemption_context']), $vector['origin_info']);

            yield 'RFC 9578 test vector '.($i + 1) => [$challenge, hex2bin($vector['token'])];
        }
    }

    public function testItRejectsATokenOfAnotherTypeThanTheChallengeItAnswers()
    {
        $challenge = new TokenChallenge('issuer.example', new BlindRsaTokenType());
        $tokenType = $this->createStub(TokenTypeInterface::class);
        $tokenType->method('getValue')->willReturn(0x0003);
        $tokenType->method('getKeyIdLength')->willReturn(32);
        $tokenType->method('getAuthenticatorLength')->willReturn(256);
        $tokenKey = $this->createStub(TokenKeyInterface::class);
        $tokenKey->method('getId')->willReturn(str_repeat("\x00", 32));
        $tokenKey->method('verify')->willReturn(true);

        $token = PrivateToken::fromBinary("\x00\x03".str_repeat("\x00", 32).$challenge->getDigest().str_repeat("\x00", 288), $tokenType);

        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token answers no challenge this origin accepts.');

        (new PrivateTokenVerifier())->verify($token, [$challenge], [$tokenKey]);
    }

    private static function parse(string $binary): PrivateToken
    {
        return PrivateToken::fromBinary($binary, new BlindRsaTokenType());
    }

    private static function tokenKey(): BlindRsaTokenKey
    {
        return new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));
    }
}
