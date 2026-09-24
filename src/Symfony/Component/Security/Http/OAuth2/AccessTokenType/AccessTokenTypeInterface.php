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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The type of access token a client asks for, and knows how to use.
 *
 * RFC 6749, Section 7.1: "The client MUST NOT use an access token if it does not understand
 * the token type." The type is therefore not a detail of one request but a property of the
 * client, which decides three things at once: what the provider must answer in the
 * "token_type" of the access token response (Section 5.1), what a request presenting the
 * token looks like, and what the client has to prove along the way.
 *
 * One implementation per type, so that the client asks the type rather than asking which
 * type it is: {@see BearerTokenType} for the bearer token of RFC 6750, which is presented
 * as it is, {@see DpopTokenType} for a token bound to a key the client holds (RFC 9449),
 * which is presented with a signature over the very request. A type added later is a class
 * added, and nothing that uses a token has to learn its name.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-7.1 Access token types
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
interface AccessTokenTypeInterface
{
    /**
     * The "token_type" this type understands, as RFC 6749, Section 5.1 has the provider
     * answer it, and Section 11.1 registers it.
     *
     * The value is case insensitive, and this is the spelling the specification writes.
     */
    public function getTokenType(): string;

    /**
     * Checks what the token endpoint answered before any of it is used.
     *
     * A token of another type is one this client cannot present, and one whose type carries
     * a protection this client asked for is one it must not silently do without.
     *
     * @param array<string, mixed> $tokenResponse
     *
     * @throws AuthenticationException When the response holds no token this type can use
     */
    public function checkTokenResponse(array $tokenResponse): void;

    /**
     * The options of a request made to the authorization server itself.
     *
     * No access token exists yet at the token endpoint, which does not mean there is nothing
     * to prove: this is where a type binding the token to a key signs for it.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function prepareTokenRequest(string $url, array $options): array;

    /**
     * The options of a request presenting the access token to a protected resource.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function presentToken(#[\SensitiveParameter] string $accessToken, string $method, string $url, array $options): array;

    /**
     * Reads what the response named and says whether the request is to be made again.
     *
     * A server may answer a first request with what the next one has to carry, a DPoP nonce
     * being the one case today (RFC 9449, Section 8). Returning true asks the caller to build
     * and send the request once more; returning false leaves the response to be read as it is,
     * including when it is an error.
     */
    public function onResponse(ResponseInterface $response, string $url): bool;
}
