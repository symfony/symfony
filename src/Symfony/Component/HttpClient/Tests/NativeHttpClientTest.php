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
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\HttpClient\Internal\NativeClientState;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

#[Group('dns-sensitive')]
class NativeHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new NativeHttpClient([], 'testBackpressureDuringInterleavedRequests' === $testCase ? 1 : 6);
    }

    #[TestWith(['/body', false])]
    #[TestWith(['/chunked', false])]
    #[TestWith(['/body', true])]
    public function testBackpressureDuringInterleavedRequests(string $path, bool $cancel)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $state = (new \ReflectionProperty($client, 'multi'))->getValue($client);
        $server = TestHttpServer::start(8067, __DIR__.'/Fixtures/backpressure');
        $driverServer = TestHttpServer::start(8068, __DIR__.'/Fixtures/backpressure');

        try {
            $response = $client->request('GET', 'http://127.0.0.1:8067'.$path, ['buffer' => false]);
            $this->assertSame(200, $response->getStatusCode());
            usleep(100000);

            $actual = hash_init('sha256');
            $length = 0;

            for ($i = 0; $i < 200; ++$i) {
                $driver = $client->request('GET', 'http://localhost:8068/', ['buffer' => false]);
                $received = false;

                foreach ($client->stream([$response, $driver]) as $current => $chunk) {
                    $this->assertSame($response, $current);
                    if ('' === $data = $chunk->getContent()) {
                        continue;
                    }

                    hash_update($actual, $data);
                    $length += \strlen($data);
                    $received = true;
                    break;
                }

                $driver->cancel();
                $driverServer->getIncrementalErrorOutput();
                $this->assertTrue($received);
                $this->assertNativePendingDataIsBounded($state);
                usleep(1000);
            }

            if ($cancel) {
                $response->cancel();

                $this->assertSame([], $state->handlesActivity);
                $this->assertSame([], $state->openHandles);
                $this->assertSame(200, $client->request('GET', 'http://localhost:8057/')->getStatusCode());

                return;
            }

            $next = $client->request('GET', 'http://127.0.0.1:8067/', ['timeout' => 2]);
            $this->assertSame('Success', $next->getContent());

            $stream = $response->toStream(false);
            $length += hash_update_stream($actual, $stream);
            fclose($stream);

            $expected = hash_init('sha256');
            for ($i = 0; $i < 256; ++$i) {
                hash_update($expected, str_repeat(\chr($i), 16384));
            }

            $this->assertSame(4194304, $length);
            $this->assertSame(hash_final($expected), hash_final($actual));
        } finally {
            $driverServer->stop();
            $server->stop();
        }
    }

    public function testInformationalResponseStream()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support informational status codes.');
    }

    public function testTimeoutOnInitialize()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support opening concurrent requests.');
    }

    public function testTimeoutOnDestruct()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support opening concurrent requests.');
    }

    public function testHttp2PushVulcain()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support HTTP/2.');
    }

    public function testHttp2PushVulcainWithUnusedResponse()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support HTTP/2.');
    }

    public function testHttp2WithPriorKnowledge()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support HTTP/2.');
    }

    public function testIPv6Resolve()
    {
        TestHttpServer::start(-8087);

        DnsMock::withMockedHosts([
            'symfony.com' => [
                [
                    'type' => 'AAAA',
                    'ipv6' => '::1',
                ],
            ],
        ]);

        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://symfony.com:8087/');

        $this->assertSame(200, $response->getStatusCode());

        DnsMock::withMockedHosts([]);
    }

    public function testUnixSocket()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support binding to unix sockets.');
    }

    private function assertNativePendingDataIsBounded(NativeClientState $state): void
    {
        $pendingBytes = 0;
        foreach ($state->handlesActivity as $activity) {
            foreach ($activity as $chunk) {
                $pendingBytes += \is_string($chunk) ? \strlen($chunk) : 0;
            }
        }

        $this->assertLessThanOrEqual(1048576 + 16384, $pendingBytes);
    }
}
