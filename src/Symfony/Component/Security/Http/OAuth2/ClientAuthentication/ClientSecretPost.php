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
 * Sends the client secret in the body of the token request.
 *
 * This is the "client_secret_post" method of RFC 6749, Section 2.3.1, which the RFC
 * itself only tolerates: it recommends "client_secret_basic" instead. Use it for the
 * providers that support nothing else.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-2.3.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class ClientSecretPost implements ClientAuthenticationInterface
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $clientSecret,
    ) {
        if ('' === $clientSecret) {
            throw new \InvalidArgumentException('The OAuth2 client secret cannot be empty. Use "NoClientAuthentication" to declare a public client, which holds no secret.');
        }
    }

    public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        $options['body']['client_secret'] = $this->clientSecret;

        return $options;
    }

    public function getMethod(): string
    {
        return 'client_secret_post';
    }
}
