<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph;

use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TokenManager
{
    private ?string $token = null;
    private ?DatePoint $tokenExpires = null;

    /**
     * @param string $graphEndpoint Graph API URL to which to POST emails
     * @param string $authEndpoint  Authentication URL
     * @param string $tenantId      Microsoft Azure tenant identifier
     * @param string $appId         Microsoft Azure app registration ID
     * @param string $appSecret     Microsoft Azure app registration secret
     */
    public function __construct(
        private readonly string $graphEndpoint,
        private readonly string $authEndpoint,
        private readonly string $tenantId,
        private readonly string $appId,
        #[\SensitiveParameter] private readonly string $appSecret,
        private readonly HttpClientInterface $client,
    ) {
    }

    public function getToken(): string
    {
        if (null !== $this->token && $this->tokenExpires > new DatePoint()) {
            return $this->token;
        }

        $endpoint = "https://$this->authEndpoint/$this->tenantId/oauth2/v2.0/token";
        $response = $this->client->request('POST', $endpoint, [
            'body' => [
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'scope' => "https://$this->graphEndpoint/.default",
                'grant_type' => 'client_credentials',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Microsoft authentication server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to authenticate: '.$response->getContent(false).\sprintf(' (code %d).', $statusCode), $response);
        }

        $tokenData = $response->toArray();
        $this->token = $tokenData['access_token'];
        $this->tokenExpires = new DatePoint("+{$tokenData['expires_in']} seconds");

        return $this->token;
    }
}
