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

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\Clock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\OidcUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\UnsupportedReasons;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\OidcLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

#[AllowMockObjectsWithoutExpectations]
class OidcLoginAuthenticatorTest extends TestCase
{
    private OidcClient $oidcClient;
    private OidcDiscovery $discovery;
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;

    protected function setUp(): void
    {
        $this->discovery = $this->createDiscovery([
            'authorization_endpoint' => 'https://provider.example.com/authorize',
            'token_endpoint' => 'https://provider.example.com/token',
            'issuer' => 'https://provider.example.com',
            'userinfo_endpoint' => 'https://provider.example.com/userinfo',
            'jwks_uri' => 'https://provider.example.com/jwks',
        ]);

        $this->oidcClient = $this->createMock(OidcClient::class);

        $this->successHandler = $this->createStub(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createStub(AuthenticationFailureHandlerInterface::class);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createDiscovery(array $configuration): OidcDiscovery
    {
        return new OidcDiscovery(
            new MockHttpClient(static fn (): MockResponse => new JsonMockResponse($configuration)),
            new ArrayAdapter(),
            'https://provider.example.com/.well-known/openid-configuration',
        );
    }

    public function testSupportsCallbackWithCode()
    {
        $authenticator = $this->createAuthenticator();

        $this->assertTrue($authenticator->supports(Request::create('/oidc/callback?code=abc&state=xyz')));
    }

    public function testSupportsCallbackWithError()
    {
        $authenticator = $this->createAuthenticator();

        $this->assertTrue($authenticator->supports(Request::create('/oidc/callback?error=access_denied')));
    }

    public function testDoesNotSupportWrongPath()
    {
        $authenticator = $this->createAuthenticator();

        $this->assertFalse($authenticator->supports(Request::create('/other-path?code=abc')));
    }

    public function testUnsupportedReasons()
    {
        $authenticator = $this->createAuthenticator();

        $request = Request::create('/other-path?code=abc');
        $request->attributes->set(SecurityRequestAttributes::UNSUPPORTED_REASONS, $reasons = new UnsupportedReasons());

        $this->assertFalse($authenticator->supports($request));
        $this->assertSame(['the request path "/other-path" does not match the "check_path" option "/oidc/callback"'], $reasons->all());

        $request = Request::create('/oidc/callback?code=abc');
        $request->attributes->set(SecurityRequestAttributes::UNSUPPORTED_REASONS, $reasons = new UnsupportedReasons());

        $this->assertTrue($authenticator->supports($request));
        $this->assertSame([], $reasons->all());
    }

    public function testSupportsTheCallbackPathWithoutAnyParameter()
    {
        // the route declared for the callback path carries no controller, so a request
        // getting past the authenticator would be reported as a routing error
        $authenticator = $this->createAuthenticator();

        $this->assertTrue($authenticator->supports(Request::create('/oidc/callback')));
    }

    public function testAuthenticateRejectsACallbackWithoutAnyParameter()
    {
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRequiresASession()
    {
        $authenticator = $this->createAuthenticator();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('stores the OIDC "state", "nonce" and PKCE code verifier in the session');

        $authenticator->authenticate(Request::create('/oidc/callback?code=abc&state=xyz'));
    }

    public function testStartRequiresASession()
    {
        $authenticator = $this->createAuthenticator();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('nor on a stateless firewall');

        $authenticator->start(Request::create('/protected'));
    }

    public function testAuthenticate()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);

        $this->oidcClient->expects($this->once())
            ->method('fetchUserInfo')
            ->with('access-123')
            ->willReturn([
                'sub' => 'user-42',
                'email' => 'test@example.com',
                'name' => 'Test User',
            ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        // no remember-me: it would re-establish a session without contacting the IdP,
        // and the OIDC user provider cannot reload a user by its identifier alone
        $this->assertFalse($passport->hasBadge(RememberMeBadge::class));
        $this->assertSame('access-123', $passport->getAttribute('oidc_token_data')['access_token']);
    }

    public function testAuthenticateVerifiesTheIdTokenSignature()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $this->buildSignedIdToken(['nonce' => $nonce]),
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator(signatureVerifier: $this->createSignatureVerifier());
        $passport = $authenticator->authenticate($this->createCallbackRequest($state, $nonce));

        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    #[DataProvider('getUnverifiableIdTokens')]
    public function testAuthenticateRejectsAnIdTokenTheProviderDidNotSign(string $idTokenBuilder, string $expectedMessage)
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            // the attacker owns the claims: the identity and the roles they carry
            'id_token' => $this->$idTokenBuilder(['nonce' => $nonce, 'sub' => 'admin', 'roles' => ['ROLE_ADMIN']]),
        ]);
        $this->oidcClient->expects($this->never())->method('fetchUserInfo');

        $authenticator = $this->createAuthenticator(signatureVerifier: $this->createSignatureVerifier());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $authenticator->authenticate($this->createCallbackRequest($state, $nonce));
    }

    public static function getUnverifiableIdTokens(): iterable
    {
        yield 'forged signature' => ['buildForgedIdToken', 'The ID token signature is invalid.'];
        yield 'no signature at all' => ['buildUnsecuredIdToken', 'The ID token is not signed with any of the expected algorithms ("ES256").'];
        yield 'signature of another provider' => ['buildIdToken', 'The ID token is not signed with any of the expected algorithms ("ES256").'];
    }

    #[DataProvider('getUnverifiableIdTokens')]
    public function testAuthenticateAcceptsAnyIdTokenWhenTheSignatureIsNotVerified(string $idTokenBuilder, string $expectedMessage)
    {
        // without a verifier, the transport security of the token endpoint request is the
        // only thing standing between the provider and a forged token: this is the very
        // behavior the "id_token_signature.required" option opts out of
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $this->$idTokenBuilder(['nonce' => $nonce, 'sub' => 'admin', 'roles' => ['ROLE_ADMIN']]),
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'admin']);

        $authenticator = $this->createAuthenticator();
        $passport = $authenticator->authenticate($this->createCallbackRequest($state, $nonce));

        $this->assertSame('admin', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateDoesNotGrantRolesFromClaims()
    {
        // a "roles" claim returned by the provider must not be mass-assigned onto the
        // OidcUser roles: the login flow always yields the default ROLE_USER
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'user-42',
            'roles' => ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertSame(['ROLE_USER'], $passport->getUser()->getRoles());
    }

    public function testAuthenticateIgnoresProviderSuppliedIdentifierClaim()
    {
        // a provider "userIdentifier" claim must not override the identity derived
        // from the configured identifier claim
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'user-42',
            'userIdentifier' => 'spoofed-identity',
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertSame('user-42', $passport->getUser()->getUserIdentifier());
        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateWithIdTokenDataSource()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken([
            'nonce' => $nonce,
            'sub' => 'user-42',
            'email' => 'test@example.com',
        ]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);

        $this->oidcClient->expects($this->never())
            ->method('fetchUserInfo');

        $authenticator = $this->createAuthenticator(['user_data_source' => 'id_token']);
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticateWithCustomUserIdentifierClaim()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'user-42',
            'email' => 'test@example.com',
        ]);

        $authenticator = $this->createAuthenticator(['user_identifier_claim' => 'email']);
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertSame('test@example.com', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        // the user provider receives that identifier, and the built-in one keeps it
        $this->assertSame('test@example.com', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateRejectsANonStringUserIdentifierClaim()
    {
        // providers do send numeric values in custom claims; a non-string identifier
        // must fail authentication cleanly instead of escaping as a TypeError
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'user-42',
            'uid' => 12345,
        ]);

        $authenticator = $this->createAuthenticator(['user_identifier_claim' => 'uid']);
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The "uid" claim is missing or invalid in the OIDC response.');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsUserInfoSubMismatchWithACustomUserIdentifierClaim()
    {
        // the "sub" binding of OIDC Core 1.0, Section 5.3.2 is what ties the UserInfo
        // document to the authenticated user: it holds whatever claim the identifier
        // is read from, so a swapped document cannot define the identity
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'other-user',
            'email' => 'victim@example.com',
        ]);

        $authenticator = $this->createAuthenticator(['user_identifier_claim' => 'email']);
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The "sub" claim from the UserInfo endpoint does not match the ID token.');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateLoadsTheUserFromTheFirewallUserProvider()
    {
        // the firewall's user provider is what loads the user, and mapping claims onto
        // roles is what it is for: it receives the verified identifier and every claim,
        // including the ones the built-in OIDC provider refuses to map by itself
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'user-42',
            'email' => 'test@example.com',
            'roles' => ['admin'],
        ]);

        $userProvider = new class implements AttributesBasedUserProviderInterface {
            public array $claims = [];

            public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
            {
                $this->claims = $attributes;

                return new InMemoryUser($identifier, null, array_map(static fn (string $role): string => 'ROLE_'.strtoupper($role), $attributes['roles'] ?? []));
            }

            public function refreshUser(UserInterface $user): UserInterface
            {
                throw new UnsupportedUserException();
            }

            public function supportsClass(string $class): bool
            {
                return InMemoryUser::class === $class;
            }
        };

        $authenticator = $this->createAuthenticator(userProvider: $userProvider);
        $request = $this->createCallbackRequest($state, $nonce);

        $user = $authenticator->authenticate($request)->getUser();

        $this->assertInstanceOf(InMemoryUser::class, $user);
        $this->assertSame('user-42', $user->getUserIdentifier());
        $this->assertSame(['ROLE_ADMIN'], $user->getRoles());
        $this->assertSame('test@example.com', $userProvider->claims['email']);
    }

    public function testAuthenticateWithInvalidState()
    {
        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest('valid-state', 'nonce');
        $request->query->set('state', 'wrong-state');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsEmptyStateWhenNoneStored()
    {
        // login CSRF: an attacker-crafted callback with an empty "state" must not pass
        // when the victim's session holds no state (empty === empty would otherwise match)
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=attacker-code&state=');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateWithProviderError()
    {
        // the provider echoes the "state" back on an error response too (RFC 6749,
        // Section 4.1.2.1), which is what makes validating it first harmless
        $state = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?error=access_denied&error_description=User+denied+access&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => 'nonce',
            'code_verifier' => 'verifier',
        ]);
        $request->setSession($session);

        try {
            $authenticator->authenticate($request);
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertSame('OIDC provider returned an error: "User denied access"', $e->getMessage());
        }

        // the matched attempt was the only one, so the whole entry is removed
        $this->assertNull($session->get('_security.oidc_login.main.attempt.'.$state));
    }

    public function testAuthenticateValidatesTheStateBeforeTheProviderError()
    {
        // an unauthenticated request must not be able to get its own "error_description"
        // into the exception the failure handler stores in the victim's session
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?error=access_denied&error_description=Forged+message');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateMissingClaim()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['email' => 'test@example.com']);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('"sub" claim');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsNonStringUserInfoSub()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => ['x']]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('"sub" claim');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsUserInfoSubMismatch()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]); // sub => user-42

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn([
            'sub' => 'someone-else',
            'email' => 'test@example.com',
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not match the ID token');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateMissingAccessToken()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'id_token' => $idToken,
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('access_token');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateClearsSession()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);
        $authenticator->authenticate($request);

        $session = $request->getSession();
        $this->assertNull($session->get('_security.oidc_login.main.attempt.'.$state));
    }

    public function testOnAuthenticationSuccessClearsPendingAttempts()
    {
        $state1 = bin2hex(random_bytes(16));
        $state2 = bin2hex(random_bytes(16));
        $state3 = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state1, $nonce);

        $passport = $authenticator->authenticate($request);
        $token = $authenticator->createToken($passport, 'main');

        $session = $request->getSession();
        $prefix = '_security.oidc_login.main.';

        // pending attempts from other tabs
        $session->set($prefix.'attempt.'.$state2, ['nonce' => bin2hex(random_bytes(16)), 'code_verifier' => bin2hex(random_bytes(32))]);
        $session->set($prefix.'attempt.'.$state3, ['nonce' => bin2hex(random_bytes(16)), 'code_verifier' => bin2hex(random_bytes(32))]);

        $response = new RedirectResponse('https://example.com/success');
        $this->successHandler->method('onAuthenticationSuccess')->willReturn($response);

        $result = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame($response, $result);

        $attemptKeys = array_filter(array_keys($session->all()), static fn (string $key): bool => str_starts_with($key, $prefix.'attempt.'));
        $this->assertCount(0, $attemptKeys);
    }

    public function testStartRedirectsToProvider()
    {
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $authenticator->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $location = $response->getTargetUrl();
        $this->assertStringStartsWith('https://provider.example.com/authorize?', $location);

        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('test-client-id', $params['client_id']);
        $this->assertSame('openid', $params['scope']);
        $this->assertNotEmpty($params['state']);
        $this->assertNotEmpty($params['nonce']);
        $this->assertNotEmpty($params['code_challenge']);
        $this->assertSame('S256', $params['code_challenge_method']);
    }

    #[DataProvider('provideScopes')]
    public function testStartRequestsTheConfiguredScopes(array|string $scope, string $expectedScope)
    {
        $authenticator = $this->createAuthenticator(['scope' => $scope]);
        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $params = [];
        parse_str(parse_url($authenticator->start($request)->getTargetUrl(), \PHP_URL_QUERY), $params);

        $this->assertSame($expectedScope, $params['scope']);
    }

    public static function provideScopes(): iterable
    {
        yield 'a list' => [['profile', 'email'], 'openid profile email'];
        // an environment variable carries them all in a single value
        yield 'a space-separated string' => [['profile email'], 'openid profile email'];
        yield 'openid on its own' => [['openid'], 'openid'];
        yield 'openid is never requested twice' => [['openid', 'profile'], 'openid profile'];
    }

    public function testStartThrowsWhenAuthorizationEndpointIsMissing()
    {
        $this->discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'token_endpoint' => 'https://provider.example.com/token',
        ]);

        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not announce any "authorization_endpoint"');

        $this->createAuthenticator()->start($request);
    }

    public function testStartRejectsAnInsecureAuthorizationEndpoint()
    {
        $this->discovery = $this->createDiscovery([
            'issuer' => 'https://provider.example.com',
            'authorization_endpoint' => 'http://provider.example.com/authorize',
        ]);

        $request = Request::create('/protected');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        try {
            $this->createAuthenticator()->start($request);
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('must use HTTPS', $e->getMessage());
        }

        // the endpoint is resolved first, so no attempt was left behind in the session
        $this->assertSame([], array_filter(array_keys($session->all()), static fn (string $key): bool => str_starts_with($key, '_security.oidc_login.main.attempt.')));
    }

    public function testStartStoresStateNonceAndCodeVerifierInSession()
    {
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/protected');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $authenticator->start($request);
        $location = $response->getTargetUrl();

        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $attempt = $session->get('_security.oidc_login.main.attempt.'.$params['state']);
        $this->assertIsArray($attempt);
        $this->assertSame($params['nonce'], $attempt['nonce']);
        $this->assertNotNull($attempt['code_verifier']);
        // the challenge is BASE64URL(SHA256(verifier)) without padding, per RFC 7636, Section 4.2
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $attempt['code_verifier'], true)), '+/', '-_'), '='), $params['code_challenge']);
        // the very redirect URI sent to the provider is the one kept for the token request
        $this->assertSame($params['redirect_uri'], $attempt['redirect_uri']);
    }

    public function testStartWithoutPkce()
    {
        $authenticator = $this->createAuthenticator(['pkce_enabled' => false]);
        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $location = $authenticator->start($request)->getTargetUrl();
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertArrayNotHasKey('code_challenge', $params);
        $this->assertArrayNotHasKey('code_challenge_method', $params);
    }

    public function testStartWithPlainPkce()
    {
        $authenticator = $this->createAuthenticator(['pkce_method' => 'plain']);
        $request = Request::create('/protected');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $location = $authenticator->start($request)->getTargetUrl();
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame('plain', $params['code_challenge_method']);
        // with "plain", RFC 7636, Section 4.2 sends the verifier itself as the challenge
        $attempt = $session->get('_security.oidc_login.main.attempt.'.$params['state']);
        $this->assertSame($attempt['code_verifier'], $params['code_challenge']);
    }

    public function testAnUnknownPkceMethodIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid PKCE method "unknown"');

        $this->createAuthenticator(['pkce_method' => 'unknown']);
    }

    public function testStartForwardsAuthorizationParams()
    {
        $authenticator = $this->createAuthenticator(authorizationParams: ['prompt' => 'consent', 'ui_locales' => 'fr-FR']);
        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $location = $authenticator->start($request)->getTargetUrl();
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame('consent', $params['prompt']);
        $this->assertSame('fr-FR', $params['ui_locales']);
    }

    public function testManagedAuthorizationParamsAreRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The authorization request parameter(s) "state", "code_challenge" are managed by the authenticator');

        $this->createAuthenticator(authorizationParams: ['state' => 'fixed', 'code_challenge' => '', 'prompt' => 'consent']);
    }

    public function testStartSendsTheConfiguredMaxAge()
    {
        $authenticator = $this->createAuthenticator(['max_age' => 3600]);
        $request = Request::create('/protected');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $location = $authenticator->start($request)->getTargetUrl();
        $params = [];
        parse_str(parse_url($location, \PHP_URL_QUERY), $params);

        $this->assertSame('3600', $params['max_age']);
    }

    public function testAuthenticateEnforcesMaxAgeAgainstTheAuthTimeClaim()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce, 'auth_time' => time() - 600]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);

        $authenticator = $this->createAuthenticator(['max_age' => 300]);
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('max_age');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateAcceptsARecentAuthTimeWithinMaxAge()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce, 'auth_time' => time() - 30]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);

        $this->oidcClient->expects($this->once())
            ->method('fetchUserInfo')
            ->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator(['max_age' => 300]);
        $request = $this->createCallbackRequest($state, $nonce);

        $passport = $authenticator->authenticate($request);

        $this->assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());
    }

    public function testAuthenticatePassesCodeVerifierToExchangeCode()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->with('auth-code', $this->anything(), 'my-verifier')
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce, 'my-verifier');

        $authenticator->authenticate($request);
    }

    public function testTheStoredRedirectUriIsReusedAtTheTokenExchange()
    {
        // RFC 6749, Section 4.1.3: the token request must carry the very redirect URI the
        // authorization request used, so the callback request never gets to recompute it
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->expects($this->once())
            ->method('exchangeCode')
            ->with('auth-code', 'https://app.example.com/oidc/callback', $this->anything())
            ->willReturn([
                'access_token' => 'access-123',
                'id_token' => $idToken,
            ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => $nonce,
            'code_verifier' => 'my-verifier',
            'redirect_uri' => 'https://app.example.com/oidc/callback',
        ]);
        $request->setSession($session);

        $authenticator->authenticate($request);
    }

    public function testAuthenticateMissingIdToken()
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('id_token');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsNonStringIdToken()
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willReturn([
            'id_token' => ['x'],
            'access_token' => 'access-123',
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('id_token');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateRejectsNonStringAccessToken()
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'id_token' => $idToken,
            'access_token' => ['x'],
        ]);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('access_token');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateMissingAuthorizationCode()
    {
        $state = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => 'nonce',
            'code_verifier' => 'verifier',
        ]);
        $request->setSession($session);

        try {
            $authenticator->authenticate($request);
            $this->fail(\sprintf('Expected an "%s" to be thrown.', AuthenticationException::class));
        } catch (AuthenticationException $e) {
            $this->assertSame('Missing authorization code in OIDC callback.', $e->getMessage());
        }

        // The matched attempt is consumed even though there's no code
        $this->assertNull($session->get('_security.oidc_login.main.attempt.'.$state));
    }

    public function testCreateTokenStoresOidcTokens()
    {
        $nonce = bin2hex(random_bytes(16));
        $state = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce);
        $passport = $authenticator->authenticate($request);

        $token = $authenticator->createToken($passport, 'main');

        $this->assertSame($idToken, $token->getAttribute('oidc_id_token'));
        $this->assertSame('access-123', $token->getAttribute('oidc_access_token'));
    }

    public function testSessionClearedEvenWhenExchangeCodeFails()
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        $this->oidcClient->method('exchangeCode')->willThrowException(new \RuntimeException('Token exchange failed'));

        $authenticator = $this->createAuthenticator();
        $request = $this->createCallbackRequest($state, $nonce, 'verifier');

        try {
            $authenticator->authenticate($request);
        } catch (\RuntimeException) {
        }

        $session = $request->getSession();
        // The matched attempt was already consumed before the exchange, so it's gone
        $this->assertNull($session->get('_security.oidc_login.main.attempt.'.$state));
    }

    public function testConcurrentLoginsPreserveSeparateAttempts()
    {
        // Two tabs starting a login concurrently must not overwrite each other's state/nonce/verifier
        $state1 = bin2hex(random_bytes(16));
        $state2 = bin2hex(random_bytes(16));
        $nonce1 = bin2hex(random_bytes(16));
        $nonce2 = bin2hex(random_bytes(16));
        $codeVerifier1 = bin2hex(random_bytes(32));
        $codeVerifier2 = bin2hex(random_bytes(32));

        $authenticator = $this->createAuthenticator();
        $session = new Session(new MockArraySessionStorage());
        $prefix = '_security.oidc_login.main.';

        // Simulate two start() calls on the same session
        $session->set($prefix.'attempt.'.$state1, ['nonce' => $nonce1, 'code_verifier' => $codeVerifier1, 'redirect_uri' => 'http://localhost/oidc/callback']);
        $session->set($prefix.'attempt.'.$state2, ['nonce' => $nonce2, 'code_verifier' => $codeVerifier2, 'redirect_uri' => 'http://localhost/oidc/callback']);

        // First callback succeeds with its own state and nonce
        $idToken1 = $this->buildIdToken(['nonce' => $nonce1]);
        $this->oidcClient->method('exchangeCode')->willReturnOnConsecutiveCalls(
            ['access_token' => 'access-123', 'id_token' => $idToken1],
            ['access_token' => 'access-456', 'id_token' => $this->buildIdToken(['nonce' => $nonce2])]
        );
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $request1 = Request::create('/oidc/callback?code=auth-code-1&state='.$state1);
        $request1->setSession($session);

        $passport1 = $authenticator->authenticate($request1);
        $this->assertSame('user-42', $passport1->getBadge(UserBadge::class)->getUserIdentifier());

        $this->assertNull($session->get($prefix.'attempt.'.$state1));
        $attempt2 = $session->get($prefix.'attempt.'.$state2);
        $this->assertSame($nonce2, $attempt2['nonce']);
        $this->assertSame($codeVerifier2, $attempt2['code_verifier']);
    }

    public function testFifoCapRemovesOldestAttempts()
    {
        $authenticator = $this->createAuthenticator();
        $request = Request::create('/protected');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $prefix = '_security.oidc_login.main.';

        // Collect states from 6 start() calls (MAX_CONCURRENT_ATTEMPTS + 1)
        $states = [];
        for ($i = 0; $i < 6; ++$i) {
            $response = $authenticator->start($request);
            parse_str(parse_url($response->getTargetUrl(), \PHP_URL_QUERY), $params);
            $states[] = $params['state'];
        }

        // Session should only have MAX_CONCURRENT_ATTEMPTS (5) attempts
        $attemptKeys = array_filter(array_keys($session->all()), static fn (string $key): bool => str_starts_with($key, $prefix.'attempt.'));
        $this->assertCount(5, $attemptKeys);

        $this->assertNull($session->get($prefix.'attempt.'.$states[0]));

        $request2 = Request::create('/oidc/callback?code=auth-code&state='.$states[0]);
        $request2->setSession($session);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state parameter.');

        $authenticator->authenticate($request2);
    }

    public function testReplayProtectionFailsOnConsumedAttempt()
    {
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $idToken = $this->buildIdToken(['nonce' => $nonce]);

        $this->oidcClient->method('exchangeCode')->willReturn([
            'access_token' => 'access-123',
            'id_token' => $idToken,
        ]);
        $this->oidcClient->method('fetchUserInfo')->willReturn(['sub' => 'user-42']);

        $authenticator = $this->createAuthenticator();

        $session = new Session(new MockArraySessionStorage());
        $prefix = '_security.oidc_login.main.';
        $session->set($prefix.'attempt.'.$state, [
            'nonce' => $nonce,
            'code_verifier' => bin2hex(random_bytes(32)),
            'redirect_uri' => 'http://localhost/oidc/callback',
        ]);

        $request1 = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $request1->setSession($session);

        $authenticator->authenticate($request1);

        $request2 = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $request2->setSession($session);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OIDC state parameter.');

        $authenticator->authenticate($request2);
    }

    public function testMissingNonceInAttemptFails()
    {
        $state = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => '',
            'code_verifier' => 'verifier',
        ]);
        $request->setSession($session);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing OIDC nonce in session.');

        $authenticator->authenticate($request);
    }

    public function testProviderErrorPreservesOtherAttempts()
    {
        $state1 = bin2hex(random_bytes(16));
        $state2 = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state1, ['nonce' => 'nonce-1', 'code_verifier' => 'verifier-1']);
        $session->set('_security.oidc_login.main.attempt.'.$state2, ['nonce' => 'nonce-2', 'code_verifier' => 'verifier-2']);

        $request1 = Request::create('/oidc/callback?error=access_denied&state='.$state1);
        $request1->setSession($session);

        try {
            $authenticator->authenticate($request1);
            $this->fail('Expected AuthenticationException to be thrown.');
        } catch (AuthenticationException $e) {
            $this->assertStringContainsString('OIDC provider returned an error', $e->getMessage());
        }

        $this->assertNull($session->get('_security.oidc_login.main.attempt.'.$state1));
        $this->assertIsArray($session->get('_security.oidc_login.main.attempt.'.$state2));
    }

    public function testInvalidStateErrorMessageSameForEmptySessionAndUnknownState()
    {
        $authenticator = $this->createAuthenticator();

        // an empty session and an unknown state must fail with the very same message,
        // so the response does not reveal whether a login was pending
        $request1 = Request::create('/oidc/callback?code=attacker-code&state=forged');
        $request1->setSession(new Session(new MockArraySessionStorage()));

        try {
            $authenticator->authenticate($request1);
            $this->fail('Expected AuthenticationException to be thrown.');
        } catch (AuthenticationException $e) {
            $this->assertSame('Invalid OIDC state parameter.', $e->getMessage());
        }

        $request2 = Request::create('/oidc/callback?code=attacker-code&state=forged');
        $session2 = new Session(new MockArraySessionStorage());
        $session2->set('_security.oidc_login.main.attempt.some-other-state', ['nonce' => 'nonce', 'code_verifier' => 'verifier']);
        $request2->setSession($session2);

        try {
            $authenticator->authenticate($request2);
            $this->fail('Expected AuthenticationException to be thrown.');
        } catch (AuthenticationException $e) {
            $this->assertSame('Invalid OIDC state parameter.', $e->getMessage());
        }
    }

    public function testMissingCodeVerifierInAttemptFails()
    {
        $state = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => 'nonce',
            'code_verifier' => '',
        ]);
        $request->setSession($session);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing PKCE code verifier in session.');

        $authenticator->authenticate($request);
    }

    public function testMissingRedirectUriInAttemptFails()
    {
        $state = bin2hex(random_bytes(16));

        $authenticator = $this->createAuthenticator();
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $session->set('_security.oidc_login.main.attempt.'.$state, [
            'nonce' => 'nonce',
            'code_verifier' => 'verifier',
            'redirect_uri' => '',
        ]);
        $request->setSession($session);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Missing OIDC redirect URI in session.');

        $authenticator->authenticate($request);
    }

    private function createCallbackRequest(string $state, string $nonce, ?string $codeVerifier = null): Request
    {
        $request = Request::create('/oidc/callback?code=auth-code&state='.$state);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $prefix = '_security.oidc_login.main.';
        $session->set($prefix.'attempt.'.$state, [
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier ?? bin2hex(random_bytes(32)),
            'redirect_uri' => 'http://localhost/oidc/callback',
        ]);

        return $request;
    }

    private function buildIdToken(array $extraClaims = []): string
    {
        $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode($this->buildIdTokenClaims($extraClaims))), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode('fake-signature'), '+/', '-_'), '=');

        return $header.'.'.$payload.'.'.$signature;
    }

    private function buildIdTokenClaims(array $extraClaims = []): array
    {
        return array_merge([
            'iss' => 'https://provider.example.com',
            'aud' => 'test-client-id',
            'sub' => 'user-42',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $extraClaims);
    }

    /**
     * An ID token really signed with the key the provider publishes below.
     */
    private function buildSignedIdToken(array $extraClaims = []): string
    {
        return (new CompactSerializer())->serialize(
            (new JWSBuilder(new AlgorithmManager([new ES256()])))
                ->withPayload(json_encode($this->buildIdTokenClaims($extraClaims)))
                // tip: use https://mkjwk.org/ to generate a JWK
                ->addSignature(new JWK([
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                    'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                    'd' => 'iA_TV2zvftni_9aFAQwFO_9aypfJFCSpcCyevDvz220',
                ]), ['alg' => 'ES256', 'kid' => 'signing-key'])
                ->build()
        );
    }

    /**
     * The very same claims, signed with nothing at all.
     */
    private function buildForgedIdToken(array $extraClaims = []): string
    {
        [$header, $payload] = explode('.', $this->buildSignedIdToken($extraClaims));

        return $header.'.'.$payload.'.'.rtrim(strtr(base64_encode('forged-signature'), '+/', '-_'), '=');
    }

    /**
     * The "alg": "none" token of RFC 7519, Section 6: a JWS with no signature at all.
     */
    private function buildUnsecuredIdToken(array $extraClaims = []): string
    {
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');

        return $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode($this->buildIdTokenClaims($extraClaims)).'.';
    }

    private function createSignatureVerifier(): OidcSignatureVerifier
    {
        return new OidcSignatureVerifier(
            $this->discovery,
            new ArrayAdapter(),
            new MockHttpClient(new JsonMockResponse(['keys' => [[
                'kid' => 'signing-key',
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => '0QEAsI1wGI-dmYatdUZoWSRWggLEpyzopuhwk-YUnA4',
                'y' => 'KYl-qyZ26HobuYwlQh-r0iHX61thfP82qqEku7i0woo',
                'use' => 'sig',
                'alg' => 'ES256',
            ]]])),
            ['ES256'],
        );
    }

    private function createAuthenticator(array $options = [], ?UserProviderInterface $userProvider = null, array $authorizationParams = [], ?OidcSignatureVerifier $signatureVerifier = null): OidcLoginAuthenticator
    {
        return new OidcLoginAuthenticator(
            new HttpUtils(),
            $userProvider ?? new OidcUserProvider(),
            $this->oidcClient,
            $this->discovery,
            new OidcIdToken(new Clock()),
            'test-client-id',
            $this->successHandler,
            $this->failureHandler,
            $options,
            $authorizationParams,
            $signatureVerifier,
        );
    }
}
