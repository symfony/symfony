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
    private $client;
    private $tracedRequests = [];
    private $stopwatch;

    public function __construct(HttpClientInterface $client, Stopwatch $stopwatch = null)
    {
        $this->client = $client;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
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

        return new TraceableResponse($this->client, $this->client->request($method, $url, $options), $content, null === $this->stopwatch ? null : $this->stopwatch->start("$method $url", 'http_client'));
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof TraceableResponse) {
            $responses = [$responses];
        } elseif (!is_iterable($responses)) {
            throw new \TypeError(sprintf('"%s()" expects parameter 1 to be an iterable of TraceableResponse objects, "%s" given.', __METHOD__, get_debug_type($responses)));
        }

        return new ResponseStream(TraceableResponse::stream($this->client, $responses, $timeout));
    }

    public function getTracedRequests(): array
    {
        return $this->tracedRequests;
    }

    public function reset()
    {
        if ($this->client instanceof ResetInterface) {
            $this->client->reset();
        }

        $this->tracedRequests = [];
    }

    /**
     * {@inheritdoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        if ($this->client instanceof LoggerAwareInterface) {
            $this->client->setLogger($logger);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }
}
