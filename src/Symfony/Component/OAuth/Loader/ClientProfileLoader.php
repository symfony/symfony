<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OAuth\Loader;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ClientProfileLoader
{
    private $client;
    private $clientProfileUrl;

    public function __construct(HttpClientInterface $client, string $clientProfileUrl)
    {
        $this->client = $client;
        $this->clientProfileUrl = $clientProfileUrl;
    }

    /**
     * Allow to fetch the client profile using the url and an access token.
     *
     * @param string $method  the HTTP method used to fetch the profile
     * @param array  $headers an array of headers used to fetch the profile
     *
     * @return ClientProfile the client data
     */
    public function fetchClientProfile(string $method = 'GET', array $headers = []): ClientProfile
    {
        $response = $this->client->request($method, $this->clientProfileUrl, [
            'headers' => $headers,
        ]);

        return new ClientProfile($response->toArray());
    }
}
