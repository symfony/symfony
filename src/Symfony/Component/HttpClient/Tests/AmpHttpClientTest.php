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
    public function testGetRequest()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testGetRequest();
    }

    public function testHeadRequest()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testHeadRequest();
    }

    public function testNonBufferedGetRequest()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testNonBufferedGetRequest();
    }

    public function testBufferSink()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testBufferSink();
    }

    public function testConditionalBuffering()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testConditionalBuffering();
    }

    public function testReentrantBufferCallback()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testReentrantBufferCallback();
    }

    public function testThrowingBufferCallback()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testThrowingBufferCallback();
    }

    public function testHttpVersion()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testHttpVersion();
    }

    public function testChunkedEncoding()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testChunkedEncoding();
    }

    public function testClientError()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Too transient on Windows');
        }

        parent::testClientError();
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
