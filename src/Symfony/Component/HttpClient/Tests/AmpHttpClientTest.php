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

use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @group dns-sensitive
 */
class AmpHttpClientTest extends HttpClientTestCase
{
    /**
     * @group transient
     * @group transient-on-windows
     */
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    /**
     * @group transient-on-windows
     */
    public function testResolve()
    {
        parent::testResolve();
    }

    /**
     * @requires extension zlib
     */
    public function testZlibStreamBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $body = fopen('compress.zlib://data:application/gzip;base64,'.base64_encode(gzencode('foo=0123456789')), 'r');

        $response = $client->request('POST', 'http://localhost:8057/post', ['body' => $body]);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 5]);
    }

    public static function getRedirectWithHostHeaderTests()
    {
        // unlike curl and the native client, amphp keeps the user-provided Host header when the authority doesn't change
        return [
            'same host and port' => ['url' => 'http://localhost:8057/custom', 'redirectWithAuth' => true, 'expectedHost' => 'foo.example.com'],
            'other port' => ['url' => 'http://localhost:8067/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:8057'],
            'other host' => ['url' => 'http://127.0.0.1:8057/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:8057'],
        ];
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }
}
