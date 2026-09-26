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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Oidc\OidcLogoutToken;

class OidcLogoutTokenTest extends TestCase
{
    private const ISSUER = 'https://provider.example.com';
    private const AUDIENCE = 'client-id';

    public function testValidateClaimsReturnsTheSessionThatEnded()
    {
        $this->assertSame('session-42', $this->validate());
    }

    public function testValidateClaimsAcceptsATokenThatSaysWhenItExpires()
    {
        // RFC 8417, Section 2 has a security event state what happened rather than expire,
        // but a provider sending an "exp" is taken at its word
        $this->assertSame('session-42', $this->validate(['exp' => 1758400000]));
    }

    public function testValidateClaimsRejectsAnExpiredToken()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid logout token');

        $this->validate(['exp' => 1758300000]);
    }

    public function testValidateClaimsRejectsATokenOfAnotherIssuer()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid logout token');

        $this->validate(['iss' => 'https://attacker.example.com']);
    }

    public function testValidateClaimsRejectsATokenMeantForAnotherClient()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid logout token');

        $this->validate(['aud' => 'another-client']);
    }

    #[DataProvider('provideMissingMandatoryClaims')]
    public function testValidateClaimsRejectsATokenMissingAMandatoryClaim(string $claim)
    {
        // Back-Channel Logout 1.0, Section 2.4
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid logout token');

        $this->validate([$claim => null]);
    }

    public static function provideMissingMandatoryClaims(): iterable
    {
        yield 'iss' => ['iss'];
        yield 'aud' => ['aud'];
        yield 'iat' => ['iat'];
        yield 'jti' => ['jti'];
        yield 'events' => ['events'];
    }

    public function testValidateClaimsRejectsATokenCarryingANonce()
    {
        // Section 2.4: an ID token replayed as a logout token is what this catches
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('it carries a "nonce" claim, which only an ID token does');

        $this->validate(['nonce' => 'n-0S6_WzA2Mj']);
    }

    #[DataProvider('provideInvalidEvents')]
    public function testValidateClaimsRejectsATokenWhoseEventsDoNotAnnounceALogout(mixed $events)
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('the "events" claim must be an object carrying the "http://schemas.openid.net/event/backchannel-logout" member');

        $this->validate(['events' => $events]);
    }

    public static function provideInvalidEvents(): iterable
    {
        yield 'another event' => [['https://schemas.openid.net/secevent/caep/event-type/session-revoked' => []]];
        yield 'no member at all' => [[]];
        yield 'not an object' => ['http://schemas.openid.net/event/backchannel-logout'];
        yield 'a member that is not an object' => [[OidcLogoutToken::EVENT => 'now']];
    }

    #[DataProvider('provideTokensWithoutASession')]
    public function testValidateClaimsRejectsATokenThatDoesNotNameASession(mixed $sid)
    {
        // the client registered "backchannel_logout_session_required": a token naming the
        // end-user alone would have it log every session of that user out
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('the "sid" claim must be a non-empty string');

        $this->validate(['sid' => $sid]);
    }

    public static function provideTokensWithoutASession(): iterable
    {
        yield 'absent' => [null];
        yield 'empty' => [''];
        yield 'not a string' => [42];
    }

    private function validate(array $claims = []): string
    {
        $claims = array_filter($claims + [
            'iss' => self::ISSUER,
            'sub' => 'user-42',
            'aud' => self::AUDIENCE,
            'iat' => 1758350000,
            'jti' => 'a9c6f0e0-0d3e-4f0e-9a1e-2f5f3a0b7c11',
            'events' => [OidcLogoutToken::EVENT => []],
            'sid' => 'session-42',
        ], static fn (mixed $value): bool => null !== $value);

        $logoutToken = new OidcLogoutToken(new MockClock((new \DateTimeImmutable())->setTimestamp(1758350010)));

        return $logoutToken->validateClaims($claims, self::ISSUER, self::AUDIENCE);
    }
}
