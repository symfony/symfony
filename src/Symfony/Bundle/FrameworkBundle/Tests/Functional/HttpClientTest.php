<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

class HttpClientTest extends AbstractWebTestCase
{
    public function testHttpClientAssertions()
    {
        $client = $this->createClient(['test_case' => 'HttpClient', 'root_config' => 'config.yml', 'debug' => true]);
        $client->enableProfiler();
        $client->request('GET', '/http_client_call');

        $this->assertHttpClientRequest('https://symfony.com/');
        $this->assertHttpClientRequest('https://symfony.com/', httpClientId: 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', 'foo', httpClientId: 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], httpClientId: 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], httpClientId: 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/', 'POST', ['foo' => 'bar'], ['X-Test-Header' => 'foo'], 'symfony.http_client');
        $this->assertHttpClientRequest('https://symfony.com/doc/current/index.html', httpClientId: 'symfony.http_client');
        $this->assertNotHttpClientRequest('https://laravel.com', httpClientId: 'symfony.http_client');

        $this->assertHttpClientRequestCount(6, 'symfony.http_client');
    }
}
