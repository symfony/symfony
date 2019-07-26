<?php

declare(strict_types=1);

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Middleware\MiddlewareInterface;
use Symfony\Component\HttpClient\Middleware\MiddlewareStack;
use Symfony\Component\HttpClient\MiddlewareHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MiddlewareHttpClientTest extends TestCase
{
    public function testMultipleChangedRequestMiddleware(): void
    {
        $httpClient = ScopingHttpClient::forBaseUri(new MockHttpClient(), 'http://example.com');
        $stack = new MiddlewareStack(
            new ModifyUserDataMiddleware(),
            new ModifyPathMiddleware(),
            new ModifyResponseMiddleware()
        );
        $middlewareHttpClient = new MiddlewareHttpClient($httpClient, $stack);

        $response = $middlewareHttpClient->request('GET', '/test/foo', ['user_data' => 10]);

        $this->assertSame('GET', $response->getInfo('http_method'));
        $this->assertSame('http://example.com/test/bar', $response->getInfo('url'));
        $this->assertSame(42, $response->getInfo('user_data'));
    }
}

class ModifyUserDataMiddleware implements MiddlewareInterface
{
    public function __invoke(string $method, string $url, array $options, callable $next): ResponseInterface
    {
        $options['user_data'] = $options['user_data'] === 10 ? 42 : 10;

        return $next($method, $url, $options);
    }
}

class ModifyPathMiddleware implements MiddlewareInterface
{
    public function __invoke(string $method, string $url, array $options, callable $next): ResponseInterface
    {
        $url = str_replace('foo', 'bar', $url);

        return $next($method, $url, $options);
    }
}

class ModifyResponseMiddleware implements MiddlewareInterface
{
    public function __invoke(string $method, string $url, array $options, callable $next): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $next($method, $url, $options);

        if ('foo' === $response->getContent()) {
            return new MockResponse('bar');
        }

        return $response;
    }
}
