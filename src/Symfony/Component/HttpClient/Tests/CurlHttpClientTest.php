<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/*
Tests for HTTP2 Push need a recent version of both PHP and curl. This docker command should run them:
docker run -it --rm -v $(pwd):/app -v /path/to/vulcain:/usr/local/bin/vulcain -w /app php:7.3-alpine ./phpunit src/Symfony/Component/HttpClient/Tests/CurlHttpClientTest.php --filter testHttp2Push
The vulcain binary can be found at https://github.com/symfony/binary-utils/releases/download/v0.1/vulcain_0.1.3_Linux_x86_64.tar.gz - see https://github.com/dunglas/vulcain for source
*/

/**
 * @requires extension curl
 */
class CurlHttpClientTest extends HttpClientTestCase
{
    private static $vulcainStarted = false;

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new CurlHttpClient();
    }

    /**
     * @requires PHP 7.2.17
     */
    public function testHttp2PushVulcain()
    {
        $client = $this->getVulcainClient();
        $logger = new TestLogger();
        $client->setLogger($logger);

        $responseAsArray = $client->request('GET', 'https://127.0.0.1:3000/json', [
            'headers' => [
                'Preload' => '/documents/*/id',
            ],
        ])->toArray();

        foreach ($responseAsArray['documents'] as $document) {
            $client->request('GET', 'https://127.0.0.1:3000'.$document['id'])->toArray();
        }

        $client->reset();

        $expected = [
            'Request: "GET https://127.0.0.1:3000/json"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/1"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/2"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/1"',
            'Response: "200 https://127.0.0.1:3000/json/1"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/2"',
            'Response: "200 https://127.0.0.1:3000/json/2"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json/3"',
        ];
        $this->assertSame($expected, $logger->logs);
    }

    /**
     * @requires PHP 7.2.17
     */
    public function testHttp2PushVulcainWithUnusedResponse()
    {
        $client = $this->getVulcainClient();
        $logger = new TestLogger();
        $client->setLogger($logger);

        $responseAsArray = $client->request('GET', 'https://127.0.0.1:3000/json', [
            'headers' => [
                'Preload' => '/documents/*/id',
            ],
        ])->toArray();

        $i = 0;
        foreach ($responseAsArray['documents'] as $document) {
            $client->request('GET', 'https://127.0.0.1:3000'.$document['id'])->toArray();
            if (++$i >= 2) {
                break;
            }
        }

        $client->reset();

        $expected = [
            'Request: "GET https://127.0.0.1:3000/json"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/1"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/2"',
            'Queueing pushed response: "https://127.0.0.1:3000/json/3"',
            'Response: "200 https://127.0.0.1:3000/json"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/1"',
            'Response: "200 https://127.0.0.1:3000/json/1"',
            'Accepting pushed response: "GET https://127.0.0.1:3000/json/2"',
            'Response: "200 https://127.0.0.1:3000/json/2"',
            'Unused pushed response: "https://127.0.0.1:3000/json/3"',
        ];
        $this->assertSame($expected, $logger->logs);
    }

    private function getVulcainClient(): CurlHttpClient
    {
        if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
            $this->markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
        }

        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073d00 > ($v = curl_version())['version_number'] || !(CURL_VERSION_HTTP2 & $v['features'])) {
            $this->markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
        }

        $client = new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);

        if (static::$vulcainStarted) {
            return $client;
        }

        if (['application/json'] !== $client->request('GET', 'http://127.0.0.1:8057/json')->getHeaders()['content-type']) {
            $this->markTestSkipped('symfony/http-client-contracts >= 2.0.1 required');
        }

        $process = new Process(['vulcain'], null, [
            'DEBUG' => 1,
            'UPSTREAM' => 'http://127.0.0.1:8057',
            'ADDR' => ':3000',
            'KEY_FILE' => __DIR__.'/Fixtures/tls/server.key',
            'CERT_FILE' => __DIR__.'/Fixtures/tls/server.crt',
        ]);
        $process->start();

        register_shutdown_function([$process, 'stop']);
        sleep('\\' === \DIRECTORY_SEPARATOR ? 10 : 1);

        if (!$process->isRunning()) {
            throw new ProcessFailedException($process);
        }

        static::$vulcainStarted = true;

        return $client;
    }
}

class TestLogger extends AbstractLogger
{
    public $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = $message;
    }
}
