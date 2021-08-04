<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Functional;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\ServerRequest as Psr7Request;
use Nyholm\Psr7\Stream as Psr7Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test to convert a request/response back and forth to make sure we do not loose data.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class CovertTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Psr7Request::class)) {
            $this->markTestSkipped('nyholm/psr7 is not installed.');
        }
    }

    /**
     * @dataProvider requestProvider
     *
     * @param Request|ServerRequestInterface                             $request
     * @param HttpFoundationFactoryInterface|HttpMessageFactoryInterface $firstFactory
     * @param HttpFoundationFactoryInterface|HttpMessageFactoryInterface $secondFactory
     */
    public function testConvertRequestMultipleTimes($request, $firstFactory, $secondFactory)
    {
        $temporaryRequest = $firstFactory->createRequest($request);
        $finalRequest = $secondFactory->createRequest($temporaryRequest);

        if ($finalRequest instanceof Request) {
            $this->assertEquals($request->getBasePath(), $finalRequest->getBasePath());
            $this->assertEquals($request->getBaseUrl(), $finalRequest->getBaseUrl());
            $this->assertEquals($request->getContent(), $finalRequest->getContent());
            $this->assertEquals($request->getEncodings(), $finalRequest->getEncodings());
            $this->assertEquals($request->getETags(), $finalRequest->getETags());
            $this->assertEquals($request->getHost(), $finalRequest->getHost());
            $this->assertEquals($request->getHttpHost(), $finalRequest->getHttpHost());
            $this->assertEquals($request->getMethod(), $finalRequest->getMethod());
            $this->assertEquals($request->getPassword(), $finalRequest->getPassword());
            $this->assertEquals($request->getPathInfo(), $finalRequest->getPathInfo());
            $this->assertEquals($request->getPort(), $finalRequest->getPort());
            $this->assertEquals($request->getProtocolVersion(), $finalRequest->getProtocolVersion());
            $this->assertEquals($request->getQueryString(), $finalRequest->getQueryString());
            $this->assertEquals($request->getRequestUri(), $finalRequest->getRequestUri());
            $this->assertEquals($request->getScheme(), $finalRequest->getScheme());
            $this->assertEquals($request->getSchemeAndHttpHost(), $finalRequest->getSchemeAndHttpHost());
            $this->assertEquals($request->getScriptName(), $finalRequest->getScriptName());
            $this->assertEquals($request->getUri(), $finalRequest->getUri());
            $this->assertEquals($request->getUser(), $finalRequest->getUser());
            $this->assertEquals($request->getUserInfo(), $finalRequest->getUserInfo());
        } elseif ($finalRequest instanceof ServerRequestInterface) {
            $strToLower = function ($arr) {
                foreach ($arr as $key => $value) {
                    yield strtolower($key) => $value;
                }
            };
            $this->assertEquals($request->getAttributes(), $finalRequest->getAttributes());
            $this->assertEquals($request->getCookieParams(), $finalRequest->getCookieParams());
            $this->assertEquals((array) $request->getParsedBody(), (array) $finalRequest->getParsedBody());
            $this->assertEquals($request->getQueryParams(), $finalRequest->getQueryParams());
            // PSR7 does not define a "withServerParams" so this is impossible to implement without knowing the PSR7 implementation.
            //$this->assertEquals($request->getServerParams(), $finalRequest->getServerParams());
            $this->assertEquals($request->getUploadedFiles(), $finalRequest->getUploadedFiles());
            $this->assertEquals($request->getMethod(), $finalRequest->getMethod());
            $this->assertEquals($request->getRequestTarget(), $finalRequest->getRequestTarget());
            $this->assertEquals((string) $request->getUri(), (string) $finalRequest->getUri());
            $this->assertEquals((string) $request->getBody(), (string) $finalRequest->getBody());
            $this->assertEquals($strToLower($request->getHeaders()), $strToLower($finalRequest->getHeaders()));
            $this->assertEquals($request->getProtocolVersion(), $finalRequest->getProtocolVersion());
        } else {
            $this->fail('$finalRequest must be an instance of PSR7 or a HTTPFoundation request');
        }
    }

    public function requestProvider()
    {
        $sfRequest = new Request(
            [
                'foo' => '1',
                'bar' => ['baz' => '42'],
            ],
            [
                'twitter' => [
                    '@dunglas' => 'Kévin Dunglas',
                    '@coopTilleuls' => 'Les-Tilleuls.coop',
                ],
                'baz' => '2',
            ],
            [
                'a2' => ['foo' => 'bar'],
            ],
            [
                'c1' => 'foo',
                'c2' => ['c3' => 'bar'],
            ],
            [
                'f1' => $this->createUploadedFile('F1', 'f1.txt', 'text/plain', \UPLOAD_ERR_OK),
                'foo' => ['f2' => $this->createUploadedFile('F2', 'f2.txt', 'text/plain', \UPLOAD_ERR_OK)],
            ],
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'dunglas.fr',
                'SERVER_NAME' => 'dunglas.fr',
                'SERVER_PORT' => null,
                'HTTP_X_SYMFONY' => '2.8',
                'REQUEST_URI' => '/testCreateRequest?foo=1&bar%5Bbaz%5D=42',
                'QUERY_STRING' => 'foo=1&bar%5Bbaz%5D=42',
            ],
            'Content'
        );

        $psr7Requests = [
            (new Psr7Request('POST', 'http://tnyholm.se/foo/?bar=biz'))
                ->withQueryParams(['bar' => 'biz']),
            new Psr7Request('GET', 'https://hey-octave.com/'),
            new Psr7Request('GET', 'https://hey-octave.com:443/'),
            new Psr7Request('GET', 'https://hey-octave.com:4242/'),
            new Psr7Request('GET', 'http://hey-octave.com:80/'),
        ];

        $nyholmFactory = new Psr17Factory();
        $psr17Factory = new PsrHttpFactory($nyholmFactory, $nyholmFactory, $nyholmFactory, $nyholmFactory);
        $symfonyFactory = new HttpFoundationFactory();

        return array_merge([
            [$sfRequest, $psr17Factory, $symfonyFactory],
        ], array_map(function ($psr7Request) use ($symfonyFactory, $psr17Factory) {
            return [$psr7Request, $symfonyFactory, $psr17Factory];
        }, $psr7Requests));
    }

    /**
     * @dataProvider responseProvider
     *
     * @param Response|ResponseInterface                                 $response
     * @param HttpFoundationFactoryInterface|HttpMessageFactoryInterface $firstFactory
     * @param HttpFoundationFactoryInterface|HttpMessageFactoryInterface $secondFactory
     */
    public function testConvertResponseMultipleTimes($response, $firstFactory, $secondFactory)
    {
        $temporaryResponse = $firstFactory->createResponse($response);
        $finalResponse = $secondFactory->createResponse($temporaryResponse);

        if ($finalResponse instanceof Response) {
            $this->assertEquals($response->getAge(), $finalResponse->getAge());
            $this->assertEquals($response->getCharset(), $finalResponse->getCharset());
            $this->assertEquals($response->getContent(), $finalResponse->getContent());
            $this->assertEquals($response->getDate(), $finalResponse->getDate());
            $this->assertEquals($response->getEtag(), $finalResponse->getEtag());
            $this->assertEquals($response->getExpires(), $finalResponse->getExpires());
            $this->assertEquals($response->getLastModified(), $finalResponse->getLastModified());
            $this->assertEquals($response->getMaxAge(), $finalResponse->getMaxAge());
            $this->assertEquals($response->getProtocolVersion(), $finalResponse->getProtocolVersion());
            $this->assertEquals($response->getStatusCode(), $finalResponse->getStatusCode());
            $this->assertEquals($response->getTtl(), $finalResponse->getTtl());
        } elseif ($finalResponse instanceof ResponseInterface) {
            $strToLower = function ($arr) {
                foreach ($arr as $key => $value) {
                    yield strtolower($key) => $value;
                }
            };
            $this->assertEquals($response->getStatusCode(), $finalResponse->getStatusCode());
            $this->assertEquals($response->getReasonPhrase(), $finalResponse->getReasonPhrase());
            $this->assertEquals((string) $response->getBody(), (string) $finalResponse->getBody());
            $this->assertEquals($strToLower($response->getHeaders()), $strToLower($finalResponse->getHeaders()));
            $this->assertEquals($response->getProtocolVersion(), $finalResponse->getProtocolVersion());
        } else {
            $this->fail('$finalResponse must be an instance of PSR7 or a HTTPFoundation response');
        }
    }

    public function responseProvider()
    {
        $sfResponse = new Response(
            'Response content.',
            202,
            ['x-symfony' => ['3.4']]
        );

        if (method_exists(Cookie::class, 'create')) {
            $cookie = Cookie::create('city', 'Lille', new \DateTime('Wed, 13 Jan 2021 22:23:01 GMT'));
        } else {
            $cookie = new Cookie('city', 'Lille', new \DateTime('Wed, 13 Jan 2021 22:23:01 GMT'));
        }

        $sfResponse->headers->setCookie($cookie);
        $body = Psr7Stream::create();
        $status = 302;
        $headers = [
            'location' => ['http://example.com/'],
        ];
        $zendResponse = new Psr7Response($status, $headers, $body);

        $nyholmFactory = new Psr17Factory();
        $psr17Factory = new PsrHttpFactory($nyholmFactory, $nyholmFactory, $nyholmFactory, $nyholmFactory);
        $symfonyFactory = new HttpFoundationFactory();

        return [
            [$sfResponse, $psr17Factory, $symfonyFactory],
            [$zendResponse, $symfonyFactory, $psr17Factory],
        ];
    }

    private function createUploadedFile($content, $originalName, $mimeType, $error)
    {
        $path = tempnam(sys_get_temp_dir(), uniqid());
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, $mimeType, $error, true);
    }
}
