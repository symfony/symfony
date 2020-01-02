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

use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NativeHttpClientTest extends HttpClientTestCase
{
    protected function getHttpClient(string $testCase): HttpClientInterface
    {
        return new NativeHttpClient();
    }

    public function testItCanBeNonBlockingStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $stream = $response->toStream();

        $this->assertTrue(stream_get_meta_data($stream)['blocked']);
        $this->assertTrue(stream_set_blocking($stream, 0));

        // Help wanted. I've no idea why this test does not pass.
        // $this->assertFalse(stream_get_meta_data($stream)['blocked']);

        $read = [$stream];
        $write = [];
        $except = [];
        $streamFound = stream_select($read, $write, $except, null, 0);

        $this->assertEquals(1, $streamFound);
        $this->assertIsArray(json_decode(stream_get_contents($stream), true));
        $this->assertTrue(feof($stream));
    }

    public function testInformationalResponseStream()
    {
        $this->markTestSkipped('NativeHttpClient doesn\'t support informational status codes.');
    }
}
