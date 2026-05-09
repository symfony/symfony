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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\GuzzleHttpHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GuzzleHttpHandlerTest extends TestCase
{
    public function testBasicGetRequest()
    {
        [$handler] = $this->makeHandler();
        $request = new Request('GET', 'https://example.com/foo');

        $promise = $handler($request, []);

        $this->assertInstanceOf(PromiseInterface::class, $promise);
        $response = $promise->wait();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMethodAndUrlAreForwarded()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('DELETE', 'https://example.com/resource/42'), [])->wait();

        $this->assertSame('DELETE', $spy->method);
        $this->assertSame('https://example.com/resource/42', $spy->url);
    }

    public function testRequestHeadersAreForwarded()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/', ['X-Custom-Header' => 'my-value']), [])->wait();

        // MockHttpClient normalises headers into normalized_headers as "Name: value" strings.
        $this->assertContains('X-Custom-Header: my-value', $spy->options['normalized_headers']['x-custom-header'] ?? []);
    }

    public function testGuzzleHeadersOptionMergesWithRequestHeaders()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(
            new Request('GET', 'https://example.com/', ['X-From-Request' => 'req']),
            ['headers' => ['X-From-Options' => 'opt']],
        )->wait();

        $this->assertContains('X-From-Request: req', $spy->options['normalized_headers']['x-from-request'] ?? []);
        $this->assertContains('X-From-Options: opt', $spy->options['normalized_headers']['x-from-options'] ?? []);
    }

    public function testBodyIsForwarded()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('POST', 'https://example.com/', ['Content-Type' => 'text/plain'], 'hello body'), [])->wait();

        $this->assertSame('hello body', $spy->options['body']);
    }

    public function testEmptyBodyIsNotSent()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('POST', 'https://example.com/', [], ''), [])->wait();

        $this->assertArrayNotHasKey('body', $spy->options ?? []);
    }

    public function testKnownSizeBodySetsContentLengthHeader()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('POST', 'https://example.com/', [], 'hello body'), [])->wait();

        $this->assertContains('Content-Length: 10', $spy->options['normalized_headers']['content-length'] ?? []);
    }

    public function testBodyWithUnknownSizeIsStreamedAsGenerator()
    {
        [$handler, $spy] = $this->makeHandler();

        // PumpStream has no known size (getSize() === null), triggering the streaming path.
        $body = new \GuzzleHttp\Psr7\PumpStream(static function (): string|false {
            static $content = 'streamed content';
            $chunk = $content;
            $content = '';

            return $chunk ?: false;
        });

        $handler(new Request('POST', 'https://example.com/', [], $body), [])->wait();

        $this->assertIsCallable($spy->options['body']);
    }

    public function testAuthBasicOption()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['auth' => ['user', 'pass']])->wait();

        // auth_basic is merged into the Authorization header by prepareRequest().
        $expected = 'Authorization: Basic '.base64_encode('user:pass');
        $this->assertContains($expected, $spy->options['normalized_headers']['authorization'] ?? []);
    }

    public function testAuthBearerMappedWhenTypeIsBearer()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['auth' => ['mytoken', '', 'bearer']])->wait();

        $this->assertContains('Authorization: Bearer mytoken', $spy->options['normalized_headers']['authorization'] ?? []);
    }

    public function testAuthNtlmMapsToSymfonyOption()
    {
        $handler = new GuzzleHttpHandler();
        $buildOptions = new \ReflectionMethod($handler, 'buildSymfonyOptions');

        $options = $buildOptions->invoke($handler, new Request('GET', 'https://example.com/'), ['auth' => ['user', 'pass', 'ntlm']]);

        $this->assertSame(['user', 'pass'], $options['auth_ntlm']);
    }

    public function testVerifyFalseMapsToVerifyPeerAndHost()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['verify' => false])->wait();

        $this->assertFalse($spy->options['verify_peer']);
        $this->assertFalse($spy->options['verify_host']);
    }

    public function testVerifyStringCaFileMapsToSymfony()
    {
        [$handler, $spy] = $this->makeHandler();

        $caBundle = tempnam(sys_get_temp_dir(), 'ca');
        file_put_contents($caBundle, 'fake-cert');

        try {
            $handler(new Request('GET', 'https://example.com/'), ['verify' => $caBundle])->wait();
            $this->assertSame($caBundle, $spy->options['cafile']);
        } finally {
            unlink($caBundle);
        }
    }

    public function testTimeoutMapsToMaxDuration()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['timeout' => 30.5])->wait();

        $this->assertSame(30.5, $spy->options['max_duration']);
    }

    public function testConnectTimeoutMapsToMaxConnectDuration()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['connect_timeout' => 5.0])->wait();

        $this->assertSame(5.0, $spy->options['max_connect_duration']);
    }

    public function testProxyStringIsForwarded()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['proxy' => 'http://proxy.example.com:8080'])->wait();

        $this->assertSame('http://proxy.example.com:8080', $spy->options['proxy']);
    }

    public function testProxyArraySelectsSchemeSpecificProxy()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'proxy' => ['https' => 'http://secure-proxy:8080', 'http' => 'http://plain-proxy:8080'],
        ])->wait();

        $this->assertSame('http://secure-proxy:8080', $spy->options['proxy']);
    }

    public function testProxyArrayWithNoProxyList()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'proxy' => ['https' => 'http://proxy:8080', 'no' => ['no-proxy.com', 'internal.net']],
        ])->wait();

        $this->assertSame('no-proxy.com,internal.net', $spy->options['no_proxy']);
    }

    public function testAllowRedirectsFalseSetsMaxRedirectsZero()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['allow_redirects' => false])->wait();

        $this->assertSame(0, $spy->options['max_redirects']);
    }

    public function testAllowRedirectsArrayMapsMaxField()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['allow_redirects' => ['max' => 3]])->wait();

        $this->assertSame(3, $spy->options['max_redirects']);
    }

    public function testAllowRedirectsTrueLeavesSymfonyDefault()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['allow_redirects' => true])->wait();

        // We must not pass max_redirects when allow_redirects=true so Symfony keeps its own default.
        $this->assertArrayNotHasKey('max_redirects', $spy->options);
    }

    public function testBufferIsAlwaysFalse()
    {
        // The handler always drives I/O via stream(), so buffer is always false
        // regardless of the Guzzle 'stream' option.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [])->wait();

        $this->assertFalse($spy->options['buffer']);
    }

    public function testHttp10IsPreservedEvenWithAutoUpgrade()
    {
        [$handler, $spy] = $this->makeHandler();
        $handler(new Request('GET', 'https://example.com/', [], null, '1.0'), [])->wait();

        // 1.0 must never be silently upgraded — lock it regardless of autoUpgradeHttpVersion.
        $this->assertSame('1.0', $spy->options['http_version']);
    }

    public function testHttp20NotForcedByDefault()
    {
        [$handler, $spy] = $this->makeHandler();
        $handler(new Request('GET', 'https://example.com/', [], null, '2.0'), [])->wait();

        // With autoUpgradeHttpVersion=true, Symfony picks the best version; don't override it.
        $this->assertNull($spy->options['http_version'] ?? null);
    }

    public function testHttpVersionForwardedWhenAutoUpgradeIsDisabled()
    {
        $spy = new \stdClass();
        $spy->options = null;
        $client = new MockHttpClient(static function (string $m, string $u, array $opts) use ($spy) {
            $spy->options = $opts;

            return new MockResponse('');
        });
        $handler = new GuzzleHttpHandler($client, autoUpgradeHttpVersion: false);

        $handler(new Request('GET', 'https://example.com/', [], null, '2.0'), [])->wait();

        $this->assertSame('2.0', $spy->options['http_version']);
    }

    public function testDefaultHttpVersionNotOverridden()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [])->wait();

        // Protocol 1.1 should not force a version; prepareRequest() normalises '' -> null.
        $this->assertNull($spy->options['http_version']);
    }

    public function testCryptoMethodIsForwarded()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['crypto_method' => \STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT])->wait();

        $this->assertSame(\STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT, $spy->options['crypto_method']);
    }

    public function testCertOptionWithPassword()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['cert' => ['/path/to/cert.pem', 'secret']])->wait();

        $this->assertSame('/path/to/cert.pem', $spy->options['local_cert']);
        $this->assertSame('secret', $spy->options['passphrase']);
    }

    public function testSslKeyOptionWithPassword()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['ssl_key' => ['/path/to/key.pem', 'keypass']])->wait();

        $this->assertSame('/path/to/key.pem', $spy->options['local_pk']);
        $this->assertSame('keypass', $spy->options['passphrase']);
    }

    public function testProgressCallbackIsWrapped()
    {
        [$handler, $spy] = $this->makeHandler();
        $capturedArgs = null;
        $progress = static function (int $dlTotal, int $dlNow, int $ulTotal, int $ulNow) use (&$capturedArgs): void {
            $capturedArgs = [$dlTotal, $dlNow, $ulTotal, $ulNow];
        };

        $handler(new Request('GET', 'https://example.com/'), ['progress' => $progress])->wait();

        $this->assertIsCallable($spy->options['on_progress']);
        // Invoke on_progress with known values and verify the argument mapping:
        // Symfony: (dlNow, dlTotal, info)  →  Guzzle: (dlTotal, dlNow, ulTotal, ulNow)
        ($spy->options['on_progress'])(42, 100, ['upload_content_length' => 200.0, 'size_upload' => 30.0]);
        $this->assertSame([100, 42, 200, 30], $capturedArgs);
    }

    public function testOnStatsIsCalledAfterSuccessfulResponse()
    {
        [$handler] = $this->makeHandler(static fn () => new MockResponse('body', ['http_code' => 201]));

        $statsCalled = false;
        $capturedStats = null;

        $response = $handler(new Request('GET', 'https://example.com/'), [
            'on_stats' => static function (\GuzzleHttp\TransferStats $stats) use (&$statsCalled, &$capturedStats) {
                $statsCalled = true;
                $capturedStats = $stats;
            },
        ])->wait();

        $this->assertTrue($statsCalled);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(201, $capturedStats?->getResponse()->getStatusCode());
        // Transfer time and handler stats are populated from Symfony's on_progress info.
        $this->assertIsFloat($capturedStats?->getTransferTime());
        $this->assertArrayHasKey('total_time', $capturedStats?->getHandlerStats());
    }

    public function testOnStatsIsCalledOnTransportError()
    {
        $client = new MockHttpClient(static fn () => new MockResponse('', ['error' => 'Connection refused']));
        $handler = new GuzzleHttpHandler($client);

        $statsCalled = false;
        $capturedStats = null;

        $promise = $handler(new Request('GET', 'https://example.com/'), [
            'on_stats' => static function (\GuzzleHttp\TransferStats $stats) use (&$statsCalled, &$capturedStats) {
                $statsCalled = true;
                $capturedStats = $stats;
            },
        ]);

        try {
            $promise->wait();
            $this->fail('Expected ConnectException');
        } catch (ConnectException) {
        }

        $this->assertTrue($statsCalled);
        $this->assertNull($capturedStats?->getResponse());
        $this->assertIsFloat($capturedStats?->getTransferTime());
    }

    public function testTransportExceptionBecomesConnectException()
    {
        $client = new MockHttpClient(static fn () => new MockResponse('', ['error' => 'Connection refused']));
        $handler = new GuzzleHttpHandler($client);

        $this->expectException(ConnectException::class);
        $handler(new Request('GET', 'https://example.com/'), [])->wait();
    }

    public function testWaitOnRejectedPromiseDoesNotBlockOtherResponses()
    {
        $client = new MockHttpClient([
            new MockResponse('', ['error' => 'Connection refused']),
            new MockResponse('ok', ['http_code' => 200]),
        ]);
        $handler = new GuzzleHttpHandler($client);

        $p1 = $handler(new Request('GET', 'https://example.com/fail'), []);
        $p2 = $handler(new Request('GET', 'https://example.com/ok'), []);

        try {
            $p1->wait();
            $this->fail('Expected ConnectException');
        } catch (ConnectException) {
        }

        // p2 must still resolve successfully after p1 was rejected.
        $r2 = $p2->wait();
        $this->assertSame(200, $r2->getStatusCode());
        $this->assertSame('ok', (string) $r2->getBody());
    }

    public function testDelayOptionPausesResponse()
    {
        [$handler] = $this->makeHandler();

        $start = microtime(true);
        $handler(new Request('GET', 'https://example.com/'), ['delay' => 50])->wait();

        $elapsed = (microtime(true) - $start) * 1000;
        $this->assertGreaterThanOrEqual(40, $elapsed);
    }

    public function testResponseBodyAndStatusAreReturned()
    {
        [$handler] = $this->makeHandler(
            static fn () => new MockResponse('{"result":"ok"}', ['http_code' => 202, 'response_headers' => ['content-type: application/json']]),
        );

        $response = $handler(new Request('GET', 'https://example.com/'), [])->wait();

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('{"result":"ok"}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
    }

    public function testOnHeadersCallbackIsInvoked()
    {
        [$handler] = $this->makeHandler(
            static fn () => new MockResponse('body', ['http_code' => 200, 'response_headers' => ['x-foo: bar']]),
        );

        $onHeadersCalled = false;
        $capturedResponse = null;

        $handler(new Request('GET', 'https://example.com/'), [
            'on_headers' => static function (ResponseInterface $response) use (&$onHeadersCalled, &$capturedResponse) {
                $onHeadersCalled = true;
                $capturedResponse = $response;
            },
        ])->wait();

        $this->assertTrue($onHeadersCalled);
        $this->assertSame(200, $capturedResponse?->getStatusCode());
    }

    public function testOnHeadersReceivesSameResponseInstanceAsResolvedPromise()
    {
        [$handler] = $this->makeHandler(
            static fn () => new MockResponse('body', ['http_code' => 200, 'response_headers' => ['x-foo: bar']]),
        );

        $onHeadersResponse = null;

        $resolvedResponse = $handler(new Request('GET', 'https://example.com/'), [
            'on_headers' => static function (ResponseInterface $response) use (&$onHeadersResponse) {
                $onHeadersResponse = $response;
            },
        ])->wait();

        $this->assertSame($onHeadersResponse, $resolvedResponse);
        $this->assertSame('body', (string) $resolvedResponse->getBody());
    }

    public function testOnHeadersExceptionRejectsWithRequestException()
    {
        [$handler] = $this->makeHandler(
            static fn () => new MockResponse('body', ['http_code' => 200]),
        );

        $promise = $handler(new Request('GET', 'https://example.com/'), [
            'on_headers' => static function () { throw new \RuntimeException('Abort!'); },
        ]);

        try {
            $promise->wait();
            $this->fail('Expected RequestException');
        } catch (RequestException $e) {
            $this->assertSame('Abort!', $e->getMessage());
            $this->assertNotNull($e->getResponse());
            $this->assertSame(200, $e->getResponse()->getStatusCode());
        }
    }

    // -- Async / concurrency --------------------------------------------------

    public function testInvokeReturnsPendingPromise()
    {
        [$handler] = $this->makeHandler();

        $promise = $handler(new Request('GET', 'https://example.com/'), []);

        // Before wait() is called the promise must be PENDING, not already resolved.
        $this->assertSame(PromiseInterface::PENDING, $promise->getState());
    }

    public function testConcurrentRequestsAreAllResolved()
    {
        $client = new MockHttpClient([
            new MockResponse('one', ['http_code' => 200]),
            new MockResponse('two', ['http_code' => 201]),
            new MockResponse('three', ['http_code' => 202]),
        ]);
        $handler = new GuzzleHttpHandler($client);

        $p1 = $handler(new Request('GET', 'https://example.com/1'), []);
        $p2 = $handler(new Request('GET', 'https://example.com/2'), []);
        $p3 = $handler(new Request('GET', 'https://example.com/3'), []);

        // All three are pending before any wait().
        $this->assertSame(PromiseInterface::PENDING, $p1->getState());
        $this->assertSame(PromiseInterface::PENDING, $p2->getState());
        $this->assertSame(PromiseInterface::PENDING, $p3->getState());

        // Waiting on p1 should drive the shared event loop and resolve all queued promises.
        $r1 = $p1->wait();

        $this->assertSame(PromiseInterface::FULFILLED, $p1->getState());
        // p2 and p3 may be resolved as a side-effect of driving the loop for p1.
        // At minimum they must resolve when individually waited on.
        $r2 = $p2->wait();
        $r3 = $p3->wait();

        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame('one', (string) $r1->getBody());
        $this->assertSame(201, $r2->getStatusCode());
        $this->assertSame('two', (string) $r2->getBody());
        $this->assertSame(202, $r3->getStatusCode());
        $this->assertSame('three', (string) $r3->getBody());
    }

    public function testPromiseThenChainIsExecutedAsynchronously()
    {
        [$handler] = $this->makeHandler(static fn () => new MockResponse('hello', ['http_code' => 200]));

        $thenCalled = false;
        $capturedBody = null;

        $promise = $handler(new Request('GET', 'https://example.com/'), [])
            ->then(static function (ResponseInterface $response) use (&$thenCalled, &$capturedBody) {
                $thenCalled = true;
                $capturedBody = (string) $response->getBody();

                return $response;
            });

        // The ->then() callback hasn't run yet.
        $this->assertFalse($thenCalled);

        $promise->wait();

        $this->assertTrue($thenCalled);
        $this->assertSame('hello', $capturedBody);
    }

    public function testCancelRemovesPendingRequest()
    {
        $client = new MockHttpClient([
            new MockResponse('first', ['http_code' => 200]),
            new MockResponse('second', ['http_code' => 200]),
        ]);
        $handler = new GuzzleHttpHandler($client);

        $p1 = $handler(new Request('GET', 'https://example.com/1'), []);
        $p2 = $handler(new Request('GET', 'https://example.com/2'), []);

        $p1->cancel();

        $this->assertSame(PromiseInterface::REJECTED, $p1->getState());

        // p2 must still be resolvable after p1 is cancelled.
        $r2 = $p2->wait();
        $this->assertSame(PromiseInterface::FULFILLED, $p2->getState());
        $this->assertSame(200, $r2->getStatusCode());
        $this->assertSame('second', (string) $r2->getBody());
    }

    // -- extra.curl pass-through ---

    #[RequiresPhpExtension('curl')]
    public function testUnmappedCurlOptIsForwardedToExtraCurl()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'curl' => [
                \CURLOPT_BUFFERSIZE => 65536,
            ],
        ])->wait();

        $this->assertSame(65536, $spy->options['extra']['curl'][\CURLOPT_BUFFERSIZE] ?? null);
    }

    #[RequiresPhpExtension('curl')]
    public function testKnownCurlOptIsNotDuplicatedInExtraCurl()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'curl' => [
                \CURLOPT_CAINFO => '/path/to/ca.pem', // mapped -> cafile
                \CURLOPT_BUFFERSIZE => 65536,         // unmapped -> extra.curl
            ],
        ])->wait();

        // Known opt: translated to the named Symfony option.
        $this->assertSame('/path/to/ca.pem', $spy->options['cafile']);
        // Known opt: must NOT appear in extra.curl (would be redundant / contradictory).
        $this->assertArrayNotHasKey(\CURLOPT_CAINFO, $spy->options['extra']['curl'] ?? []);
        // Unknown opt: forwarded.
        $this->assertSame(65536, $spy->options['extra']['curl'][\CURLOPT_BUFFERSIZE] ?? null);
    }

    #[RequiresPhpExtension('curl')]
    public function testKnownCurlOptWithFalsyValueIsNotForwardedToExtraCurl()
    {
        // CURLOPT_SSL_VERIFYPEER => false maps to verify_peer=false.
        // The mapping return value is (bool)false - must NOT be mistaken for
        // "unmapped" and end up in extra.curl as well.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'curl' => [\CURLOPT_SSL_VERIFYPEER => false],
        ])->wait();

        $this->assertFalse($spy->options['verify_peer']);
        $this->assertArrayNotHasKey(\CURLOPT_SSL_VERIFYPEER, $spy->options['extra']['curl'] ?? []);
    }

    #[RequiresPhpExtension('curl')]
    public function testExtraCurlIsMergedWithExistingExtras()
    {
        // Multiple unmapped opts all end up in the same sub-array.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'curl' => [
                \CURLOPT_LOW_SPEED_LIMIT => 1024,
                \CURLOPT_BUFFERSIZE => 32768,
            ],
        ])->wait();

        $this->assertSame(1024, $spy->options['extra']['curl'][\CURLOPT_LOW_SPEED_LIMIT]);
        $this->assertSame(32768, $spy->options['extra']['curl'][\CURLOPT_BUFFERSIZE]);
    }

    #[RequiresPhpExtension('curl')]
    public function testBlockedCurlOptIsDroppedSilently()
    {
        // Options that CurlHttpClient rejects in extra.curl must be silently
        // dropped rather than forwarded, to avoid a runtime exception.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), [
            'curl' => [
                \CURLOPT_VERBOSE => true,     // in $curloptsToCheck -> blocked
                \CURLOPT_BUFFERSIZE => 65536, // safe unmapped -> forwarded
            ],
        ])->wait();

        $this->assertArrayNotHasKey(\CURLOPT_VERBOSE, $spy->options['extra']['curl'] ?? []);
        $this->assertSame(65536, $spy->options['extra']['curl'][\CURLOPT_BUFFERSIZE] ?? null);
    }

    #[RequiresPhpExtension('curl')]
    public function testCurlOptMaxDirsIsMapped()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['curl' => [\CURLOPT_MAXREDIRS => 3]])->wait();

        $this->assertSame(3, $spy->options['max_redirects']);
    }

    #[RequiresPhpExtension('curl')]
    public function testCurlOptSslCipherListIsMapped()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['curl' => [\CURLOPT_SSL_CIPHER_LIST => 'ECDHE-RSA-AES256-GCM-SHA384']])->wait();

        $this->assertSame('ECDHE-RSA-AES256-GCM-SHA384', $spy->options['ciphers']);
    }

    #[RequiresPhpExtension('curl')]
    public function testCurlOptRefererIsMapped()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['curl' => [\CURLOPT_REFERER => 'https://referrer.example.com/']])->wait();

        $this->assertContains('Referer: https://referrer.example.com/', $spy->options['normalized_headers']['referer'] ?? []);
    }

    #[RequiresPhpExtension('curl')]
    public function testCurlOptCertInfoIsMapped()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['curl' => [\CURLOPT_CERTINFO => 1]])->wait();

        $this->assertTrue($spy->options['capture_peer_cert_chain']);
    }

    // --- sink ---

    public function testSinkAsFilePathWritesBodyToFile()
    {
        $path = tempnam(sys_get_temp_dir(), 'guzzle_sink_');
        $handler = new GuzzleHttpHandler(new MockHttpClient(new MockResponse('hello sink')));

        $handler(new Request('GET', 'https://example.com/'), ['sink' => $path])->wait();

        $this->assertSame('hello sink', file_get_contents($path));
        unlink($path);
    }

    public function testSinkAsResourceWritesBodyToResource()
    {
        $resource = fopen('php://temp', 'rw+');
        $handler = new GuzzleHttpHandler(new MockHttpClient(new MockResponse('hello resource')));

        $response = $handler(new Request('GET', 'https://example.com/'), ['sink' => $resource])->wait();

        rewind($resource);
        $this->assertSame('hello resource', stream_get_contents($resource));
        $this->assertSame('hello resource', (string) $response->getBody());
    }

    public function testSinkAsStreamInterfaceWritesBody()
    {
        $stream = \GuzzleHttp\Psr7\Utils::streamFor(fopen('php://temp', 'rw+'));
        $handler = new GuzzleHttpHandler(new MockHttpClient(new MockResponse('hello stream')));

        $handler(new Request('GET', 'https://example.com/'), ['sink' => $stream])->wait();

        $stream->rewind();
        $this->assertSame('hello stream', (string) $stream);
    }

    // --- read_timeout ---

    public function testReadTimeoutIsMappedToTimeout()
    {
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['read_timeout' => 4.5])->wait();

        $this->assertSame(4.5, $spy->options['timeout']);
    }

    // --- decode_content ---

    public function testDecodeContentFalsePreservesExplicitAcceptEncoding()
    {
        // Caller already chose an encoding: we must not override it.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/', ['Accept-Encoding' => 'br']), ['decode_content' => false])->wait();

        $this->assertContains('Accept-Encoding: br', $spy->options['normalized_headers']['accept-encoding'] ?? []);
    }

    public function testDecodeContentFalseInjectsIdentityEncoding()
    {
        // Without an explicit Accept-Encoding, Symfony would auto-decompress.
        // Injecting 'identity' tells the server not to compress, which in turn
        // prevents Symfony from decoding the body.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/'), ['decode_content' => false])->wait();

        $this->assertContains('Accept-Encoding: identity', $spy->options['normalized_headers']['accept-encoding'] ?? []);
    }

    public function testDecodeContentTrueRemovesExplicitAcceptEncoding()
    {
        [$handler, $spy] = $this->makeHandler();

        // Caller explicitly set Accept-Encoding; decode_content=true means Symfony
        // should manage it, so we strip the caller's value.
        $handler(new Request('GET', 'https://example.com/', ['Accept-Encoding' => 'gzip']), ['decode_content' => true])->wait();

        $this->assertArrayNotHasKey('accept-encoding', $spy->options['normalized_headers'] ?? []);
    }

    public function testDecodeContentStringRemovesExplicitAcceptEncoding()
    {
        // A string value (e.g. 'gzip') means "request this encoding and decode".
        // We let Symfony manage Accept-Encoding by stripping any explicit value.
        [$handler, $spy] = $this->makeHandler();

        $handler(new Request('GET', 'https://example.com/', ['Accept-Encoding' => 'br']), ['decode_content' => 'gzip'])->wait();

        $this->assertArrayNotHasKey('accept-encoding', $spy->options['normalized_headers'] ?? []);
    }

    // --- tick() ---

    public function testTickProgressesResponsesWithoutBlocking()
    {
        $handler = new GuzzleHttpHandler(new MockHttpClient(new MockResponse('tick body')));

        $promise = $handler(new Request('GET', 'https://example.com/'), []);

        // tick() should make non-blocking progress and eventually resolve the response.
        $handler->tick();

        $response = $promise->wait();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('tick body', (string) $response->getBody());
    }

    public function testTickProcessesMultipleConcurrentRequests()
    {
        $handler = new GuzzleHttpHandler(new MockHttpClient([
            new MockResponse('first'),
            new MockResponse('second'),
        ]));

        $p1 = $handler(new Request('GET', 'https://example.com/1'), []);
        $p2 = $handler(new Request('GET', 'https://example.com/2'), []);

        // Drive all pending requests via tick + execute.
        $handler->execute();

        $this->assertSame('first', (string) $p1->wait()->getBody());
        $this->assertSame('second', (string) $p2->wait()->getBody());
    }

    public function testTickBreaksAfterProcessingAvailableChunks()
    {
        $handler = new GuzzleHttpHandler(new MockHttpClient([
            new MockResponse('one'),
            new MockResponse('two'),
        ]));

        $p1 = $handler(new Request('GET', 'https://example.com/1'), []);
        $p2 = $handler(new Request('GET', 'https://example.com/2'), []);

        $handler->tick();
        $this->assertSame(0, (PromiseInterface::FULFILLED === $p1->getState()) + (PromiseInterface::FULFILLED === $p2->getState()));

        $handler->tick();
        $handler->tick();
        $this->assertSame(1, (PromiseInterface::FULFILLED === $p1->getState()) + (PromiseInterface::FULFILLED === $p2->getState()));

        $handler->tick();
        $handler->tick();
        $handler->tick();
        $this->assertSame(2, (PromiseInterface::FULFILLED === $p1->getState()) + (PromiseInterface::FULFILLED === $p2->getState()));
        $this->assertSame('one', (string) $p1->wait()->getBody());
        $this->assertSame('two', (string) $p2->wait()->getBody());
    }

    public function testNonStandardStatusCodeAbove599IsPreserved()
    {
        // GuzzleResponse rejects status codes >= 600 in its constructor, so the
        // handler must smuggle the real code through reflection.
        [$handler] = $this->makeHandler(
            static fn () => new MockResponse('proprietary', ['http_code' => 999])
        );

        $response = $handler(new Request('GET', 'https://example.com/'), [])->wait();

        $this->assertSame(999, $response->getStatusCode());
        $this->assertSame('proprietary', (string) $response->getBody());
    }

    /**
     * Build a handler backed by a spy MockHttpClient.
     *
     * The returned stdClass is populated with method / url / options after
     * each request is fired through the handler.
     */
    private function makeHandler(?callable $responseFactory = null): array
    {
        $spy = new \stdClass();
        $spy->method = null;
        $spy->url = null;
        $spy->options = null;

        $client = new MockHttpClient(
            static function (string $method, string $url, array $options) use ($responseFactory, $spy) {
                $spy->method = $method;
                $spy->url = $url;
                $spy->options = $options;

                return $responseFactory
                    ? ($responseFactory)($method, $url, $options)
                    : new MockResponse('{"ok":true}', ['http_code' => 200, 'response_headers' => ['content-type: application/json']]);
            },
        );

        return [new GuzzleHttpHandler($client), $spy];
    }
}
