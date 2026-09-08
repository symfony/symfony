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
 * Sends the client identifier and secret as HTTP Basic credentials.
 *
 * This is the "client_secret_basic" method of RFC 6749, Section 2.3.1, the one the RFC
 * requires providers to support and recommends clients to use.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6749#section-2.3.1
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
final class ClientSecretBasic implements ClientAuthenticationInterface
{
    public function __construct(
        #[\SensitiveParameter] private readonly string $clientSecret,
    ) {
        if ('' === $clientSecret) {
            throw new \InvalidArgumentException('The OAuth2 client secret cannot be empty. Use "NoClientAuthentication" to declare a public client, which holds no secret.');
        }
    }

    /**
     * RFC 6749, Section 2.3.1: the client identifier and the secret are each
     * form-urlencoded before being combined into the HTTP Basic credentials, so that a
     * colon, a percent sign or a non-ASCII byte in either of them survives the round trip.
     */
    public function authenticate(string $clientId, string $tokenEndpoint, array $options): array
    {
        $options['auth_basic'] = urlencode($clientId).':'.urlencode($this->clientSecret);

        return $options;
    }

    public function getMethod(): string
    {
        return 'client_secret_basic';
    }
}
