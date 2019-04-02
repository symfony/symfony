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
                'http_code' => 200,
            ]);
        });
        $controller = new ProxyController($httpClient);

        $response = $controller($request, $expectedUrl, 'GET', ['timeout' => 10], ['custom-header' => 'myheadervalue']);

        $this->assertEquals($expectedBody, $response->getContent());
        $this->assertEquals('myheadervalue', $response->headers->get('custom-header'));
    }

    public function testInvalidRoute()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid proxy configuration for route "myproxy"');

        $request = new Request();
        $request->attributes->set('_route', 'myproxy');

        $expectedBody = '<html><body>Page body</body></html>';

        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($expectedBody) {
            $this->assertEquals('GET', $method);

            return new MockResponse($expectedBody, [
                'http_code' => 200,
            ]);
        });
        $controller = new ProxyController($httpClient);

        $controller($request, '');
    }
}
