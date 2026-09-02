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

use Symfony\Component\Security\Http\Oidc\OidcDiscovery;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OIDC client for confidential clients (holding a client secret).
 *
 * Authenticates at the token endpoint using either `client_secret_post` (secret
 * in the request body) or `client_secret_basic` (HTTP Basic Auth), per RFC 6749 §2.3.1.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class OidcConfidentialClient extends OidcClient
{
    public function __construct(
        HttpClientInterface $httpClient,
        OidcDiscovery $discovery,
        string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly string $tokenEndpointAuthMethod = 'client_secret_post',
    ) {
        if (!\in_array($tokenEndpointAuthMethod, ['client_secret_post', 'client_secret_basic'], true)) {
            throw new \InvalidArgumentException(\sprintf('A confidential OIDC client authenticates with "client_secret_post" or "client_secret_basic", "%s" given.', $tokenEndpointAuthMethod));
        }

        parent::__construct($httpClient, $discovery, $clientId);
    }

    protected function applyClientAuthentication(array $body, array $options): array
    {
        if ('client_secret_basic' === $this->tokenEndpointAuthMethod) {
            // RFC 6749 Section 2.3.1: the client id and the secret are each form-urlencoded
            // before being combined into the HTTP Basic credentials, so that a colon, a
            // percent sign or a non-ASCII byte in either of them survives the round trip
            $options['auth_basic'] = urlencode($this->clientId).':'.urlencode($this->clientSecret);
            $options['body'] = $body;

            return $options;
        }

        $body['client_secret'] = $this->clientSecret;
        $options['body'] = $body;

        return $options;
    }
}
