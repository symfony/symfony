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
 * Authenticates at the token endpoint using `client_secret_post` (secret in the
 * request body), per RFC 6749 §2.3.1.
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
    ) {
        parent::__construct($httpClient, $discovery, $clientId);
    }

    protected function applyClientAuthentication(array $body, array $options): array
    {
        $body['client_secret'] = $this->clientSecret;
        $options['body'] = $body;

        return $options;
    }
}
