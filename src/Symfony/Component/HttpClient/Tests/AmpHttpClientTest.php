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

use Amp\Dns;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // amphp/dns loads the system configuration on first use, which runs PowerShell on Windows and can outlast the timeout of the first test
        Dns\resolve('localhost');
    }

    #[Group('transient')]
    #[Group('transient-on-windows')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    #[Group('transient-on-windows')]
    public function testResolve()
    {
        parent::testResolve();
    }

    #[Group('transient-on-windows')]
    public function testNotATimeout()
    {
        parent::testNotATimeout();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutIsNotAFatalError()
    {
        parent::testTimeoutIsNotAFatalError();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutOnInitialize()
    {
        parent::testTimeoutOnInitialize();
    }

    #[Group('transient-on-windows')]
    public function testTimeoutOnDestruct()
    {
        parent::testTimeoutOnDestruct();
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 30]);
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

    #[TestWith(['http://localhost:8057/'])]
    #[TestWith(['http://localhost:8057/timeout-body'])]
    public function testEndOfResponseIsNotDelayed(string $url)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', $url);
        $lastData = null;

        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isLast()) {
                $this->assertLessThan(0.5, (hrtime(true) - $lastData) / 1E9);
            } elseif ('' !== $chunk->getContent()) {
                $lastData = hrtime(true);
            }
        }
    }

    public function testFailureBeforeHeadersIsNotDelayed()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('POST', 'http://localhost:8057/post', ['body' => static function () {
            throw new \RuntimeException('Cannot read the body.');
        }]);

        $start = hrtime(true);

        try {
            $response->getHeaders();
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertSame('Cannot read the body.', $e->getMessage());
        }

        $this->assertLessThan(0.5, (hrtime(true) - $start) / 1E9);
    }

    #[TestWith(['GET'])]
    #[TestWith(['HEAD'])]
    public function testCompletedResponseDoesNotBlockOtherResponses(string $method)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $completed = $client->request($method, 'http://localhost:8057/head');
        $this->assertSame(200, $completed->getStatusCode());

        $response = $client->request('GET', 'http://localhost:8057/', ['timeout' => 2]);

        $start = hrtime(true);
        $response->getContent();

        $this->assertLessThan(0.5, (hrtime(true) - $start) / 1E9);
    }
}
