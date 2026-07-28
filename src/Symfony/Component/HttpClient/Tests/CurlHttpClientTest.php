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
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\CurlClientState;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @requires extension curl
 * @group dns-sensitive
 */
class CurlHttpClientTest extends HttpClientTestCase
{
    private const HTTPS_PROXY_ERROR = 'Cannot use an "https://" proxy: the installed curl does not support HTTPS proxies and could connect to it in cleartext; curl 7.52 or higher is required.';

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        if (!str_contains($testCase, 'Push')) {
            return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false]);
        }

        if (\PHP_VERSION_ID >= 70300 && \PHP_VERSION_ID < 70304) {
            $this->markTestSkipped('PHP 7.3.0 to 7.3.3 don\'t support HTTP/2 PUSH');
        }

        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > ($v = curl_version())['version_number'] || !(\CURL_VERSION_HTTP2 & $v['features'])) {
            $this->markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
        }

        return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false], 6, 50);
    }

    public function testTimeoutIsNotAFatalError()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testTimeoutIsNotAFatalError();
    }

    public function testHandleIsReinitOnReset()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);

        $r = new \ReflectionMethod($httpClient, 'ensureState');
        $r->setAccessible(true);
        $clientState = $r->invoke($httpClient);
        $initialShareId = $clientState->share;
        $httpClient->reset();
        self::assertNotSame($initialShareId, $clientState->share);
    }

    public function testProcessAfterReset()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://127.0.0.1:8057/json');

        $client->reset();

        $this->assertSame(['application/json'], $response->getHeaders()['content-type']);
    }

    public function testOverridingRefererUsingCurlOptions()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set "CURLOPT_REFERER" with "extra.curl", use option "headers" instead.');

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The HTTP method cannot be overridden using "extra.curl".');

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set "CURLOPT_PRIVATE" with "extra.curl".');

        $httpClient->request('POST', 'http://localhost:8057/', [
            'extra' => [
                'curl' => [
                    \CURLOPT_PRIVATE => 'overridden private',
                ],
            ],
        ]);
    }

    public static function getRedirectWithHostHeaderTests()
    {
        // curl itself drops the credentials when the port changes
        return [
            'same host and port' => ['url' => 'http://localhost:8057/custom', 'redirectWithAuth' => true, 'expectedHost' => 'localhost:8057'],
            'other port' => ['url' => 'http://localhost:8067/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:8057'],
            'other host' => ['url' => 'http://127.0.0.1:8057/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:8057'],
        ];
    }

    public function testHttpsProxyIsRejectedWhenCurlLacksSupport()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->withProxyEnvironment([], [], false, function () use ($client) {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(self::HTTPS_PROXY_ERROR);

            $client->request('GET', 'http://127.0.0.1:8057/', ['proxy' => 'https://127.0.0.1:8057']);
        });
    }

    public function testHttpsProxyFromServerVarsIsRejectedWhenCurlLacksSupport()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->withProxyEnvironment(['https_proxy' => 'https://127.0.0.1:8057'], [], false, function () use ($client) {
            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(self::HTTPS_PROXY_ERROR);

            $client->request('GET', 'https://127.0.0.1:8057/');
        });
    }

    public function testProxyFromProcessEnvIsNotUsed()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        // Only curl reads the process environment, and it must not do so behind our back
        $this->withProxyEnvironment([], ['http_proxy' => 'http://127.0.0.1:9'], true, function () use ($client) {
            $response = $client->request('GET', 'http://127.0.0.1:8057/');

            $this->assertSame(200, $response->getStatusCode());
        });
    }

    public function testHttpsProxyIsRejectedOnRedirectWhenCurlLacksSupport()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->withProxyEnvironment(['https_proxy' => 'https://127.0.0.1:8057'], [], false, function () use ($client) {
            $response = $client->request('GET', 'http://127.0.0.1:8057/302?location=https://127.0.0.1:8057/');

            $this->expectException(TransportException::class);
            $this->expectExceptionMessage(self::HTTPS_PROXY_ERROR);

            $response->getStatusCode();
        });
    }

    public function testHttpsProxyIsNotRejectedWhenNoProxyMatches()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->withProxyEnvironment([], [], false, function () use ($client) {
            $response = $client->request('GET', 'http://127.0.0.1:8057/', [
                'proxy' => 'https://127.0.0.1:8057',
                'no_proxy' => '127.0.0.1',
            ]);

            $this->assertSame(200, $response->getStatusCode());
        });
    }

    public function testHttpsProxyIsNotRejectedWhenCurlSupportsIt()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $this->withProxyEnvironment([], [], true, function () use ($client) {
            $response = $client->request('GET', 'http://127.0.0.1:8057/', ['proxy' => 'https://127.0.0.1:8057']);
            $response->cancel();

            $this->addToAssertionCount(1);
        });
    }

    public function testKeepAuthorizationHeaderOnRedirectToSameHostWithConfiguredHostToIpAddressMapping()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        $response = $httpClient->request('POST', 'http://127.0.0.1:8057/301', [
            'headers' => [
                'Authorization' => 'Basic Zm9vOmJhcg==',
            ],
            'resolve' => [
                'symfony.com' => '10.10.10.10',
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/302', $response->toArray()['REQUEST_URI'] ?? null);
    }

    /**
     * @group integration
     */
    public function testMaxConnections()
    {
        foreach ($ports = [80, 8681, 8682, 8683, 8684] as $port) {
            if (!($fp = @fsockopen('localhost', $port, $errorCode, $errorMessage, 2))) {
                self::markTestSkipped('FrankenPHP is not running');
            }
            fclose($fp);
        }

        $httpClient = $this->getHttpClient(__FUNCTION__);

        $expectedResults = [
            [false, false, false, false, false],
            [true, true, true, true, true],
            [true, true, true, true, true],
        ];

        foreach ($expectedResults as $expectedResult) {
            foreach ($ports as $i => $port) {
                $response = $httpClient->request('GET', \sprintf('http://localhost:%s/http-client', $port));
                $response->getContent();

                self::assertSame($expectedResult[$i], str_contains($response->getInfo('debug'), 'Re-using existing connection'));
            }
        }
    }

    /**
     * Runs $test with a controlled proxy environment and a curl that pretends to support HTTPS proxies or not.
     */
    private function withProxyEnvironment(array $server, array $env, bool $httpsProxySupport, \Closure $test): void
    {
        $backup = [];

        foreach (['http_proxy', 'HTTP_PROXY', 'https_proxy', 'HTTPS_PROXY', 'all_proxy', 'ALL_PROXY', 'no_proxy', 'NO_PROXY'] as $name) {
            $backup[$name] = [\array_key_exists($name, $_SERVER) ? $_SERVER[$name] : null, getenv($name, true)];
            unset($_SERVER[$name]);
            putenv($name);
        }

        foreach ($server as $name => $value) {
            $_SERVER[$name] = $value;
        }

        foreach ($env as $name => $value) {
            putenv($name.'='.$value);
        }

        $curlVersion = CurlClientState::$curlVersion ?? curl_version();
        $httpsProxyFeature = \defined('CURL_VERSION_HTTPS_PROXY') ? \CURL_VERSION_HTTPS_PROXY : 1 << 21;
        CurlClientState::$curlVersion = ['features' => $httpsProxySupport ? $curlVersion['features'] | $httpsProxyFeature : $curlVersion['features'] & ~$httpsProxyFeature] + $curlVersion;

        try {
            $test();
        } finally {
            CurlClientState::$curlVersion = $curlVersion;

            foreach ($backup as $name => [$serverValue, $envValue]) {
                if (null === $serverValue) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $serverValue;
                }

                false === $envValue ? putenv($name) : putenv($name.'='.$envValue);
            }
        }
    }
}
