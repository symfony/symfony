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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\HttpCache\Store;

class CachingHttpClientTest extends TestCase
{
    public function testRequestHeaders()
    {
        $options = [
            'headers' => [
                'Application-Name' => 'test1234',
                'Test-Name-Header' => 'test12345',
            ],
        ];

        $mockClient = new MockHttpClient();
        $store = new Store(sys_get_temp_dir().'/sf_http_cache');
        $client = new CachingHttpClient($mockClient, $store, $options);

        $response = $client->request('GET', 'http://example.com/foo-bar');

        rmdir(sys_get_temp_dir().'/sf_http_cache');
        self::assertInstanceOf(MockResponse::class, $response);
        self::assertSame($response->getRequestOptions()['normalized_headers']['application-name'][0], 'Application-Name: test1234');
        self::assertSame($response->getRequestOptions()['normalized_headers']['test-name-header'][0], 'Test-Name-Header: test12345');
    }
}
