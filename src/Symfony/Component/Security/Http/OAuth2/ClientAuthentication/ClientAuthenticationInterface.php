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
 * How an OAuth 2.0 client authenticates itself at the token endpoint.
 *
 * A client registration carries exactly one such method, which RFC 7591, Section 2
 * names in its "token_endpoint_auth_method" metadata: a secret sent in the request
 * body or as HTTP Basic credentials, nothing at all for a public client, or a signed
 * assertion (OIDC Core 1.0, Section 9). It is a property of the registration and not
 * of the grant, so the same method authenticates every request to that endpoint.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-2.3 Client authentication
 * @see https://datatracker.ietf.org/doc/html/rfc7591#section-2   Client metadata
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface ClientAuthenticationInterface
{
    /**
     * Applies the client authentication scheme to a token endpoint request.
     *
     * A scheme is free to sign the request body, to move part of it into a header, or to
     * leave it untouched: implementations get the HttpClient options the request is about
     * to be made with, the body among them under the "body" key, and return the ones they
     * want it made with instead.
     *
     * @param string               $clientId      The client the request is made for
     * @param string               $tokenEndpoint The endpoint the request is made to, which a signed
     *                                            assertion names as its audience
     * @param array<string, mixed> $options       The HttpClient options the request is about to be
     *                                            made with, carrying the token request body under
     *                                            the "body" key
     *
     * @return array<string, mixed> The final HttpClient options
     */
    public function authenticate(string $clientId, string $tokenEndpoint, array $options): array;

    /**
     * Returns the RFC 7591, Section 2 name of the method, e.g. "client_secret_basic".
     *
     * This is the value the client is registered with at the provider, and the one the
     * provider announces support for in the "token_endpoint_auth_methods_supported" of
     * its metadata.
     *
     * It is also read to make a security decision, and compared exactly: an implementation
     * that authenticates with nothing must return "none" and no variation of it, or the
     * client it belongs to silently escapes the rules a public client cannot bend, namely
     * that it can turn off neither PKCE nor the ID token signature check.
     */
    public function getMethod(): string;
}
