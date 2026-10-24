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
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenKey;
use Symfony\Component\Security\Http\PrivacyPass\BlindRsaTokenType;
use Symfony\Component\Security\Http\PrivacyPass\CacheSpentTokenStore;
use Symfony\Component\Security\Http\PrivacyPass\IssuerDirectory;
use Symfony\Component\Security\Http\PrivacyPass\PrivateTokenManager;
use Symfony\Component\Security\Http\PrivacyPass\RedemptionContextInterface;
use Symfony\Component\Security\Http\PrivacyPass\SpentTokenStoreInterface;
use Symfony\Component\Security\Http\PrivacyPass\TokenChallengeFactory;
use Symfony\Component\Security\Http\Tests\PrivacyPass\Fixtures\PrivacyPassTestVectors;

class PrivateTokenManagerTest extends TestCase
{
    #[DataProvider('provideAcceptedHeaders')]
    public function testItAcceptsATokenAnsweringItsChallenge(string $authorization)
    {
        $this->assertTrue($this->manager()->isTokenValid(self::request($authorization)));
    }

    public static function provideAcceptedHeaders(): iterable
    {
        $token = self::token();

        yield 'quoted' => [\sprintf('PrivateToken token="%s"', $token)];
        yield 'unquoted' => [\sprintf('PrivateToken token=%s', $token)];
        yield 'lowercase scheme' => [\sprintf('privatetoken token="%s"', $token)];
        yield 'an unknown parameter first' => [\sprintf('PrivateToken unknown="ignore-me", token="%s"', $token)];
        yield 'an unknown parameter last' => [\sprintf('PrivateToken token="%s", unknown="ignore-me"', $token)];
    }

    #[DataProvider('provideRejectedHeaders')]
    public function testItRejectsARequestWithNoTokenItCanRead(string $authorization)
    {
        $this->assertFalse($this->manager()->isTokenValid(self::request($authorization)));
    }

    public static function provideRejectedHeaders(): iterable
    {
        yield 'no header' => [''];
        yield 'another scheme' => [\sprintf('Bearer %s', self::token())];
        yield 'the token parameter under another scheme' => [\sprintf('Bearer token="%s"', self::token())];
        yield 'the scheme alone' => ['PrivateToken'];
        yield 'no token parameter' => ['PrivateToken challenge="abc"'];
        yield 'another parameter ending in token' => [\sprintf('PrivateToken other-token="%s"', self::token())];
        yield 'not base64url' => ['PrivateToken token="@@@"'];
        yield 'another token type' => [\sprintf('PrivateToken token="%s"', strtr(base64_encode(hex2bin(PrivacyPassTestVectors::VOPRF_TOKEN)), '+/', '-_'))];
    }

    public function testItRejectsATamperedToken()
    {
        $binary = hex2bin(PrivacyPassTestVectors::TOKENS[0]['token']);
        $binary = substr_replace($binary, $binary[353] ^ "\x01", 353, 1);

        $this->assertFalse($this->manager()->isTokenValid(self::request(\sprintf('PrivateToken token="%s"', strtr(base64_encode($binary), '+/', '-_')))));
    }

    public function testItRejectsATokenBoundToAnotherContext()
    {
        $manager = $this->manager(context: str_repeat("\x00", 32));

        $this->assertFalse($manager->isTokenValid(self::request(\sprintf('PrivateToken token="%s"', self::token()))));
    }

    public function testATokenIsSpentOnce()
    {
        $manager = $this->manager(new CacheSpentTokenStore(new ArrayAdapter()));
        $request = self::request(\sprintf('PrivateToken token="%s"', self::token()));

        $this->assertTrue($manager->isTokenValid($request));
        $this->assertFalse($manager->isTokenValid($request));
    }

    public function testATokenItRejectsIsNotSpent()
    {
        $spentTokens = $this->createMock(SpentTokenStoreInterface::class);
        $spentTokens->expects($this->never())->method('spend');
        $manager = $this->manager($spentTokens);

        $this->assertFalse($manager->isTokenValid(self::request(\sprintf('PrivateToken token="%s"', strtr(base64_encode(str_repeat("\x00", 354)), '+/', '-_')))));
    }

    public function testATokenIsReplayableWithoutAStore()
    {
        $manager = $this->manager();
        $request = self::request(\sprintf('PrivateToken token="%s"', self::token()));

        $this->assertTrue($manager->isTokenValid($request));
        $this->assertTrue($manager->isTokenValid($request));
    }

    public function testItAcceptsATokenSignedWithAKeyTakenFromTheIssuerDirectory()
    {
        $vector = PrivacyPassTestVectors::TOKENS[0];
        $redemptionContext = $this->createStub(RedemptionContextInterface::class);
        $redemptionContext->method('getAcceptedContexts')->willReturn([hex2bin($vector['redemption_context'])]);
        $client = new MockHttpClient(new JsonMockResponse(['token-keys' => [[
            'token-type' => 2,
            'token-key' => strtr(base64_encode(hex2bin(PrivacyPassTestVectors::TOKEN_KEY)), '+/', '-_'),
        ]]]));
        $directory = new IssuerDirectory($client, new ArrayAdapter(), $vector['issuer_name'], new BlindRsaTokenType());
        $manager = new PrivateTokenManager(new TokenChallengeFactory($vector['issuer_name'], new BlindRsaTokenType(), $directory, $redemptionContext, $vector['origin_info']));

        $this->assertTrue($manager->isTokenValid(self::request(\sprintf('PrivateToken token="%s"', self::token()))));
        $this->assertStringContainsString(PrivacyPassTestVectors::HEADER_TOKEN_KEY_BASE64URL, $manager->getChallengeHeader(self::request('')));
    }

    public function testItChallengesTheRequestsItRejects()
    {
        $header = $this->manager()->getChallengeHeader(self::request(''));

        $this->assertStringStartsWith('PrivateToken challenge="', $header);
        $this->assertStringContainsString('token-key="', $header);
    }

    private function manager(?SpentTokenStoreInterface $spentTokens = null, ?string $context = null): PrivateTokenManager
    {
        $vector = PrivacyPassTestVectors::TOKENS[0];
        $redemptionContext = $this->createStub(RedemptionContextInterface::class);
        $redemptionContext->method('getAcceptedContexts')->willReturn([$context ?? hex2bin($vector['redemption_context'])]);

        $factory = new TokenChallengeFactory(
            $vector['issuer_name'],
            new BlindRsaTokenType(),
            [new BlindRsaTokenKey(hex2bin(PrivacyPassTestVectors::TOKEN_KEY))],
            $redemptionContext,
            $vector['origin_info'],
        );

        return new PrivateTokenManager($factory, $spentTokens);
    }

    private static function token(): string
    {
        return strtr(base64_encode(hex2bin(PrivacyPassTestVectors::TOKENS[0]['token'])), '+/', '-_');
    }

    private static function request(string $authorization): Request
    {
        $request = Request::create('https://origin.example/contact', 'POST');

        if ('' !== $authorization) {
            $request->headers->set('Authorization', $authorization);
        }

        return $request;
    }
}
