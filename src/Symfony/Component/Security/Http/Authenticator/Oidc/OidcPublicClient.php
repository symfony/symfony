<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

/**
 * OIDC client for public clients (holding no client secret).
 *
 * A public client cannot keep a secret confidential (RFC 6749 §2.1), so it sends
 * no credentials at the token endpoint: it is identified by the `client_id` the
 * authorization code was issued to, which the token request always carries. This
 * is the `token_endpoint_auth_method` value `none` of RFC 7591 §2.
 *
 * Nothing else then binds the authorization code to this client, so PKCE
 * (RFC 7636) is what protects the exchange: a code intercepted on the redirect
 * cannot be redeemed without the code verifier. This client therefore refuses to
 * exchange a code that is not protected by PKCE.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-2.1 OAuth 2.0 client types
 * @see https://datatracker.ietf.org/doc/html/rfc7636             PKCE (RFC 7636)
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class OidcPublicClient extends OidcClient
{
    protected function applyClientAuthentication(array $body, array $options): array
    {
        if ('' === ($body['code_verifier'] ?? '')) {
            throw new \LogicException('A public OIDC client must exchange the authorization code with PKCE, as it sends no client secret. Pass the code verifier to "exchangeCode()", or use a confidential client.');
        }

        $options['body'] = $body;

        return $options;
    }
}
