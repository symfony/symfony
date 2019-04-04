<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\ProxyController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class ProxyControllerTest extends TestCase
{
    public function testRoute()
    {
        $request = new Request();

        $expectedBody = '<html><body>Page body</body></html>';
        $expectedUrl = 'https://www.example.com/success';

        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($expectedUrl, $expectedBody) {
            $this->assertEquals('GET', $method);
            $this->assertEquals($expectedUrl, $url);

            return new MockResponse($expectedBody, [
                'response_headers' => [
                    'Content-Type: application/json',
                    'Cache-Control: max-age: 60, public',
                    'Content-Encoding: gzip',
                    'Content-Transfer-Encoding: BASE64',
                ],
            ]);
        });
        $controller = new ProxyController($httpClient);

        $response = $controller(
            $request,
            $expectedUrl,
            'GET',
            [
                'timeout' => 10,
            ],
            [
                'CACHE_CONTROL' => 'private',
                'custom-header' => 'myheadervalue',
            ]
        );

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();

        $this->assertEquals($expectedBody, $body);
        $this->assertEquals('application/json', $response->headers->get('content-type'), 'Remote response headers are passed');
        $this->assertNull($response->headers->get('content-encoding'), 'Header Content-Encoding is removed, the HTTP client decodes responses');
        $this->assertNull($response->headers->get('content-transfer-encoding'), 'Header Content-Transfer-Encoding is removed, the HTTP client decodes responses');
        $this->assertEquals('private', $response->headers->get('cache-control'), 'Extra headers replace remote headers');
        $this->assertEquals('myheadervalue', $response->headers->get('custom-header'), 'Custom header added');
        $this->assertCount(4, $response->headers, 'No other header added');
    }

    public function testInvalidRoute()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid proxy configuration for route "myproxy": Invalid URL');

        $request = new Request();
        $request->attributes->set('_route', 'myproxy');

        $httpClient = new MockHttpClient(function ($method, $url, $options) {
            return new MockResponse('');
        });
        $controller = new ProxyController($httpClient);

        $controller($request, '');
    }
}
