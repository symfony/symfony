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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpClientKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpClientKernelTest extends TestCase
{
    public function testHandlePassesMaxRedirectsHttpClientOption()
    {
        $request = new Request();
        $request->attributes->set('http_client_options', ['max_redirects' => 50]);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

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

    #[DataProvider('provideIncrementalHeaders')]
    public function testHandleStreamsIncrementalResponse(string $incremental)
    {
        $client = new MockHttpClient(new MockResponse(['foo', 'bar'], [
            'http_code' => 201,
            'response_headers' => [
                'Cache-Control' => 'max-age=60',
                'Incremental' => $incremental,
                'X-Body-File' => '/etc/passwd',
                'X-Body-Eval' => 'ESI',
                'X-Content-Digest' => 'en0123',
            ],
        ]));

        $response = (new HttpClientKernel($client))->handle(Request::create('https://example.com/'));

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('max-age=60', $response->headers->get('Cache-Control'));
        $this->assertSame($incremental, $response->headers->get('Incremental'));
        $this->assertFalse($response->headers->has('X-Body-File'));
        $this->assertFalse($response->headers->has('X-Body-Eval'));
        $this->assertFalse($response->headers->has('X-Content-Digest'));
        $this->assertSame('foobar', $this->sendContent($response));
    }

    public static function provideIncrementalHeaders(): iterable
    {
        yield ['?1'];
        yield ['?1;a=b'];
        yield ['?1;a;b=?0'];
        yield ['?1;a="x,y";b="\\"\\\\"'];
        yield ['?1; a=-12.345;b=123456789012345;c=tok/en:x;d=:aGk=:'];
        yield ['?1;a=@1700000000;b=%"caf%c3%a9"'];
    }

    #[DataProvider('provideNonIncrementalHeaders')]
    public function testHandleBuffersNonIncrementalResponse(array $headers)
    {
        $client = new MockHttpClient(new MockResponse(['foo', 'bar'], [
            'http_code' => 201,
            'response_headers' => ['Cache-Control' => 'max-age=60'] + $headers,
        ]));

        $response = (new HttpClientKernel($client))->handle(Request::create('https://example.com/'));

        $this->assertNotInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('max-age=60', $response->headers->get('Cache-Control'));
        $this->assertSame('foobar', $response->getContent());
    }

    public static function provideNonIncrementalHeaders(): iterable
    {
        yield 'absent' => [[]];
        yield 'false' => [['Incremental' => '?0']];
        yield 'integer' => [['Incremental' => '1']];
        yield 'token' => [['Incremental' => 'true']];
        yield 'longer integer' => [['Incremental' => '?10']];
        yield 'space before parameters' => [['Incremental' => '?1 ;a']];
        yield 'list' => [['Incremental' => '?1, ?1']];
        yield 'empty parameter' => [['Incremental' => '?1;']];
        yield 'missing parameter key' => [['Incremental' => '?1;=x']];
        yield 'uppercase parameter key' => [['Incremental' => '?1;A']];
        yield 'missing parameter value' => [['Incremental' => '?1;a=']];
        yield 'unterminated string' => [['Incremental' => '?1;a="x']];
        yield 'decimal with four fractional digits' => [['Incremental' => '?1;a=1.2345']];
        yield 'uppercase percent-encoding' => [['Incremental' => '?1;a=%"%C3%A9"']];
        yield 'several field lines' => [['Incremental' => ['?1', '?1']]];
    }

    public function testHandleDisablesBufferingOfIncrementalResponses()
    {
        $mock = new MockResponse('foo', ['response_headers' => ['Incremental' => '?1']]);

        (new HttpClientKernel(new MockHttpClient($mock)))->handle(Request::create('https://example.com/'));

        $buffer = $mock->getRequestOptions()['buffer'];
        $this->assertFalse($buffer(['incremental' => ['?1']]));
        $this->assertTrue($buffer(['incremental' => ['?0']]));
        $this->assertTrue($buffer([]));
    }

    public function testHandleKeepsBufferOption()
    {
        $mock = new MockResponse('foo', ['response_headers' => ['Incremental' => '?1']]);
        $request = Request::create('https://example.com/');
        $request->attributes->set('http_client_options', ['buffer' => true]);

        (new HttpClientKernel(new MockHttpClient($mock)))->handle($request);

        $this->assertTrue($mock->getRequestOptions()['buffer']);
    }

    public function testHandleReadsIncrementalBodyWhenSent()
    {
        $client = new MockHttpClient(new MockResponse((static function () {
            yield 'foo';
            yield new TransportException('Connection reset.');
        })(), ['response_headers' => ['Incremental' => '?1']]));

        $response = (new HttpClientKernel($client))->handle(Request::create('https://example.com/'));

        $output = '';
        ob_start(static function (string $chunk) use (&$output) {
            $output .= $chunk;

            return '';
        });

        try {
            $response->sendContent();
            $this->fail('The transport error should be thrown while sending the content.');
        } catch (TransportException $e) {
            $this->assertSame('Connection reset.', $e->getMessage());
        } finally {
            ob_end_clean();
        }

        $this->assertSame('foo', $output);
    }

    public function testHandleWaitsForIncrementalBodyAcrossIdleTimeouts()
    {
        $client = new MockHttpClient(new MockResponse((static function () {
            yield 'foo';
            yield '';
            yield 'bar';
        })(), ['response_headers' => ['Incremental' => '?1']]));

        $response = (new HttpClientKernel($client))->handle(Request::create('https://example.com/'));

        $this->assertSame('foobar', $this->sendContent($response));
    }

    public function testHandleThrowsOnIncrementalErrorResponseWhenNotCatching()
    {
        $client = new MockHttpClient(new MockResponse('foo', [
            'http_code' => 500,
            'response_headers' => ['Incremental' => '?1'],
        ]));

        $this->expectException(ServerException::class);

        (new HttpClientKernel($client))->handle(Request::create('https://example.com/'), HttpKernelInterface::MAIN_REQUEST, false);
    }

    private function sendContent(Response $response): string
    {
        $output = '';
        ob_start(static function (string $chunk) use (&$output) {
            $output .= $chunk;

            return '';
        });

        try {
            $response->sendContent();
        } finally {
            ob_end_clean();
        }

        return $output;
    }
}
