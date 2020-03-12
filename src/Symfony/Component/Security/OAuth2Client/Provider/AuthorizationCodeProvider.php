<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Provider;

use Symfony\Component\Security\OAuth2Client\Authorization\AuthorizationCodeResponse;
use Symfony\Component\Security\OAuth2Client\Exception\MissingOptionsException;
use Symfony\Component\Security\OAuth2Client\Token\AuthorizationCodeGrantAccessToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AuthorizationCodeProvider extends GenericProvider
{
    /**
     * The following options: redirect_uri, scope and state are optional or recommended https://tools.ietf.org/html/rfc6749#section-4.1.
     */
    public function fetchAuthorizationInformations(array $options, array $headers = [], string $method = 'GET', bool $secured = false)
    {
        $query = [
            'response_type' => 'code',
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

        $defaultHeaders = [
            'Accept' => 'application/x-www-form-urlencoded',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);
        $finalQuery = $this->mergeRequestArguments($query, $options);

        $response = $this->client->request($method, $this->options['authorization_url'], [
            'headers' => $finalHeaders,
            'query' => $finalQuery,
        ]);

        $matches = $this->parseResponse($response);

        return new AuthorizationCodeResponse($matches['code'], $matches['state']);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET', bool $secured = false)
    {
        if (!isset($options['code'])) {
            throw new MissingOptionsException(sprintf('The required options code is missing'));
        }

        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);

        $response = $this->client->request($method, $this->options['access_token_url'], [
            'headers' => $finalHeaders,
            'query' => [
                'grant_type' => 'authorization_code',
                'code' => $options['code'],
                'redirect_uri' => $this->options['redirect_uri'],
                'client_id' => $this->options['client_id'],
            ],
        ]);

        $this->parseResponse($response);

        $this->checkResponseIsCacheable($response);

        return new AuthorizationCodeGrantAccessToken($response->toArray());
    }
}
