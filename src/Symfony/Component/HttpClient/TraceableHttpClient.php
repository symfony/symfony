<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class TraceableHttpClient implements HttpClientInterface
{
    private $client;
    private $tracedRequests = [];

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $traceInfo = [];
        $this->tracedRequests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
            'info' => &$traceInfo,
        ];
        $onProgress = $options['on_progress'] ?? null;

        $options['on_progress'] = function (int $dlNow, int $dlSize, array $info) use (&$traceInfo, $onProgress) {
            $traceInfo = $info;

            if (null !== $onProgress) {
                $onProgress($dlNow, $dlSize, $info);
            }
        };

        return $this->client->request($method, $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function getTracedRequests(): array
    {
        return $this->tracedRequests;
    }

    public function reset()
    {
        $this->tracedRequests = [];
    }
}
