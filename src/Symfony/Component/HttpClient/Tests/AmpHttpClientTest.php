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
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
    #[Group('transient-on-windows')]
    public function testGetRequest()
    {
        parent::testGetRequest();
    }

    #[Group('transient-on-windows')]
    public function testHeadRequest()
    {
        parent::testHeadRequest();
    }

    #[Group('transient-on-windows')]
    public function testNonBufferedGetRequest()
    {
        parent::testNonBufferedGetRequest();
    }

    #[Group('transient-on-windows')]
    public function testBufferSink()
    {
        parent::testBufferSink();
    }

    #[Group('transient-on-windows')]
    public function testConditionalBuffering()
    {
        parent::testConditionalBuffering();
    }

    #[Group('transient-on-windows')]
    public function testReentrantBufferCallback()
    {
        parent::testReentrantBufferCallback();
    }

    #[Group('transient-on-windows')]
    public function testThrowingBufferCallback()
    {
        parent::testThrowingBufferCallback();
    }

    #[Group('transient-on-windows')]
    public function testHttpVersion()
    {
        parent::testHttpVersion();
    }

    #[Group('transient-on-windows')]
    public function testChunkedEncoding()
    {
        parent::testChunkedEncoding();
    }

    #[Group('transient-on-windows')]
    public function testClientError()
    {
        parent::testClientError();
    }

    #[Group('transient-on-windows')]
    public function testIgnoreErrors()
    {
        parent::testIgnoreErrors();
    }

    #[Group('transient')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 5]);
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }

    public function testMaxConnectDurationPreservesAsync()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $responses = [];
        for ($i = 0; $i < 3; ++$i) {
            $responses[] = $client->request('GET', 'http://localhost:8057/', [
                'max_connect_duration' => 5.0,
            ]);
        }

        $start = microtime(true);
        foreach ($client->stream($responses) as $chunk) {
            if ($chunk->isFirst()) {
                // noop - connection completed
            }
        }
        $duration = microtime(true) - $start;

        $this->assertLessThan(2, $duration, 'Requests should be processed concurrently');
    }
}
