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

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\StaticSocketConnector;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Amp\Socket\socketConnector;

/**
 * @group dns-sensitive
 */
class AmpHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new AmpHttpClient(['verify_peer' => false, 'verify_host' => false, 'timeout' => 5]);
    }

    public function testUnixSocket()
    {
        if (\PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Unix sockets only support PHP >= 8.4.');
        }

        if (strtoupper(substr(\PHP_OS, 0, 3)) === 'WIN') {
            $this->markTestSkipped('Unix sockets are not supported on Windows.');
        }

        $client = new AmpHttpClient(['bindto' => '/var/run/docker.sock']);

        $client = $client->withOptions(['base_uri' => 'http://docker']);

        $response = $client->request('GET', '/info');

        $this->assertSame(200, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertNotEmpty($content['ID']);
    }

    public function testProxy()
    {
        $this->markTestSkipped('A real proxy server would be needed.');
    }
}
