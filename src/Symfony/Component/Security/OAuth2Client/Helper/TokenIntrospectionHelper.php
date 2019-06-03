<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Helper;

use Symfony\Component\Security\OAuth2Client\Token\IntrospectedToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://tools.ietf.org/html/rfc7662
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TokenIntrospectionHelper
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function introspecte(string $introspectionEndpointURI, string $token, array $headers = [], array $extraQuery = [], string $tokenTypeHint = null, string $method = 'POST'): IntrospectedToken
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $defaultQuery = ['token' => $token];

        if ($tokenTypeHint) {
            $defaultQuery['token_type_hint'] = $tokenTypeHint;
        }

        $finalHeaders = array_unique(array_merge($defaultHeaders, $headers));
        $finalQuery = array_unique(array_merge($defaultQuery, $extraQuery));

        $response = $this->client->request($method, $introspectionEndpointURI, [
            'headers' => $finalHeaders,
            'query' => $finalQuery,
        ]);

        $body = $response->toArray();

        return new IntrospectedToken($body);
    }
}
