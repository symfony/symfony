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

    public function testInformationalResponseStream()
    {
        self::markTestSkipped('NativeHttpClient doesn\'t support informational status codes.');
    }

    public function testTimeoutOnInitialize()
    {
        self::markTestSkipped('NativeHttpClient doesn\'t support opening concurrent requests.');
    }

    public function testTimeoutOnDestruct()
    {
        self::markTestSkipped('NativeHttpClient doesn\'t support opening concurrent requests.');
    }

    public function testHttp2PushVulcain()
    {
        self::markTestSkipped('NativeHttpClient doesn\'t support HTTP/2.');
    }

    public function testHttp2PushVulcainWithUnusedResponse()
    {
        self::markTestSkipped('NativeHttpClient doesn\'t support HTTP/2.');
    }
}
