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

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @requires extension curl
 */
class CurlHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        if (false !== strpos($testCase, 'Push')) {
            if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
                self::markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
            }

            if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > ($v = curl_version())['version_number'] || !(\CURL_VERSION_HTTP2 & $v['features'])) {
                self::markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
            }
        }

        return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
    }

    public function testBindToPort()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057', ['bindto' => '127.0.0.1:9876']);
        $response->getStatusCode();

        $r = new \ReflectionProperty($response, 'handle');
        $r->setAccessible(true);

        $curlInfo = curl_getinfo($r->getValue($response));

        self::assertSame('127.0.0.1', $curlInfo['local_ip']);
        self::assertSame(9876, $curlInfo['local_port']);
    }

    public function testTimeoutIsNotAFatalError()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Too transient on Windows');
        }

        parent::testTimeoutIsNotAFatalError();
    }

    public function testHandleIsReinitOnReset()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);

        $r = new \ReflectionProperty($httpClient, 'multi');
        $r->setAccessible(true);
        $clientState = $r->getValue($httpClient);
        $initialShareId = $clientState->share;
        $httpClient->reset();
        self::assertNotSame($initialShareId, $clientState->share);
    }

    public function testProcessAfterReset()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://127.0.0.1:8057/json');

        $client->reset();

        self::assertSame(['application/json'], $response->getHeaders()['content-type']);
    }

    public function testOverridingRefererUsingCurlOptions()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Cannot set "CURLOPT_REFERER" with "extra.curl", use option "headers" instead.');

        $httpClient->request('GET', 'http://localhost:8057/', [
            'extra' => [
                'curl' => [
                    \CURLOPT_REFERER => 'Banana',
                ],
            ],
        ]);
    }

    public function testOverridingHttpMethodUsingCurlOptions()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The HTTP method cannot be overridden using "extra.curl".');

        $httpClient->request('POST', 'http://localhost:8057/', [
            'extra' => [
                'curl' => [
                    \CURLOPT_HTTPGET => true,
                ],
            ],
        ]);
    }

    public function testOverridingInternalAttributesUsingCurlOptions()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Cannot set "CURLOPT_PRIVATE" with "extra.curl".');

        $httpClient->request('POST', 'http://localhost:8057/', [
            'extra' => [
                'curl' => [
                    \CURLOPT_PRIVATE => 'overriden private',
                ],
            ],
        ]);
    }
}
