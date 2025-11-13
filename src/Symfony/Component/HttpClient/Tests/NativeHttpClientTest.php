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
use Symfony\Bridge\PhpUnit\DnsMock;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

#[Group('dns-sensitive')]
class NativeHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new NativeHttpClient();
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
}
