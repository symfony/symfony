<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\AccessTokenType;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\OAuth2\Dpop\DpopProofFactory;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * An access token bound to a key the client holds, RFC 9449.
 *
 * Every request carries a proof signed for it, and the token is presented under the "DPoP"
 * scheme rather than "Bearer" (Section 7.1): a token taken off the wire is of no use to
 * whoever cannot sign for the request it is presented in.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9449 OAuth 2.0 Demonstrating Proof of Possession (DPoP)
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class DpopTokenType implements AccessTokenTypeInterface
{
    public const TOKEN_TYPE = 'DPoP';

    /**
     * The error a server answers when it wants the next proof to carry a nonce of its own,
     * Section 8: in the body of a token endpoint error, in the challenge of a resource.
     */
    private const NONCE_ERROR = 'use_dpop_nonce';

    /**
     * The nonce each server last named, by the origin that named it.
     *
     * Section 9: a nonce is only accepted by the server that issued it, and the provider and
     * a protected resource are not the same server. Keeping one value for both would send
     * each of them the other's nonce and cost a round trip every time they alternate.
     *
     * @var array<string, string>
     */
    private array $nonces = [];

    public function __construct(
        private readonly DpopProofFactory $proofFactory,
    ) {
    }

    public function getTokenType(): string
    {
        return self::TOKEN_TYPE;
    }

    /**
     * Refuses a response that did not bind the token.
     *
     * Section 5: "A token_type of DPoP MUST be included in the access token response to
     * signal to the client that the access token was bound to its DPoP key", and a provider
     * "MAY elect to issue access tokens that are not DPoP bound, which is signaled to the
     * client with a value of Bearer". The client then "MUST discard the response [...] if
     * this protection is deemed important for the security of the application": holding a
     * key and signing for every request is what deems it important, so the response is
     * refused rather than used unbound.
     */
    public function checkTokenResponse(array $tokenResponse): void
    {
        $tokenType = $tokenResponse['token_type'] ?? null;

        if (!\is_string($tokenType) || 0 !== strcasecmp(self::TOKEN_TYPE, $tokenType)) {
            throw new AuthenticationException(\sprintf('The OIDC provider answered the token request with a "token_type" of "%s", so it did not bind the access token to the key of this client (RFC 9449, Section 5). Register the client for DPoP at the provider, or stop configuring a DPoP key for this firewall.', \is_string($tokenType) ? $tokenType : get_debug_type($tokenType)));
        }
    }

    public function prepareTokenRequest(string $url, array $options): array
    {
        $options['headers']['DPoP'] = $this->proofFactory->createProof('POST', $url, null, $this->nonces[self::origin($url)] ?? null);

        return $options;
    }

    public function presentToken(#[\SensitiveParameter] string $accessToken, string $method, string $url, array $options): array
    {
        $options['headers']['Authorization'] = self::TOKEN_TYPE.' '.$accessToken;
        $options['headers']['DPoP'] = $this->proofFactory->createProof($method, $url, $accessToken, $this->nonces[self::origin($url)] ?? null);

        return $options;
    }

    /**
     * Records the nonce the server named, and says whether the request is to be made again.
     *
     * A server names its nonce in a header of the refusal, and of any later response, so the
     * value is kept for the requests that follow instead of being asked for again. Sending
     * the same proof a second time would be refused for the same reason, so the request is
     * only repeated once the server has named a nonce this client had not already used for it.
     */
    public function onResponse(ResponseInterface $response, string $url): bool
    {
        $origin = self::origin($url);
        $previous = $this->nonces[$origin] ?? null;
        $nonce = $response->getHeaders(false)[DpopProofFactory::NONCE_HEADER][0] ?? null;

        if (\is_string($nonce) && '' !== $nonce) {
            $this->nonces[$origin] = $nonce;
        }

        return self::wantsNonce($response)
            && isset($this->nonces[$origin])
            && $this->nonces[$origin] !== $previous;
    }

    /**
     * The scheme, host and port of a URL, which is what identifies the server that issued a
     * nonce: one origin is one server, and the path it is reached at is not.
     */
    private static function origin(string $url): string
    {
        $parts = parse_url($url);

        if (!\is_array($parts)) {
            // a URL the provider announced and this client made a request to, so it parses;
            // keying on the whole of it is what keeps a nonce from reaching another server
            return $url;
        }

        return ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private static function wantsNonce(ResponseInterface $response): bool
    {
        try {
            $statusCode = $response->getStatusCode();
        } catch (HttpClientExceptionInterface) {
            return false;
        }

        if (400 !== $statusCode && 401 !== $statusCode) {
            return false;
        }

        // the token endpoint says it in the error of a JSON body, a resource such as the
        // UserInfo endpoint in the challenge it answers with
        if (str_contains(implode(' ', $response->getHeaders(false)['www-authenticate'] ?? []), self::NONCE_ERROR)) {
            return true;
        }

        try {
            return self::NONCE_ERROR === ($response->toArray(false)['error'] ?? null);
        } catch (HttpClientExceptionInterface) {
            // an error page rather than the JSON the endpoint owes, which says nothing
            // about a nonce and is reported by the caller reading the response
            return false;
        }
    }
}
