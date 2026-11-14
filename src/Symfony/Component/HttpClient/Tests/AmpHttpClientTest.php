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
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('dns-sensitive')]
class AmpHttpClientTest extends HttpClientTestCase
{
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

    #[RequiresPhpExtension('zlib')]
    public function testZlibStreamBody()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $body = fopen('compress.zlib://data:application/gzip;base64,'.base64_encode(gzencode('foo=0123456789')), 'r');

        $response = $client->request('POST', 'http://localhost:'.self::port().'/post', ['body' => $body]);

        $this->assertSame(['foo' => '0123456789', 'REQUEST_METHOD' => 'POST'], $response->toArray());
    }

    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 30]);
    }

    public static function getRedirectWithHostHeaderTests()
    {
        // unlike curl and the native client, amphp keeps the user-provided Host header when the authority doesn't change
        return [
            'same host and port' => ['url' => 'http://localhost:'.self::port().'/custom', 'redirectWithAuth' => true, 'expectedHost' => 'foo.example.com'],
            'other port' => ['url' => 'http://localhost:'.self::port(8067).'/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:'.self::port()],
            'other host' => ['url' => 'http://127.0.0.1:'.self::port().'/custom', 'redirectWithAuth' => false, 'expectedHost' => 'localhost:'.self::port()],
        ];
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }

    #[TestWith(['/'])]
    #[TestWith(['/timeout-body'])]
    public function testEndOfResponseIsNotDelayed(string $path)
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:'.self::port().$path);
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
        $response = $client->request('POST', 'http://localhost:'.self::port().'/post', ['body' => static function () {
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
        $completed = $client->request($method, 'http://localhost:'.self::port().'/head');
        $this->assertSame(200, $completed->getStatusCode());

        $response = $client->request('GET', 'http://localhost:'.self::port().'/', ['timeout' => 2]);

        $start = hrtime(true);
        $response->getContent();

        $this->assertLessThan(0.5, (hrtime(true) - $start) / 1E9);
    }
}
