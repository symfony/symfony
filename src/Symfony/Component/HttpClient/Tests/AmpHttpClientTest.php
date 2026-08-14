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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
    #[Group('transient')]
    public function testNonBlockingStream()
    {
        parent::testNonBlockingStream();
    }

    #[Group('transient-on-windows')]
    public function testResolve()
    {
        parent::testResolve();
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
}
