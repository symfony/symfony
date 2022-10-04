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

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Component\HttpClient\Response\TraceableResponse;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class TraceableHttpClient implements HttpClientInterface, ResetInterface, LoggerAwareInterface
{
    private HttpClientInterface $client;
    private ?Stopwatch $stopwatch;
    private \ArrayObject $tracedRequests;

    public function __construct(HttpClientInterface $client, Stopwatch $stopwatch = null)
    {
        $this->client = $client;
        $this->stopwatch = $stopwatch;
        $this->tracedRequests = new \ArrayObject();
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $content = null;
        $traceInfo = [];
        $this->tracedRequests[] = [
            'method' => $method,
            'url' => $url,
            'options' => $options,
            'info' => &$traceInfo,
            'content' => &$content,
        ];
        $onProgress = $options['on_progress'] ?? null;

        if (false === ($options['extra']['trace_content'] ?? true)) {
            unset($content);
            $content = false;
        }

        $options['on_progress'] = function (int $dlNow, int $dlSize, array $info) use (&$traceInfo, $onProgress) {
            $traceInfo = $info;

            if (null !== $onProgress) {
                $onProgress($dlNow, $dlSize, $info);
            }
        };

        return new TraceableResponse($this->client, $this->client->request($method, $url, $options), $content, $this->stopwatch?->start("$method $url", 'http_client'));
    }

    public function stream(ResponseInterface|iterable $responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof TraceableResponse) {
            $responses = [$responses];
        }

        return new ResponseStream(TraceableResponse::stream($this->client, $responses, $timeout));
    }

    public function getTracedRequests(): array
    {
        return $this->tracedRequests->getArrayCopy();
    }

    public function reset()
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }

        $this->tracedRequests->exchangeArray([]);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }
}
