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
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\ImgStub;

/**
 * @author Jérémy Romey <jeremy@free-agent.fr>
 */
final class HttpClientDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * @var TraceableHttpClient[]
     */
    private array $clients = [];

    public function registerClient(string $name, TraceableHttpClient $client)
    {
        $this->clients[$name] = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->lateCollect();
    }

    public function lateCollect()
    {
        $this->data['request_count'] = 0;
        $this->data['error_count'] = 0;
        $this->data += ['clients' => []];

        foreach ($this->clients as $name => $client) {
            [$errorCount, $traces] = $this->collectOnClient($client);

            $this->data['clients'] += [
                $name => [
                    'traces' => [],
                    'error_count' => 0,
                ],
            ];

            $this->data['clients'][$name]['traces'] = array_merge($this->data['clients'][$name]['traces'], $traces);
            $this->data['request_count'] += \count($traces);
            $this->data['error_count'] += $this->data['clients'][$name]['error_count'] += $errorCount;

            $client->reset();
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
    public function getName(): string
    {
        return 'http_client';
    }

    public function reset()
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
            'retry_count' => 1,
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

            if (($info['http_method'] ?? null) === $trace['method']) {
                unset($info['http_method']);
            }

            if (($info['url'] ?? null) === $trace['url']) {
                unset($info['url']);
            }

            foreach ($info as $k => $v) {
                if (!$v || (is_numeric($v) && 0 > $v)) {
                    unset($info[$k]);
                }
            }

            if (\is_string($content = $trace['content'])) {
                $contentType = 'application/octet-stream';

                foreach ($info['response_headers'] ?? [] as $h) {
                    if (0 === stripos($h, 'content-type: ')) {
                        $contentType = substr($h, \strlen('content-type: '));
                        break;
                    }
                }

                if (0 === strpos($contentType, 'image/') && class_exists(ImgStub::class)) {
                    $content = new ImgStub($content, $contentType, '');
                } else {
                    $content = [$content];
                }

                $content = ['response_content' => $content];
            } elseif (\is_array($content)) {
                $content = ['response_json' => $content];
            } else {
                $content = [];
            }

            if (isset($info['retry_count'])) {
                $content['retries'] = $info['previous_info'];
                unset($info['previous_info']);
            }

            $debugInfo = array_diff_key($info, $baseInfo);
            $info = ['info' => $debugInfo] + array_diff_key($info, $debugInfo) + $content;
            unset($traces[$i]['info']); // break PHP reference used by TraceableHttpClient
            $traces[$i]['info'] = $this->cloneVar($info);
            $traces[$i]['options'] = $this->cloneVar($trace['options']);
        }

        return [$errorCount, $traces];
    }
}
