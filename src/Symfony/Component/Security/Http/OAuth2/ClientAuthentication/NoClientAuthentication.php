<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\OAuth2\ClientAuthentication;

/**
 * Sends no client credentials, for a public client.
 *
 * A public client cannot keep a secret confidential (RFC 6749, Section 2.1), so it is
 * only identified by the "client_id" the token request always carries. This is the
 * "none" method of RFC 7591, Section 2.
 *
 * Nothing else then binds the authorization code to the client, so PKCE (RFC 7636) is
 * what protects the exchange: a code intercepted on the redirect cannot be redeemed
 * without the code verifier. Exchanging a code without one is therefore refused here.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-2.1 OAuth 2.0 client types
 * @see https://datatracker.ietf.org/doc/html/rfc7636             PKCE
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class NoClientAuthentication implements ClientAuthenticationInterface
{
    /**
     * The refresh token grant of RFC 6749, Section 6 carries neither a code nor a verifier,
     * so it is the one grant exempted here; every other one is refused without a verifier,
     * so that a grant added later never reaches the token endpoint unprotected.
     */
    public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        $body = $options['body'] ?? [];

        if ('refresh_token' !== ($body['grant_type'] ?? null) && '' === ($body['code_verifier'] ?? '')) {
            throw new \LogicException('A public OAuth2 client must exchange the authorization code with PKCE, as it sends no client secret. Pass the code verifier to "exchangeCode()", or authenticate the client with a secret.');
        }

        return $options;
    }

    public function getMethod(): string
    {
        return 'none';
    }
}
