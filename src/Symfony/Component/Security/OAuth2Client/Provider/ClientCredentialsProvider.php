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

use Symfony\Component\Security\OAuth2Client\Token\ClientGrantToken;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ClientCredentialsProvider extends GenericProvider
{
    /**
     * {@inheritdoc}
     *
     * The ClientGrantProvider isn't suitable to fetch an authorization code
     * as the credentials should be obtained by the client.
     *
     * More informations on https://tools.ietf.org/html/rfc6749#section-4.4.1
     */
    public function fetchAuthorizationInformations(array $options, array $headers = [], string $method = 'GET')
    {
        throw new \RuntimeException(sprintf('The %s does not support the authorization process, the credentials should be obtained by the client, please refer to https://tools.ietf.org/html/rfc6749#section-4.4.1', self::class));
    }

    /**
     * {@inheritdoc}
     *
     * The scope option is optional as explained https://tools.ietf.org/html/rfc6749#section-4.4.2
     *
     * The response headers are checked as the response should not be cacheable https://tools.ietf.org/html/rfc6749#section-5.1
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET')
    {
        $query = [
            'grant_type' => 'client_credentials',
        ];

        if ($options['scope']) {
            $query['scope'] = $options['scope'];
        } else {
            if ($this->logger) {
                $this->logger->warning('The scope option isn\'t defined, the expected behaviour can vary');

                $query = array_unique(array_merge($query, $options));
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

        return new ClientGrantToken($response->toArray());
    }
}
