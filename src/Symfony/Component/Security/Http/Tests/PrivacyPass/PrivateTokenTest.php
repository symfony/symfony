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
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\Exception\InvalidPrivateTokenException;
use Symfony\Component\Security\Http\PrivacyPass\PrivateToken;
use Symfony\Component\Security\Http\PrivacyPass\TokenTypeInterface;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class PrivateTokenTest extends TestCase
{
    #[DataProvider('provideTestVectors')]
    public function testItParsesTheTokensOfTheTestVectors(string $nonce, string $challenge, string $token)
    {
        $token = PrivateToken::fromBinary(hex2bin($token), new BlindRsaTokenType());

        $this->assertSame(0x0002, $token->tokenType->getValue());
        $this->assertSame($nonce, bin2hex($token->nonce));
        $this->assertSame(hash('sha256', hex2bin($challenge)), bin2hex($token->challengeDigest));
        $this->assertSame(PrivacyPassTestVectors::TOKEN_KEY_ID, bin2hex($token->tokenKeyId));
        $this->assertSame(256, \strlen($token->authenticator));
    }

    #[DataProvider('provideTestVectors')]
    public function testItParsesTheTokenAuthenticationParameter(string $nonce, string $challenge, string $token)
    {
        $encoded = strtr(base64_encode(hex2bin($token)), '+/', '-_');

        $this->assertSame($nonce, bin2hex(PrivateToken::fromBase64Url($encoded, new BlindRsaTokenType())->nonce));
    }

    public static function provideTestVectors(): iterable
    {
        foreach (PrivacyPassTestVectors::TOKENS as $i => $vector) {
            yield 'RFC 9578 test vector '.($i + 1) => [$vector['nonce'], $vector['challenge'], $vector['token']];
        }
    }

    #[DataProvider('provideAuthenticatorInputs')]
    public function testTheAuthenticatorInputMatchesTheTestVectors(string $authenticatorInput)
    {
        $token = PrivateToken::fromBinary(hex2bin($authenticatorInput).str_repeat("\x00", 256), new BlindRsaTokenType());

        $this->assertSame($authenticatorInput, bin2hex($token->getAuthenticatorInput()));
    }

    public static function provideAuthenticatorInputs(): iterable
    {
        foreach (PrivacyPassTestVectors::AUTHENTICATOR_INPUTS as $i => $vector) {
            yield 'RFC 9577 test vector '.($i + 1) => [$vector['authenticator_input']];
        }
    }

    public function testTheStructureOfTheTokenIsReadFromItsType()
    {
        $tokenType = $this->createStub(TokenTypeInterface::class);
        $tokenType->method('getValue')->willReturn(0x0001);
        $tokenType->method('getKeyIdLength')->willReturn(32);
        $tokenType->method('getAuthenticatorLength')->willReturn(48);

        $token = PrivateToken::fromBinary(hex2bin(PrivacyPassTestVectors::VOPRF_TOKEN), $tokenType);

        $this->assertSame(48, \strlen($token->authenticator));
        $this->assertSame(substr(PrivacyPassTestVectors::VOPRF_TOKEN, 0, 196), bin2hex($token->getAuthenticatorInput()));
    }

    #[DataProvider('provideInvalidTokens')]
    public function testItRejectsATokenItCannotRead(string $expectedMessage, string $token)
    {
        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage($expectedMessage);

        PrivateToken::fromBinary($token, new BlindRsaTokenType());
    }

    public static function provideInvalidTokens(): iterable
    {
        yield 'empty' => ['A token of type "0x0002" is 354 bytes long, 0 given.', ''];
        yield 'one byte' => ['A token of type "0x0002" is 354 bytes long, 1 given.', "\x00"];
        yield 'one byte short' => ['A token of type "0x0002" is 354 bytes long, 353 given.', "\x00\x02".str_repeat("\x00", 351)];
        yield 'one byte too long' => ['A token of type "0x0002" is 354 bytes long, 355 given.', "\x00\x02".str_repeat("\x00", 353)];
        yield 'a token of another type' => ['A token of type "0x0002" is 354 bytes long, 146 given.', hex2bin(PrivacyPassTestVectors::VOPRF_TOKEN)];
        yield 'a greased token type' => ['The token is of type "0x0000", a token of type "0x0002" was asked for.', str_repeat("\x00", 354)];
        yield 'a reserved token type' => ['The token is of type "0x02AA", a token of type "0x0002" was asked for.', "\x02\xAA".str_repeat("\x00", 352)];
    }

    public function testItRejectsAValueThatIsNotBase64Url()
    {
        $this->expectException(InvalidPrivateTokenException::class);
        $this->expectExceptionMessage('The token is not base64url-encoded.');

        PrivateToken::fromBase64Url('@@@', new BlindRsaTokenType());
    }
}
