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
use Symfony\Component\HttpClient\Internal\AmpClientState;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

use function Amp\delay;

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

    #[TestWith(['/body', false])]
    #[TestWith(['/chunked', false])]
    #[TestWith(['/body', true])]
    public function testBackpressureWhileOtherFibersRun(string $path, bool $cancel)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $state = (new \ReflectionProperty($client, 'multi'))->getValue($client);
        $server = TestHttpServer::start(8067, __DIR__.'/Fixtures/backpressure');

        try {
            $response = $client->request('GET', 'http://localhost:8067'.$path, ['buffer' => false]);
            $stream = $response->toStream(false);
            $data = fread($stream, 8192);
            $this->assertNotEmpty($data);

            delay(0.2);
            $this->assertNotEmpty($state->handlesActivity);
            $this->assertAmpPendingDataIsBounded($state);

            if ($cancel) {
                $response->cancel();
                fclose($stream);
                delay(0);

                $this->assertSame([], $state->handlesActivity);
                $this->assertSame([], $state->openHandles);

                return;
            }

            $actual = hash_init('sha256');
            hash_update($actual, $data);
            $length = \strlen($data);
            $reads = 0;

            while (!feof($stream)) {
                $data = fread($stream, 8192);
                hash_update($actual, $data);
                $length += \strlen($data);

                if (0 === ++$reads % 64) {
                    delay(0.01);
                    $this->assertAmpPendingDataIsBounded($state);
                }
            }

            $this->assertSame(4194304, $length);
            $this->assertSame($this->getAmpBackpressureBodyHash(), hash_final($actual));
        } finally {
            $server->stop();
        }
    }

    public function testBackpressureReleasesAResponseWhenAnotherResponseIsSelected()
    {
        $client = new AmpHttpClient(['timeout' => 2], null, 1);
        $state = (new \ReflectionProperty($client, 'multi'))->getValue($client);
        $server = TestHttpServer::start(8067, __DIR__.'/Fixtures/backpressure');

        try {
            $first = $client->request('GET', 'http://localhost:8067/body', ['buffer' => false]);
            $stream = $first->toStream(false);
            $data = fread($stream, 8192);
            $this->assertNotEmpty($data);

            delay(0.2);
            $this->assertNotEmpty($state->handlesActivity);
            $this->assertAmpPendingDataIsBounded($state);

            $second = $client->request('GET', 'http://localhost:8067/');
            $this->assertSame('Success', $second->getContent());

            $actual = hash_init('sha256');
            hash_update($actual, $data);
            $this->assertSame(4194304, \strlen($data) + hash_update_stream($actual, $stream));
            $this->assertSame($this->getAmpBackpressureBodyHash(), hash_final($actual));
        } finally {
            $server->stop();
        }
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

    public function testShutdownResetClosesTheConnectionPools()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $state = (new \ReflectionProperty($client, 'multi'))->getValue($client);
        $client->request('GET', 'http://127.0.0.1:8057/json')->getContent();
        $pool = \WeakReference::create(current((new \ReflectionProperty($state, 'clients'))->getValue($state))[0]);

        $client->reset();
        $this->assertNotNull($pool->get());

        $client->reset(true);
        $this->assertNull($pool->get());

        $this->assertSame(200, $client->request('GET', 'http://127.0.0.1:8057/json')->getStatusCode());
    }

    private function assertAmpPendingDataIsBounded(AmpClientState $state): void
    {
        $pendingBytes = 0;
        foreach ($state->handlesActivity as $activity) {
            foreach ($activity as $chunk) {
                $pendingBytes += \is_string($chunk) ? \strlen($chunk) : 0;
            }
        }

        $this->assertLessThanOrEqual(1048576 + 8192, $pendingBytes);
    }

    private function getAmpBackpressureBodyHash(): string
    {
        $hash = hash_init('sha256');
        for ($i = 0; $i < 256; ++$i) {
            hash_update($hash, str_repeat(\chr($i), 16384));
        }

        return hash_final($hash);
    }
}
