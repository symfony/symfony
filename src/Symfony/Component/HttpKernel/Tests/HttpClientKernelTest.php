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

        $response = self::createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);

        $client = self::createMock(HttpClientInterface::class);
        $client
            ->expects(self::once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) use ($request, $response) {
                self::assertSame($request->getMethod(), $method);
                self::assertSame($request->getUri(), $uri);
                self::assertArrayHasKey('max_redirects', $options);
                self::assertSame(50, $options['max_redirects']);

                return $response;
            });

        $kernel = new HttpClientKernel($client);
        $kernel->handle($request);
    }
}
