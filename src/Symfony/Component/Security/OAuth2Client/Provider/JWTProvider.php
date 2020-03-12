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
use Symfony\Component\Security\OAuth2Client\Exception\InvalidJWTAuthorizationOptions;
use Symfony\Component\Security\OAuth2Client\Exception\InvalidJWTTokenTypeException;
use Symfony\Component\Security\OAuth2Client\Exception\MissingOptionsException;
use Symfony\Component\Security\OAuth2Client\Token\AuthorizationCodeGrantAccessToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class JWTProvider extends GenericProvider
{
    /**
     * {@inheritdoc}
     */
    public function fetchAuthorizationInformations(array $options, array $headers = [], string $method = 'POST')
    {
        if (!isset($options['iss'], $options['sub'], $options['aud'], $options['exp'])) {
            throw new InvalidJWTAuthorizationOptions(sprintf(''));
        }

        $body = [
            'iss' => $options['iss'],
            'sub' => $options['sub'],
            'aud' => $options['aud'],
            'exp' => $options['exp'],
        ];

        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);
        $finalQuery = $this->mergeRequestArguments($body, $options);

        $response = $this->client->request($method, $this->options['authorization_url'], [
            'headers' => $finalHeaders,
            'body' => $finalQuery,
        ]);

        $matches = $this->parseResponse($response);

        return new AuthorizationCodeResponse($matches['code'], $matches['state']);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET')
    {
        if (!isset($options['assertion'])) {
            throw new MissingOptionsException(sprintf('The assertion query parameters mut be set!'));
        }

        $query = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $options['assertion'],
        ];

        if (isset($options['client_id']) && \is_string($options['assertion'])) {
            $query['client_id'] = $options['client_id'];
        } elseif (!\is_string($options['assertion'])) {
            throw new InvalidJWTTokenTypeException(sprintf('The given JWT token isn\'t properly typed, given %s', \gettype($options['assertion'])));
        }

        if (isset($options['scope'])) {
            $query['scope'] = $options['scope'];
        }

        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);

        $response = $this->client->request($method, $this->options['access_token_url'], [
            'headers' => $finalHeaders,
            'query' => $query,
        ]);

        $this->parseResponse($response);

        $this->checkResponseIsCacheable($response);

        return new AuthorizationCodeGrantAccessToken($response->toArray());
    }
}
