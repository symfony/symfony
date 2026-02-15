<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap;

use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
class BatchHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    private const BATCH_SIZE = 250;

    private \WeakMap $pendingRequests;

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->pendingRequests ??= new \WeakMap();
        $pendingRequests = [];

        foreach ($this->pendingRequests as $response => $_) {
            if ($response->getInfo('http_code')) {
                $this->pendingRequests->offsetUnset($response);
            } else {
                $pendingRequests[] = $response;
            }
        }

        if (\count($pendingRequests) >= self::BATCH_SIZE) {
            foreach ($this->client->stream($pendingRequests) as $response => $chunk) {
                if (!$chunk->isTimeout() && $chunk->isFirst()) {
                    $response->getStatusCode(); // ignore 3/4/5xx
                    $this->pendingRequests->offsetUnset($response);
                    break;
                }
            }
        }

        $response = $this->client->request($method, $url, $options);
        $this->pendingRequests[$response] = true;

        return $response;
    }
}
