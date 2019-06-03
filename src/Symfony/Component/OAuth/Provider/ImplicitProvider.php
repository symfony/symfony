<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OAuth\Provider;

use Symfony\Component\OAuth\Token\ImplicitGrantToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ImplicitProvider extends GenericProvider
{
    /**
     * The ImplicitGrantProvider cannot fetch an Authorization code
     * as described in https://tools.ietf.org/html/rfc6749#section-4.2.
     */
    public function fetchAuthorizationCode(array $options, array $headers = [], string $method = 'GET')
    {
        throw new \RuntimeException(\sprintf(
            'The %s doesn\'t support the authorization code, please refer to https://tools.ietf.org/html/rfc6749#section-4.2',
            self::class
        ));
    }

    /**
     * {@inheritdoc}
     *
     * The following options: redirect_uri, scope and state are optional or recommended https://tools.ietf.org/html/rfc6749#section-4.2
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET')
    {
        $query = [
            'response_type' => 'token',
            'client_id' => $this->options['client_id'],
        ];

        if (isset($options['redirect_uri'])) {
            $query['redirect_uri'] = $options['redirect_uri'];
        }

        if (isset($options['scope'])) {
            $query['scope'] = $options['scope'];
        }

        if (isset($options['state'])) {
            $query['state'] = $options['state'];
        }

        $defaultHeaders = ['Content-Type' => 'application/x-www-form-urlencoded'];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);
        $finalQuery = $this->mergeRequestArguments($query, $options);

        $response = $this->client->request($method, $this->options['accessToken_url'], [
            'headers' => $finalHeaders,
            'query' => $finalQuery,
        ]);

        $matches = $this->parseResponse($response);

        return new ImplicitGrantToken($matches);
    }
}
