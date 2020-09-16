<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\FormattableHandlerTrait;
use Monolog\Handler\ProcessableHandlerTrait;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Push logs directly to Elasticsearch and format them according to Logstash specification.
 *
 * This handler dials directly with the HTTP interface of Elasticsearch. This
 * means it will slow down your application if Elasticsearch takes times to
 * answer. Even if all HTTP calls are done asynchronously.
 *
 * In a development environment, it's fine to keep the default configuration:
 * for each log, an HTTP request will be made to push the log to Elasticsearch.
 *
 * In a production environment, it's highly recommended to wrap this handler
 * in a handler with buffering capabilities (like the FingersCrossedHandler, or
 * BufferHandler) in order to call Elasticsearch only once with a bulk push. For
 * even better performance and fault tolerance, a proper ELK (https://www.elastic.co/what-is/elk-stack)
 * stack is recommended.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ElasticsearchLogstashHandler extends AbstractHandler
{
    use FormattableHandlerTrait;
    use ProcessableHandlerTrait;

    private $endpoint;
    private $index;
    private $client;
    private $responses;

    /**
     * @param string|int $level The minimum logging level at which this handler will be triggered
     */
    public function __construct(string $endpoint = 'http://127.0.0.1:9200', string $index = 'monolog', HttpClientInterface $client = null, $level = Logger::DEBUG, bool $bubble = true)
    {
        if (!interface_exists(HttpClientInterface::class)) {
            throw new \LogicException(sprintf('The "%s" handler needs an HTTP client. Try running "composer require symfony/http-client".', __CLASS__));
        }

        parent::__construct($level, $bubble);
        $this->endpoint = $endpoint;
        $this->index = $index;
        $this->client = $client ?: HttpClient::create(['timeout' => 1]);
        $this->responses = new \SplObjectStorage();
    }

    public function handle(array $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        $this->sendToElasticsearch([$record]);

        return !$this->bubble;
    }

    public function handleBatch(array $records): void
    {
        $records = array_filter($records, [$this, 'isHandling']);

        if ($records) {
            $this->sendToElasticsearch($records);
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        // Monolog 1.X
        if (\defined(LogstashFormatter::class.'::V1')) {
            return new LogstashFormatter('application', null, null, 'ctxt_', LogstashFormatter::V1);
        }

        // Monolog 2.X
        return new LogstashFormatter('application');
    }

    private function sendToElasticsearch(array $records)
    {
        $formatter = $this->getFormatter();

        $body = '';
        foreach ($records as $record) {
            foreach ($this->processors as $processor) {
                $record = $processor($record);
            }

            $body .= json_encode([
                'index' => [
                    '_index' => $this->index,
                    '_type' => '_doc',
                ],
            ]);
            $body .= "\n";
            $body .= $formatter->format($record);
            $body .= "\n";
        }

        $response = $this->client->request('POST', $this->endpoint.'/_bulk', [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->responses->attach($response);

        $this->wait(false);
    }

    public function __destruct()
    {
        $this->wait(true);
    }

    private function wait(bool $blocking)
    {
        foreach ($this->client->stream($this->responses, $blocking ? null : 0.0) as $response => $chunk) {
            try {
                if ($chunk->isTimeout() && !$blocking) {
                    continue;
                }
                if (!$chunk->isFirst() && !$chunk->isLast()) {
                    continue;
                }
                if ($chunk->isLast()) {
                    $this->responses->detach($response);
                }
            } catch (ExceptionInterface $e) {
                $this->responses->detach($response);
                error_log(sprintf("Could not push logs to Elasticsearch:\n%s", (string) $e));
            }
        }
    }
}
