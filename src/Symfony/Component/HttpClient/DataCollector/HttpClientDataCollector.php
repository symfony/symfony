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

    public function addClient(string $name, TraceableHttpClient $client)
    {
        $this->clients[$name] = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = [
            'clients' => [],
            'request_count' => 0,
            'error_count' => 0,
        ];

        foreach ($this->clients as $name => $client) {
            $traces = $client->getTraces();

            $this->data['request_count'] += \count($traces);
            $errorCount = 0;

            foreach ($traces as $i => $trace) {
                if (400 <= ($trace['info']['http_code'] ?? 0)) {
                    ++$errorCount;
                }

                $info = $trace['info'];
                $traces[$i]['http_code'] = $info['http_code'];

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

                $traces[$i]['info'] = $this->cloneVar($info);
                $traces[$i]['options'] = $this->cloneVar($trace['options']);
            }

            $this->data['clients'][$name] = [
                'traces' => $traces,
                'error_count' => $errorCount,
            ];
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
        $this->data = [];
        foreach ($this->clients as $client) {
            $client->clearTraces();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'http_client';
    }
}
