<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\PoEditor;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PoEditorHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (isset($options['poeditor_credentials'])) {
            if ('POST' === $method) {
                $options['body'] = $options['poeditor_credentials'] + $options['body'];
            }
            unset($options['poeditor_credentials']);
        }

        return $this->client->request($method, $url, $options);
    }

    public static function create(HttpClientInterface $client, string $baseUri, string $apiToken, string $projectId): HttpClientInterface
    {
        return ScopingHttpClient::forBaseUri(new self($client), $baseUri, [
            'poeditor_credentials' => [
                'api_token' => $apiToken,
                'id' => $projectId,
            ],
        ]);
    }
}
