<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ElasticsearchLogstashHandlerTest extends TestCase
{
    public function testHandle()
    {
        $callCount = 0;
        $responseFactory = function ($method, $url, $options) use (&$callCount) {
            $body = <<<EOBODY
{"index":{"_index":"log","_type":"_doc"}}
{"@timestamp":"2020-01-01T00:00:00.000000+01:00","@version":1,"host":"my hostname","message":"My info message","type":"application","channel":"app","level":"INFO","monolog_level":200}


EOBODY;

            // Monolog 1X
            if (\defined(LogstashFormatter::class.'::V1')) {
                $body = str_replace(',"monolog_level":200', '', $body);
                $body = str_replace(',"monolog_level":300', '', $body);
            }

            $this->assertSame('POST', $method);
            $this->assertSame('http://es:9200/_bulk', $url);
            $this->assertSame($body, $options['body']);
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            ++$callCount;

            return new MockResponse();
        };

        $handler = new ElasticsearchLogstashHandler('http://es:9200', 'log', new MockHttpClient($responseFactory));
        $handler->setFormatter($this->getDefaultFormatter());

        $record = RecordFactory::create(Logger::INFO, 'My info message', 'app', datetime: new \DateTimeImmutable('2020-01-01T00:00:00+01:00'));

        $handler->handle($record);

        $this->assertSame(1, $callCount);
    }

    public function testHandleWithElasticsearch8()
    {
        $callCount = 0;
        $responseFactory = function ($method, $url, $options) use (&$callCount) {
            $body = <<<EOBODY
{"index":{"_index":"log"}}
{"@timestamp":"2020-01-01T00:00:00.000000+01:00","@version":1,"host":"my hostname","message":"My info message","type":"application","channel":"app","level":"INFO","monolog_level":200}


EOBODY;

            // Monolog 1X
            if (\defined(LogstashFormatter::class.'::V1')) {
                $body = str_replace(',"monolog_level":200', '', $body);
                $body = str_replace(',"monolog_level":300', '', $body);
            }

            $this->assertSame('POST', $method);
            $this->assertSame('http://es:9200/_bulk', $url);
            $this->assertSame($body, $options['body']);
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            ++$callCount;

            return new MockResponse();
        };

        $handler = new ElasticsearchLogstashHandler('http://es:9200', 'log', new MockHttpClient($responseFactory), Logger::DEBUG, true, '8.0.0');
        $handler->setFormatter($this->getDefaultFormatter());

        $record = RecordFactory::create(Logger::INFO, 'My info message', 'app', datetime: new \DateTimeImmutable('2020-01-01T00:00:00+01:00'));

        $handler->handle($record);

        $this->assertSame(1, $callCount);
    }

    public function testHandleBatch()
    {
        $callCount = 0;
        $responseFactory = function ($method, $url, $options) use (&$callCount) {
            $body = <<<EOBODY
{"index":{"_index":"log","_type":"_doc"}}
{"@timestamp":"2020-01-01T00:00:00.000000+01:00","@version":1,"host":"my hostname","message":"My info message","type":"application","channel":"app","level":"INFO","monolog_level":200}

{"index":{"_index":"log","_type":"_doc"}}
{"@timestamp":"2020-01-01T00:00:01.000000+01:00","@version":1,"host":"my hostname","message":"My second message","type":"application","channel":"php","level":"WARNING","monolog_level":300}


EOBODY;

            // Monolog 1X
            if (\defined(LogstashFormatter::class.'::V1')) {
                $body = str_replace(',"monolog_level":200', '', $body);
                $body = str_replace(',"monolog_level":300', '', $body);
            }

            $this->assertSame('POST', $method);
            $this->assertSame('http://es:9200/_bulk', $url);
            $this->assertSame($body, $options['body']);
            $this->assertSame('Content-Type: application/json', $options['normalized_headers']['content-type'][0]);
            ++$callCount;

            return new MockResponse();
        };

        $handler = new ElasticsearchLogstashHandler('http://es:9200', 'log', new MockHttpClient($responseFactory));
        $handler->setFormatter($this->getDefaultFormatter());

        $records = [
            RecordFactory::create(Logger::INFO, 'My info message', 'app', datetime: new \DateTimeImmutable('2020-01-01T00:00:00+01:00')),
            RecordFactory::create(Logger::WARNING, 'My second message', 'php', datetime: new \DateTimeImmutable('2020-01-01T00:00:01+01:00')),
        ];

        $handler->handleBatch($records);

        $this->assertSame(1, $callCount);
    }

    private function getDefaultFormatter(): FormatterInterface
    {
        // Monolog 1.X
        if (\defined(LogstashFormatter::class.'::V1')) {
            return new LogstashFormatter('application', 'my hostname', null, 'ctxt_', LogstashFormatter::V1);
        }

        // Monolog 2.X
        return new LogstashFormatter('application', 'my hostname');
    }
}
