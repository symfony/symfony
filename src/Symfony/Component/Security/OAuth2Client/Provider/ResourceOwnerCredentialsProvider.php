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

use Symfony\Component\Security\OAuth2Client\Exception\InvalidRequestException;
use Symfony\Component\Security\OAuth2Client\Token\ResourceOwnerCredentialsGrantToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ResourceOwnerCredentialsProvider extends GenericProvider
{
    /**
     * The ResourceOwnerCredentialsGrantProvider isn't suitable to fetch
     * an authorization code as the credentials should be obtained by the client.
     *
     * More informations on https://tools.ietf.org/html/rfc6749#section-4.3.1
     */
    public function fetchAuthorizationInformations(array $options, array $headers = [], string $method = 'GET')
    {
        throw new \RuntimeException(sprintf('The %s does not support the authorization process, please refer to https://tools.ietf.org/html/rfc6749#section-4.3.1', self::class));
    }

    /**
     * {@inheritdoc}
     *
     * The scope key is optional as explained in https://tools.ietf.org/html/rfc6749#section-4.3.2
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET')
    {
        if (!isset($options['username'], $options['password'])) {
            throw new InvalidRequestException(sprintf('The access_token request requires that you provide a username and a password!'));
        }

        $query = [
            'grant_type' => 'password',
            'username' => $options['username'],
            'password' => $options['password'],
        ];

        if (isset($options['scope'])) {
            $query['scope'] = $options['scope'];
        } else {
            if ($this->logger) {
                $this->logger->warning('The scope is not provided, the expected behaviour can vary.');
            }
        }

        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $finalHeaders = $this->mergeRequestArguments($defaultHeaders, $headers);
        $finalQuery = $this->mergeRequestArguments($query, $options);

        $response = $this->client->request($method, $this->options['access_token_url'], [
            'headers' => $finalHeaders,
            'query' => $finalQuery,
        ]);

        $this->parseResponse($response);

        $this->checkResponseIsCacheable($response);

        return new ResourceOwnerCredentialsGrantToken($response->toArray());
    }
}
