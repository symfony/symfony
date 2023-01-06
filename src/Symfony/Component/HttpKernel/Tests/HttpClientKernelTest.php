<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpClientKernel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpClientKernelTest extends TestCase
{
    public function testHandlePassesMaxRedirectsHttpClientOption()
    {
        $request = new Request();
        $request->attributes->set('http_client_options', ['max_redirects' => 50]);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) use ($request, $response) {
                $this->assertSame($request->getMethod(), $method);
                $this->assertSame($request->getUri(), $uri);
                $this->assertArrayHasKey('max_redirects', $options);
                $this->assertSame(50, $options['max_redirects']);

                return $response;
            });

        $kernel = new HttpClientKernel($client);
        $kernel->handle($request);
    }
}
