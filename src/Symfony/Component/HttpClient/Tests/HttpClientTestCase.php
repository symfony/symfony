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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Test\HttpClientTestCase as BaseHttpClientTestCase;

abstract class HttpClientTestCase extends BaseHttpClientTestCase
{
    public function testMaxDuration()
    {
        $this->markTestSkipped('Implemented as of version 4.4');
    }

    public function testToStream()
    {
        $client = $this->getHttpClient(__FUNCTION__);

        $response = $client->request('GET', 'http://localhost:8057');

        $stream = $response->toStream();

        $this->assertSame("{\n    \"SER", fread($stream, 10));
        $this->assertSame('VER_PROTOCOL', fread($stream, 12));
        $this->assertFalse(feof($stream));
        $this->assertTrue(rewind($stream));

        $this->assertIsArray(json_decode(fread($stream, 1024), true));
        $this->assertSame('', fread($stream, 1));
        $this->assertTrue(feof($stream));
    }

    public function testConditionalBuffering()
    {
        $client = $this->getHttpClient(__FUNCTION__);
        $response = $client->request('GET', 'http://localhost:8057');
        $firstContent = $response->getContent();
        $secondContent = $response->getContent();

        $this->assertSame($firstContent, $secondContent);

        $response = $client->request('GET', 'http://localhost:8057', ['buffer' => function () { return false; }]);
        $response->getContent();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot get the content of the response twice: buffering is disabled.');
        $response->getContent();
    }
}
