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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenKey;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\EmptyRedemptionContext;
use Symfony\Component\Security\Http\PrivacyPass\RedemptionContextInterface;
use Symfony\Component\Security\Http\PrivacyPass\TimeWindowRedemptionContext;
use Symfony\Component\Security\Http\PrivacyPass\TokenChallengeFactory;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class TokenChallengeFactoryTest extends TestCase
{
    public function testTheChallengeHeaderMatchesTheTestVectors()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], self::context(hex2bin(substr(PrivacyPassTestVectors::HEADER_CHALLENGE, 38, 64))), ['origin.example'], 10);

        $this->assertSame(
            \sprintf('PrivateToken challenge="%s", token-key="%s", max-age="10"', PrivacyPassTestVectors::HEADER_CHALLENGE_BASE64URL, PrivacyPassTestVectors::HEADER_TOKEN_KEY_BASE64URL),
            $factory->getChallengeHeader(self::request()),
        );
    }

    public function testTheMaxAgeIsOptional()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext());

        $this->assertStringNotContainsString('max-age', $factory->getChallengeHeader(self::request()));
    }

    public function testTheMaxAgeIsReadFromTheRedemptionContext()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new TimeWindowRedemptionContext('s3cr3t', 1800));

        $this->assertStringContainsString('max-age="3600"', $factory->getChallengeHeader(self::request()));
    }

    public function testTheMaxAgeCanBeShorterThanTheRedemptionContext()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new TimeWindowRedemptionContext('s3cr3t', 1800), null, 60);

        $this->assertStringContainsString('max-age="60"', $factory->getChallengeHeader(self::request()));
    }

    public function testItRefusesToPromiseMoreThanTheRedemptionContextAccepts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A challenge cannot be advertised as accepted for 7201 seconds: its redemption context stops accepting it after 7200.');

        new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new TimeWindowRedemptionContext('s3cr3t'), null, 7201);
    }

    public function testItRejectsANegativeMaxAge()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The max-age of a challenge cannot be negative, "-1" given.');

        new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new TimeWindowRedemptionContext('s3cr3t'), null, -1);
    }

    public function testItChallengesWithTheFirstAcceptedContextAndAcceptsTheOthers()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], self::context(str_repeat("\x01", 32), str_repeat("\x02", 32)), []);

        $challenges = $factory->getChallenges(self::request());

        $this->assertCount(2, $challenges);
        $this->assertSame(str_repeat("\x01", 32), $challenges[0]->redemptionContext);
        $this->assertSame(str_repeat("\x02", 32), $challenges[1]->redemptionContext);
        $this->assertStringContainsString($challenges[0]->toBase64Url(), $factory->getChallengeHeader(self::request()));
    }

    public function testItChallengesWithTheFirstKeyAndAcceptsTheOthers()
    {
        $other = new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::OTHER_TOKEN_KEY));
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [$other, self::tokenKey()], new EmptyRedemptionContext());

        $this->assertStringContainsString($other->toBase64Url(), $factory->getChallengeHeader(self::request()));
        $this->assertCount(2, [...$factory->getTokenKeys()]);
    }

    public function testItBindsTheTokensToThisOriginByDefault()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext());

        $challenge = $factory->getChallenges(self::request())[0];

        $this->assertSame(['origin.example'], $challenge->originInfo);
    }

    public function testItIssuesCrossOriginChallenges()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), []);

        $challenge = $factory->getChallenges(self::request())[0];

        $this->assertSame([], $challenge->originInfo);
        $this->assertStringEndsWith('0000', bin2hex(base64_decode(strtr($challenge->toBase64Url(), '-_', '+/'), true)));
    }

    public function testTheOriginNamesAreComparedWithoutTheirCase()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), ['ORIGIN.EXAMPLE', 'other.example']);

        $this->assertSame(['ORIGIN.EXAMPLE', 'other.example'], $factory->getChallenges(self::request())[0]->originInfo);
    }

    public function testItRejectsAnOriginOutsideTheNamesTheTokensAreScopedTo()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), ['other.example']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The origin "origin.example" must be one of the names the tokens are scoped to, "other.example" given.');

        $factory->getChallenges(self::request());
    }

    public function testItNeverGreasesTheChallengeByDefault()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext());

        for ($i = 0; $i < 20; ++$i) {
            $this->assertSame(1, substr_count($factory->getChallengeHeader(self::request()), 'PrivateToken '));
        }
    }

    public function testItGreasesTheChallengeWithAReservedTokenType()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), null, 10, 100);

        $header = $factory->getChallengeHeader(self::request());
        $challenges = array_map(trim(...), explode('PrivateToken ', $header));

        $this->assertCount(3, $challenges);
        [, $real, $greased] = $challenges;
        $this->assertSame($factory->getChallenges(self::request())[0]->toBase64Url(), self::parameter($real, 'challenge'));
        $this->assertContains(unpack('n', base64_decode(strtr(self::parameter($greased, 'challenge'), '-_', '+/'), true))[1], [0x0000, 0x02AA, 0x1132, 0x2E96, 0x3CD3, 0x4473, 0x5A63, 0x6D32, 0x7F3F, 0x8D07, 0x916B, 0xA6A4, 0xBEAB, 0xC3F3, 0xDA42, 0xE944, 0xF057]);
        $this->assertSame(\strlen(self::parameter($real, 'challenge')), \strlen(self::parameter($greased, 'challenge')));
        $this->assertSame(\strlen(self::parameter($real, 'token-key')), \strlen(self::parameter($greased, 'token-key')));
        $this->assertNotSame(self::parameter($real, 'token-key'), self::parameter($greased, 'token-key'));
        $this->assertSame('10', self::parameter($greased, 'max-age'));
    }

    public function testAGreasedChallengeIsNeverAcceptedForARedemption()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), null, null, 100);

        $this->assertCount(1, $factory->getChallenges(self::request()));
    }

    public function testEachGreasedChallengeIsDrawnAgain()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), null, null, 100);

        $this->assertNotSame($factory->getChallengeHeader(self::request()), $factory->getChallengeHeader(self::request()));
    }

    public function testItRejectsAShareOfGreasedChallengesThatIsNotAPercentage()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The share of greased challenges must be a percentage, "101" given.');

        new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [self::tokenKey()], new EmptyRedemptionContext(), null, null, 101);
    }

    public function testItNeedsAKeyToChallengeWith()
    {
        $factory = new TokenChallengeFactory('issuer.example', new BlindRsaTokenType(), [], new EmptyRedemptionContext());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The origin has no token key of the issuer "issuer.example" to challenge with.');

        $factory->getChallengeHeader(self::request());
    }

    private function context(string ...$contexts): RedemptionContextInterface
    {
        $redemptionContext = $this->createStub(RedemptionContextInterface::class);
        $redemptionContext->method('getAcceptedContexts')->willReturn($contexts);

        return $redemptionContext;
    }

    private static function request(): Request
    {
        return Request::create('https://origin.example/contact', 'POST');
    }

    private static function parameter(string $challenge, string $name): string
    {
        preg_match(\sprintf('/%s="([^"]*)"/', $name), $challenge, $matches);

        return $matches[1] ?? '';
    }

    private static function tokenKey(): BlindRsaTokenKey
    {
        return new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY));
    }
}
