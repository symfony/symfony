<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcTokens;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\OidcTokensBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;

class OidcLoginAuthenticatorTest extends TestCase
{
    private OidcClient $oidcClient;
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;

    protected function setUp(): void
    {
        $this->oidcClient = $this->createMock(OidcClient::class);
        $this->successHandler = $this->createStub(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createStub(AuthenticationFailureHandlerInterface::class);
    }

    public function testSupportsCallbackWithCodeAndState()
    {
        $authenticator = $this->createAuthenticator();

        $request = Request::create('/oidc/callback?code=abc&state=xyz');
        $this->assertTrue($authenticator->supports($request));
    }

    public function testSupportsCallbackWithError()
    {
        $authenticator = $this->createAuthenticator();

        $request = Request::create('/oidc/callback?error=access_denied');
        $this->assertTrue($authenticator->supports($request));
    }

    public function testDoesNotSupportWrongPath()
    {
        $authenticator = $this->createAuthenticator();

        $request = Request::create('/other-path?code=abc&state=xyz');
        $this->assertFalse($authenticator->supports($request));
    }

    public function testDoesNotSupportMissingParams()
    {
        $authenticator = $this->createAuthenticator();

        $request = Request::create('/oidc/callback');
        $this->assertFalse($authenticator->supports($request));
    }

    public function testAuthenticate()
    {
        $tokens = new OidcTokens(
            accessToken: 'access-123',
            idToken: 'id-456',
            refreshToken: 'refresh-789',
        );

        $this->oidcClient->expects($this->once())
            ->method('handleCallback')
            ->willReturn($tokens);

        $this->oidcClient->expects($this->once())
            ->method('fetchUserInfo')
            ->with('access-123')
            ->willReturn([
                'sub' => 'user-42',
                'email' => 'test@example.com',
                'name' => 'Test User',
            ]);

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=abc&state=xyz');

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);

        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertSame('user-42', $userBadge->getUserIdentifier());

        $this->assertTrue($passport->hasBadge(OidcTokensBadge::class));
        $oidcTokensBadge = $passport->getBadge(OidcTokensBadge::class);
        $this->assertSame('access-123', $oidcTokensBadge->getOidcTokens()->accessToken);

        $this->assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    public function testAuthenticateWithoutUserinfo()
    {
        // Create a valid JWT-like ID token with claims in the payload
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => 'user-42',
            'email' => 'test@example.com',
        ])), '+/', '-_'), '=');
        $signature = base64_encode('fake-signature');
        $idToken = $header.'.'.$payload.'.'.$signature;

        $tokens = new OidcTokens(
            accessToken: 'access-123',
            idToken: $idToken,
        );

        $this->oidcClient->expects($this->once())
            ->method('handleCallback')
            ->willReturn($tokens);

        $this->oidcClient->expects($this->never())
            ->method('fetchUserInfo');

        $authenticator = $this->createAuthenticator(['enable_userinfo' => false]);
        $request = Request::create('/oidc/callback?code=abc&state=xyz');

        $passport = $authenticator->authenticate($request);

        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateMissingClaim()
    {
        $tokens = new OidcTokens(accessToken: 'access-123', idToken: 'id-456');

        $this->oidcClient->method('handleCallback')->willReturn($tokens);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['email' => 'test@example.com']);

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=abc&state=xyz');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('"sub" claim');

        $authenticator->authenticate($request);
    }

    public function testStartWithDirectRedirect()
    {
        $redirectResponse = new RedirectResponse('https://provider.example.com/authorize?...');
        $this->oidcClient->expects($this->once())
            ->method('startAuthorization')
            ->willReturn($redirectResponse);

        $authenticator = $this->createAuthenticator(['direct_redirect' => true]);
        $request = Request::create('/protected');

        $response = $authenticator->start($request);

        $this->assertSame($redirectResponse, $response);
    }

    public function testStartWithoutDirectRedirect()
    {
        $authenticator = $this->createAuthenticator(['direct_redirect' => false]);
        $request = Request::create('/protected');

        $response = $authenticator->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());
    }

    private function createAuthenticator(array $options = []): OidcLoginAuthenticator
    {
        return new OidcLoginAuthenticator(
            new HttpUtils(),
            $this->oidcClient,
            $this->successHandler,
            $this->failureHandler,
            $options,
        );
    }
}
