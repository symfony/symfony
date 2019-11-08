<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\DataCollector;

use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class HttpClientDataCollector extends DataCollector
{
    /**
     * @var TraceableHttpClient[]
     */
    private $clients = [];

    public function registerClient(string $name, TraceableHttpClient $client)
    {
        $this->clients[$name] = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->initData();

        foreach ($this->clients as $name => $client) {
            [$errorCount, $traces] = $this->collectOnClient($client);

            $this->data['clients'][$name] = [
                'traces' => $traces,
                'error_count' => $errorCount,
            ];

            $this->data['request_count'] += \count($traces);
            $this->data['error_count'] += $errorCount;
        }
    }

    public function getClients(): array
    {
        return $this->data['clients'] ?? [];
    }

    public function getRequestCount(): int
    {
        return $this->data['request_count'] ?? 0;
    }

    public function getErrorCount(): int
    {
        return $this->data['error_count'] ?? 0;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->initData();
        foreach ($this->clients as $client) {
            $client->reset();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'http_client';
    }

    private function initData()
    {
        $this->data = [
            'clients' => [],
            'request_count' => 0,
            'error_count' => 0,
        ];
    }

    private function collectOnClient(TraceableHttpClient $client): array
    {
        $traces = $client->getTracedRequests();
        $errorCount = 0;
        $baseInfo = [
            'response_headers' => 1,
            'redirect_count' => 1,
            'redirect_url' => 1,
            'user_data' => 1,
            'error' => 1,
            'url' => 1,
        ];

        foreach ($traces as $i => $trace) {
            if (400 <= ($trace['info']['http_code'] ?? 0)) {
                ++$errorCount;
            }

            $info = $trace['info'];
            $traces[$i]['http_code'] = $info['http_code'] ?? 0;

            unset($info['filetime'], $info['http_code'], $info['ssl_verify_result'], $info['content_type']);

            if ($trace['method'] === $info['http_method']) {
                unset($info['http_method']);
            }

            if ($trace['url'] === $info['url']) {
                unset($info['url']);
            }

            foreach ($info as $k => $v) {
                if (!$v || (is_numeric($v) && 0 > $v)) {
                    unset($info[$k]);
                }
            }

            $debugInfo = array_diff_key($info, $baseInfo);
            $info = array_diff_key($info, $debugInfo) + ['debug_info' => $debugInfo];
            $traces[$i]['info'] = $this->cloneVar($info);
            $traces[$i]['options'] = $this->cloneVar($trace['options']);
        }

        return [$errorCount, $traces];
    }
}
