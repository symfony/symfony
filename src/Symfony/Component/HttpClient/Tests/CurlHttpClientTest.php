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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase;

/**
 * @requires extension curl
 */
class CurlHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new CurlHttpClient();
    }

    /**
     * @requires PHP 7.2.17
     */
    public function testHttp2Push()
    {
        if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
            $this->markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
        }

        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073d00 > ($v = curl_version())['version_number'] || !(CURL_VERSION_HTTP2 & $v['features'])) {
            $this->markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
        }

        $logger = new class() extends AbstractLogger {
            public $logs = [];

            public function log($level, $message, array $context = [])
            {
                $this->logs[] = $message;
            }
        };

        $client = new CurlHttpClient();
        $client->setLogger($logger);

        $index = $client->request('GET', 'https://http2-push.io');
        $index->getContent();

        $css = $client->request('GET', 'https://http2-push.io/css/style.css');
        $js = $client->request('GET', 'https://http2-push.io/js/http2-push.js');

        $css->getHeaders();
        $js->getHeaders();

        $expected = [
            'Request: "GET https://http2-push.io/"',
            'Queueing pushed response: "https://http2-push.io/css/style.css"',
            'Queueing pushed response: "https://http2-push.io/js/http2-push.js"',
            'Response: "200 https://http2-push.io/"',
            'Connecting request to pushed response: "GET https://http2-push.io/css/style.css"',
            'Connecting request to pushed response: "GET https://http2-push.io/js/http2-push.js"',
            'Response: "200 https://http2-push.io/css/style.css"',
            'Response: "200 https://http2-push.io/js/http2-push.js"',
        ];
        $this->assertSame($expected, $logger->logs);
    }
}
