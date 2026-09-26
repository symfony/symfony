<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Oidc\OidcBackChannelLogout;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\Oidc\OidcEndedSessions;
use Symfony\Component\Security\Http\Oidc\OidcLogoutToken;

#[RequiresPhpExtension('openssl')]
class OidcBackChannelLogoutTest extends TestCase
{
    private const ISSUER = 'https://provider.example.com';
    private const CLIENT_ID = 'client-id';
    private const NOW = 1758350000;

    private const PUBLIC_JWK = [
        'kid' => 'signing-key',
        'kty' => 'EC',
        'crv' => 'P-256',
        'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
        'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
        'use' => 'sig',
        'alg' => 'ES256',
    ];

    public function testLogoutRecordsTheEndOfTheSessionTheProviderNames()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');

        $this->createBackChannelLogout($endedSessions)->logout($this->buildLogoutToken());

        $this->assertTrue($endedSessions->has('session-42'));
    }

    /**
     * A token carrying a "sub" as well names an end-user and a session: it is the session
     * that ended, and logging every session of that user out is what "sid" avoids.
     */
    public function testLogoutRecordsTheSessionAndNotTheUserItNamesToo()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');

        $this->createBackChannelLogout($endedSessions)->logout($this->buildLogoutToken(['sid' => 'another-session']));

        $this->assertTrue($endedSessions->has('another-session'));
        $this->assertFalse($endedSessions->has('user-42'));
        $this->assertFalse($endedSessions->has('session-42'));
    }

    /**
     * Section 2.8: the provider is told it is done. A session this server never opened, and one
     * whose end is already recorded, both cost an entry and no more; replaying a logout token
     * says what the first one said.
     */
    public function testLogoutTakesTheSameTokenTwice()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');
        $logout = $this->createBackChannelLogout($endedSessions);
        $token = $this->buildLogoutToken();

        $logout->logout($token);
        $logout->logout($token);

        $this->assertTrue($endedSessions->has('session-42'));
    }

    public function testLogoutRejectsATokenTheProviderDidNotSign()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');

        [$header, $payload] = explode('.', $this->buildLogoutToken());

        try {
            $this->createBackChannelLogout($endedSessions)->logout($header.'.'.$payload.'.');
            $this->fail(\sprintf('Expected a "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            // the caller answers this to whoever reached the endpoint, so it says no more
            // than that the token is not the provider's; the reason is the previous one
            $this->assertSame('The logout token could not be verified.', $e->getMessage());
            $this->assertSame('The ID token signature is invalid.', $e->getPrevious()?->getMessage());
        }

        $this->assertFalse($endedSessions->has('session-42'));
    }

    public function testLogoutRejectsATokenWhoseClaimsDoNotHold()
    {
        $endedSessions = new OidcEndedSessions(new ArrayAdapter(), 'main');

        try {
            $this->createBackChannelLogout($endedSessions)->logout($this->buildLogoutToken(['aud' => 'another-client']));
            $this->fail(\sprintf('Expected a "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertStringStartsWith('Invalid logout token', $e->getMessage());
        }

        $this->assertFalse($endedSessions->has('session-42'));
    }

    private function createBackChannelLogout(OidcEndedSessions $endedSessions): OidcBackChannelLogout
    {
        $clock = new MockClock((new \DateTimeImmutable())->setTimestamp(self::NOW + 10));
        $discovery = new OidcDiscovery(
            new MockHttpClient(new JsonMockResponse(['issuer' => self::ISSUER, 'jwks_uri' => self::ISSUER.'/jwks'])),
            new ArrayAdapter(),
            self::ISSUER.'/.well-known/openid-configuration',
        );

        return new OidcBackChannelLogout(
            new OidcSignatureVerifier(
                $discovery,
                new ArrayAdapter(),
                new MockHttpClient(new JsonMockResponse(['keys' => [self::PUBLIC_JWK]])),
                ['ES256'],
                3600,
                true,
                $clock,
            ),
            new OidcLogoutToken($clock),
            $discovery,
            self::CLIENT_ID,
            $endedSessions,
        );
    }

    private function buildLogoutToken(array $claims = []): string
    {
        $jwk = new JWK([
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
            'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
            'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
        ]);

        $payload = json_encode($claims + [
            'iss' => self::ISSUER,
            'sub' => 'user-42',
            'aud' => self::CLIENT_ID,
            'iat' => self::NOW,
            'jti' => 'a9c6f0e0-0d3e-4f0e-9a1e-2f5f3a0b7c11',
            'events' => [OidcLogoutToken::EVENT => new \stdClass()],
            'sid' => 'session-42',
        ]);

        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload($payload)
                ->addSignature($jwk, ['alg' => 'ES256', 'kid' => 'signing-key', 'typ' => 'logout+jwt'])
                ->build()
        );
    }
}
