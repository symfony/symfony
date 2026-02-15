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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;

#[RequiresPhpExtension('curl')]
#[Group('dns-sensitive')]
class CurlHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): CurlHttpClient
    {
        $usePersistentConnections = str_contains($testCase, 'Persistent');
        if (!str_contains($testCase, 'Push')) {
            return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false, 'extra' => ['use_persistent_connections' => $usePersistentConnections]]);
        }

        if (!\defined('CURLMOPT_PUSHFUNCTION') || 0x073D00 > ($v = curl_version())['version_number'] || !(\CURL_VERSION_HTTP2 & $v['features'])) {
            $this->markTestSkipped('curl <7.61 is used or it is not compiled with support for HTTP/2 PUSH');
        }

        return new CurlHttpClient(['verify_peer' => false, 'verify_host' => false, 'extra' => ['use_persistent_connections' => $usePersistentConnections]], 6, 50);
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

        $r = new \ReflectionProperty($httpClient, 'multi');
        $clientState = $r->getValue($httpClient);
        $initialShareId = $clientState->share;
        $httpClient->reset();
        self::assertNotSame($initialShareId, $clientState->share);
    }

    public function testCurlClientStateIsSharedBetweenClones()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $cloneA = $client->withOptions(['headers' => ['Foo: bar']]);
        $cloneB = $client->withOptions(['headers' => ['Foo: baz']]);

        $r = new \ReflectionProperty($client, 'multi');
        $state = $r->getValue($client);

        self::assertSame($state, $r->getValue($cloneA));
        self::assertSame($state, $r->getValue($cloneB));
    }

    public function testCurlClientStateInitializesHandlesLazily()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $r = new \ReflectionProperty($client, 'multi');
        $state = $r->getValue($client);

        self::assertFalse(isset($state->handle));
        self::assertFalse(isset($state->share));

        $client->request('GET', 'http://127.0.0.1:8057/json')->getStatusCode();

        self::assertInstanceOf(\CurlMultiHandle::class, $state->handle);
        self::assertInstanceOf(\CurlShareHandle::class, $state->share);
    }

    public function testCurlClientPersistentStateInitializesHandlesLazily()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $r = new \ReflectionProperty($client, 'multi');
        $state = $r->getValue($client);

        self::assertFalse(isset($state->handle));
        self::assertFalse(isset($state->share));
        self::assertFalse(isset($state->persistentShare));

        $client->request('GET', 'http://127.0.0.1:8057/json')->getStatusCode();

        self::assertInstanceOf(\CurlMultiHandle::class, $state->handle);
        self::assertInstanceOf(\CurlShareHandle::class, $state->share);
        self::assertInstanceOf(\PHP_VERSION_ID >= 80500 ? \CurlSharePersistentHandle::class : \CurlShareHandle::class, $state->persistentShare);
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

    public function testOverridingMaxConnectDurationUsingCurlOptions()
    {
        $httpClient = $this->getHttpClient(__FUNCTION__);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set "CURLOPT_CONNECTTIMEOUT_MS" with "extra.curl", use option "max_connect_duration" instead.');

        $httpClient->request('GET', 'http://localhost:8057/', [
            'extra' => [
                'curl' => [
                    \CURLOPT_CONNECTTIMEOUT_MS => 5000,
                ],
            ],
        ]);
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

    #[Group('integration')]
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
}
