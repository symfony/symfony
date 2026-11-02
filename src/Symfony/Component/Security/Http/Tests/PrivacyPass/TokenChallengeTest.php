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
use Symfony\Component\Security\Http\PrivacyPass\TokenChallenge;
use Symfony\Component\Security\Http\PrivacyPass\TokenTypeInterface;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class TokenChallengeTest extends TestCase
{
    #[DataProvider('provideIssuanceTestVectors')]
    public function testItEncodesTheChallengesOfTheTestVectors(string $issuerName, string $redemptionContext, array $originInfo, string $expected)
    {
        $challenge = new TokenChallenge($issuerName, new BlindRsaTokenType(), hex2bin($redemptionContext), $originInfo);

        $this->assertSame($expected, self::toHex($challenge));
    }

    public static function provideIssuanceTestVectors(): iterable
    {
        foreach (PrivacyPassTestVectors::TOKENS as $i => $vector) {
            yield 'RFC 9578 test vector '.($i + 1) => [$vector['issuer_name'], $vector['redemption_context'], $vector['origin_info'], $vector['challenge']];
        }
    }

    #[DataProvider('provideAuthenticationSchemeTestVectors')]
    public function testTheDigestMatchesTheTestVectors(string $issuerName, string $redemptionContext, array $originInfo, string $authenticatorInput)
    {
        $challenge = new TokenChallenge($issuerName, new BlindRsaTokenType(), hex2bin($redemptionContext), $originInfo);

        $this->assertSame(substr($authenticatorInput, 68, 64), bin2hex($challenge->getDigest()));
    }

    public static function provideAuthenticationSchemeTestVectors(): iterable
    {
        foreach (PrivacyPassTestVectors::AUTHENTICATOR_INPUTS as $i => $vector) {
            yield 'RFC 9577 test vector '.($i + 1) => [$vector['issuer_name'], $vector['redemption_context'], $vector['origin_info'], $vector['authenticator_input']];
        }
    }

    public function testTheChallengeParameterIsBase64UrlEncodedWithItsPadding()
    {
        $challenge = new TokenChallenge('issuer.example', new BlindRsaTokenType(), hex2bin(substr(PrivacyPassTestVectors::HEADER_CHALLENGE, 38, 64)), ['origin.example']);

        $this->assertSame(PrivacyPassTestVectors::HEADER_CHALLENGE, self::toHex($challenge));
        $this->assertSame(PrivacyPassTestVectors::HEADER_CHALLENGE_BASE64URL, $challenge->toBase64Url());
        $this->assertStringEndsWith('==', $challenge->toBase64Url());
    }

    public function testACrossOriginChallengeCarriesNoOriginInformation()
    {
        $challenge = new TokenChallenge('issuer.example', new BlindRsaTokenType());

        $this->assertSame('0002000e6973737565722e6578616d706c65000000', self::toHex($challenge));
    }

    public function testTheOriginNamesAreCommaSeparated()
    {
        $challenge = new TokenChallenge('issuer.example', new BlindRsaTokenType(), '', ['foo.example', 'bar.example']);

        $this->assertStringEndsWith(bin2hex('foo.example,bar.example'), self::toHex($challenge));
    }

    public function testItAcceptsAPortInAServerName()
    {
        $challenge = new TokenChallenge('issuer.example:8443', new BlindRsaTokenType(), '', ['origin.example:8443']);

        $this->assertStringContainsString(bin2hex('issuer.example:8443'), self::toHex($challenge));
    }

    public function testTheTokenTypeIsTakenFromTheTypeItself()
    {
        $tokenType = $this->createStub(TokenTypeInterface::class);
        $tokenType->method('getValue')->willReturn(0x0003);

        $challenge = new TokenChallenge('issuer.example', $tokenType);

        $this->assertSame('0003000e6973737565722e6578616d706c65000000', self::toHex($challenge));
    }

    public function testItRejectsATokenTypeThatDoesNotFitTheChallengeStructure()
    {
        $tokenType = $this->createStub(TokenTypeInterface::class);
        $tokenType->method('getValue')->willReturn(0x10000);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must declare a 16-bit value, "65536" given.');

        new TokenChallenge('issuer.example', $tokenType);
    }

    private static function toHex(TokenChallenge $challenge): string
    {
        return bin2hex(base64_decode(strtr($challenge->toBase64Url(), '-_', '+/'), true));
    }

    #[DataProvider('provideInvalidChallenges')]
    public function testItRejectsAChallengeTheClientWouldIgnore(string $expectedMessage, string $issuerName, string $redemptionContext = '', array $originInfo = [])
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new TokenChallenge($issuerName, new BlindRsaTokenType(), $redemptionContext, $originInfo);
    }

    public static function provideInvalidChallenges(): iterable
    {
        yield 'empty issuer name' => ['The issuer name must be between 1 and 65535 bytes long.', ''];
        yield 'issuer name too long' => ['The issuer name must be between 1 and 65535 bytes long.', str_repeat('a', 65536)];
        yield 'user information in the issuer name' => ['The issuer name "issuer@example" must be an ASCII host with an optional port, and no user information.', 'issuer@example'];
        yield 'path in the issuer name' => ['The issuer name "issuer.example/token" must be an ASCII host with an optional port, and no user information.', 'issuer.example/token'];
        yield 'scheme in the issuer name' => ['The issuer name "https://issuer.example" must be an ASCII host with an optional port, and no user information.', 'https://issuer.example'];
        yield 'user information in an origin name' => ['The origin name "origin@example" must be an ASCII host with an optional port, and no user information.', 'issuer.example', '', ['origin@example']];
        yield 'a space in the issuer name' => ['The issuer name "issuer .example" must be an ASCII host with an optional port, and no user information.', 'issuer .example'];
        yield 'a control character in the issuer name' => ["The issuer name \"issuer.example\x7F\" must be an ASCII host with an optional port, and no user information.", "issuer.example\x7F"];
        yield 'a name that is not an A-label' => ['The issuer name "issüer.example" must be an ASCII host with an optional port, and no user information.', 'issüer.example'];
        yield 'a non-ASCII origin name' => ['The origin name "origin.exämple" must be an ASCII host with an optional port, and no user information.', 'issuer.example', '', ['origin.exämple']];
        yield 'redemption context of 16 bytes' => ['The redemption context must be either empty or 32 bytes long, 16 bytes given.', 'issuer.example', '0123456789abcdef'];
    }
}
