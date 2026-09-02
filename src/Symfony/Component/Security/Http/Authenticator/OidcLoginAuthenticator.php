<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcClient;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcIdToken;
use Symfony\Component\Security\Http\Authenticator\Oidc\OidcSignatureVerifier;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Oidc\OidcDiscovery;

/**
 * Authenticator for the OpenID Connect Authorization Code Flow.
 *
 * @see https://openid.net/specs/openid-connect-core-1_0.html#CodeFlowAuth
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class OidcLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    private const MAX_CONCURRENT_ATTEMPTS = 5;

    private array $options;

    /**
     * @param OidcSignatureVerifier|null $signatureVerifier Verifies the ID token signature against the provider JWKS, or null
     *                                                      to decode the token without verifying it, which OIDC Core 1.0,
     *                                                      Section 3.1.3.7, item 6 only allows as long as the token endpoint
     *                                                      request verifies TLS
     */
    public function __construct(
        private readonly HttpUtils $httpUtils,
        private readonly UserProviderInterface $userProvider,
        private readonly OidcClient $oidcClient,
        private readonly OidcDiscovery $discovery,
        private readonly OidcIdToken $idToken,
        private readonly string $clientId,
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
        array $options,
        private readonly ?OidcSignatureVerifier $signatureVerifier = null,
    ) {
        $this->options = array_merge([
            'check_path' => '/oidc/callback',
            'firewall_name' => 'main',
            'scope' => ['openid'],
        ], $options);
    }

    public function supports(Request $request): bool
    {
        // every request on the callback path is handled here: the route declared for it
        // carries no controller, so one passing through would end up reported as a routing
        // error instead of the authentication failure it is
        return $this->httpUtils->checkRequestPath($request, $this->options['check_path']);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $session = $this->getSession($request);
        $prefix = $this->getSessionPrefix();

        // both resolved before anything is stored in the session, so that a provider
        // announcing no usable authorization endpoint, or a "check_path" given as a route
        // name with no URL generator to resolve it, does not leave any attempt behind
        $authorizationEndpoint = $this->discovery->getSecureEndpoint('authorization_endpoint');
        $redirectUri = $this->httpUtils->generateUri($request, $this->options['check_path']);

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $codeVerifier = $this->generateCodeVerifier();

        // each pending attempt lives under its own session key, carrying the state, so
        // that concurrent logins started from several tabs write distinct entries instead
        // of rewriting a shared one; the count is capped, oldest attempts dropped first,
        // so that an unauthenticated visitor cannot grow the session unbounded
        // (spring-security's unbounded per-state storage was CVE-2021-22119)
        $attemptKeys = $this->getAttemptKeys($session);
        while (\count($attemptKeys) >= self::MAX_CONCURRENT_ATTEMPTS) {
            $session->remove(array_shift($attemptKeys));
        }

        $session->set($prefix.'attempt.'.$state, [
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
        ]);

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->getScopes()),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];

        $authorizationUrl = $authorizationEndpoint
            .(str_contains($authorizationEndpoint, '?') ? '&' : '?')
            .http_build_query($params, '', '&', \PHP_QUERY_RFC3986);

        return new RedirectResponse($authorizationUrl);
    }

    public function authenticate(Request $request): Passport
    {
        $session = $this->getSession($request);
        $prefix = $this->getSessionPrefix();

        // the "state" is validated first: an unauthenticated request would otherwise get an
        // attacker-supplied "error_description" stored in the session, through the exception
        // the failure handler keeps there (the provider echoes "state" back on errors too,
        // as RFC 6749, Section 4.1.2.1 requires)
        $state = $request->query->get('state');
        if (!\is_string($state) || '' === $state) {
            throw new AuthenticationException('Invalid OIDC state parameter.');
        }

        // the attempt is looked up with hash_equals() over the stored keys, so the
        // attacker-supplied value is never used as a session key, and an unknown state
        // fails with the same message as an empty session (no oracle, no login CSRF)
        $expectedKey = $prefix.'attempt.'.$state;
        $matchedKey = null;

        foreach ($this->getAttemptKeys($session) as $key) {
            if (hash_equals($key, $expectedKey)) {
                $matchedKey = $key;
                break;
            }
        }

        if (null === $matchedKey) {
            throw new AuthenticationException('Invalid OIDC state parameter.');
        }

        // the matched attempt is consumed right away, whatever the outcome: a replayed
        // callback fails on the state check, and the other pending attempts survive
        $attempt = $session->get($matchedKey);
        $session->remove($matchedKey);

        $nonce = \is_array($attempt) ? $attempt['nonce'] ?? null : null;
        $codeVerifier = \is_array($attempt) ? $attempt['code_verifier'] ?? null : null;
        $redirectUri = \is_array($attempt) ? $attempt['redirect_uri'] ?? null : null;

        $this->checkForProviderError($request);

        $code = $request->query->get('code');
        if (null === $code) {
            throw new AuthenticationException('Missing authorization code in OIDC callback.');
        }

        // the nonce is mandatory in this flow, since start() always sends one: requiring it
        // here means the check of the "nonce" claim in the ID token can never be skipped
        if (!\is_string($nonce) || '' === $nonce) {
            throw new AuthenticationException('Missing OIDC nonce in session.');
        }

        // same for the PKCE verifier: exchanging the code without it would downgrade PKCE
        if (!\is_string($codeVerifier) || '' === $codeVerifier) {
            throw new AuthenticationException('Missing PKCE code verifier in session.');
        }

        // and the redirect URI resolved when the flow started: RFC 6749, Section 4.1.3
        // requires the token request to carry the very value the authorization request
        // used, so it is replayed from the attempt instead of being recomputed from the
        // callback request, whose host is not necessarily the one the flow started on
        if (!\is_string($redirectUri) || '' === $redirectUri) {
            throw new AuthenticationException('Missing OIDC redirect URI in session.');
        }

        $tokenData = $this->exchangeAuthorizationCode($redirectUri, $code, $codeVerifier);

        // the signature is verified before the claims are read, so that a token the
        // provider did not issue never reaches the claim validation at all
        if (null !== $this->signatureVerifier) {
            $idTokenClaims = $this->signatureVerifier->verify($tokenData['id_token']);
        } else {
            $idTokenClaims = $this->idToken->decode($tokenData['id_token']);
        }

        $this->idToken->validateClaims(
            $idTokenClaims,
            $this->discovery->getConfiguration()['issuer'] ?? '',
            $this->clientId,
            $nonce,
        );

        $claims = $this->fetchUserClaims($tokenData['access_token'], $idTokenClaims);

        // The user is loaded by the firewall's user provider, from the verified "sub"
        // claim and with every claim passed as badge attributes: a provider implementing
        // AttributesBasedUserProviderInterface receives them, which is where mapping
        // claims onto roles belongs. The built-in "oidc" provider builds a self-contained
        // OidcUser, without letting a claim define the identity or grant any role.
        $passport = new SelfValidatingPassport(
            new UserBadge($claims['sub'], $this->userProvider->loadUserByIdentifier(...), $claims),
        );
        $passport->setAttribute('oidc_token_data', $tokenData);

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());

        $tokenData = $passport->getAttribute('oidc_token_data');
        if (\is_array($tokenData)) {
            $token->setAttribute('oidc_id_token', $tokenData['id_token'] ?? null);
            $token->setAttribute('oidc_access_token', $tokenData['access_token'] ?? null);
        }

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // each pending attempt carries a nonce and a PKCE verifier, which have no
        // place in an authenticated session; a callback for one of them would
        // re-authenticate anyway, the state check just fails it earlier
        $session = $this->getSession($request);
        foreach ($this->getAttemptKeys($session) as $key) {
            $session->remove($key);
        }

        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return true;
    }

    private function checkForProviderError(Request $request): void
    {
        $error = $request->query->get('error');
        if (null !== $error) {
            $description = $request->query->get('error_description', $error);

            // only the matched attempt was consumed: a provider error for one tab
            // must not cancel the logins pending in the others
            throw new AuthenticationException(\sprintf('OIDC provider returned an error: "%s"', $description));
        }
    }

    /**
     * Exchanges the authorization code for tokens and ensures the token endpoint
     * returned an ID and access token.
     *
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCode(string $redirectUri, string $code, string $codeVerifier): array
    {
        $tokenData = $this->oidcClient->exchangeCode($code, $redirectUri, $codeVerifier);

        if (!\is_string($tokenData['id_token'] ?? null) || '' === $tokenData['id_token']) {
            throw new AuthenticationException('The token endpoint response does not contain a valid "id_token".');
        }
        if (!\is_string($tokenData['access_token'] ?? null) || '' === $tokenData['access_token']) {
            throw new AuthenticationException('The token endpoint response does not contain a valid "access_token".');
        }

        return $tokenData;
    }

    /**
     * Fetches user claims from the UserInfo endpoint and enforces the OIDC Core
     * 1.0, Section 5.3.2 rule that its "sub" matches the ID token "sub".
     *
     * @param array<string, mixed> $idTokenClaims
     *
     * @return array<string, mixed>
     */
    private function fetchUserClaims(string $accessToken, array $idTokenClaims): array
    {
        $claims = $this->oidcClient->fetchUserInfo($accessToken);

        if (!\is_string($claims['sub'] ?? null) || '' === $claims['sub']) {
            throw new AuthenticationException('The "sub" claim is missing or invalid in the OIDC response.');
        }

        if (!\is_string($idTokenClaims['sub'] ?? null) || !hash_equals($idTokenClaims['sub'], $claims['sub'])) {
            throw new AuthenticationException('The "sub" claim from the UserInfo endpoint does not match the ID token.');
        }

        return $claims;
    }

    /**
     * Returns the scopes of the authorization request, always including "openid",
     * which OIDC Core 1.0, Section 3.1.2.1 requires for the request to return an
     * ID token. Each configured value may hold several space-separated scopes, so
     * that an environment variable can carry them all.
     *
     * @return list<string>
     */
    private function getScopes(): array
    {
        $scopes = ['openid'];
        foreach ((array) $this->options['scope'] as $scope) {
            foreach (preg_split('/\s+/', (string) $scope, -1, \PREG_SPLIT_NO_EMPTY) as $value) {
                $scopes[] = $value;
            }
        }

        return array_values(array_unique($scopes));
    }

    private function generateCodeVerifier(): string
    {
        // A 32-byte (256-bit) verifier, hex-encoded to 64 characters, as recommended
        // by RFC 7636 Appendix B. The S256 challenge below is the only base64url step.
        return bin2hex(random_bytes(32));
    }

    private function getSession(Request $request): SessionInterface
    {
        if (!$request->hasSession()) {
            throw new \LogicException('The "oidc_login" authenticator stores the OIDC "state", "nonce" and PKCE code verifier in the session, which this request has none of: it cannot be used with the session disabled, nor on a stateless firewall.');
        }

        return $request->getSession();
    }

    /**
     * Returns the session keys of the pending attempts, oldest first.
     *
     * @return list<string>
     */
    private function getAttemptKeys(SessionInterface $session): array
    {
        $attemptPrefix = $this->getSessionPrefix().'attempt.';

        $keys = [];
        foreach (array_keys($session->all()) as $key) {
            // a numeric session attribute name comes back as an int key
            if (str_starts_with((string) $key, $attemptPrefix)) {
                $keys[] = (string) $key;
            }
        }

        return $keys;
    }

    private function getSessionPrefix(): string
    {
        return '_security.oidc_login.'.$this->options['firewall_name'].'.';
    }
}
