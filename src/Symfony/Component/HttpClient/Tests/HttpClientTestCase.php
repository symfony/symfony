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

use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    public function testAcceptHeader()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');
        $requestHeaders = $response->toArray();

        $this->assertSame('*/*', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => 'foo/bar',
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertSame('foo/bar', $requestHeaders['HTTP_ACCEPT']);

        $response = $client->request('GET', 'http://localhost:8057', [
            'headers' => [
                'Accept' => null,
            ],
        ]);
        $requestHeaders = $response->toArray();

        $this->assertArrayNotHasKey('HTTP_ACCEPT', $requestHeaders);
    }

    public function testInfoOnCanceledResponse()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testBufferSink()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testConditionalBuffering()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testReentrantBufferCallback()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testThrowingBufferCallback()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testMaxDuration()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }
}
