<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Factory;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @author Aurélien Pillevesse <aurelienpillevesse@hotmail.fr>
 */
class PsrHttpFactoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir();
    }

    /**
     * @dataProvider provideFactories
     */
    public function testCreateRequest(PsrHttpFactory $factory)
    {
        $stdClass = new \stdClass();
        $request = new Request(
            [
                'bar' => ['baz' => '42'],
                'foo' => '1',
            ],
            [
                'twitter' => [
                    '@dunglas' => 'Kévin Dunglas',
                    '@coopTilleuls' => 'Les-Tilleuls.coop',
                ],
                'baz' => '2',
            ],
            [
                'a1' => $stdClass,
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
                'HTTP_X_SYMFONY' => '2.8',
                'REQUEST_URI' => '/testCreateRequest?bar[baz]=42&foo=1',
                'QUERY_STRING' => 'bar[baz]=42&foo=1',
            ],
            'Content'
        );
        $request->headers->set(' X-Broken', 'abc');

        $psrRequest = $factory->createRequest($request);

        $this->assertSame('Content', $psrRequest->getBody()->__toString());

        $queryParams = $psrRequest->getQueryParams();
        $this->assertSame('1', $queryParams['foo']);
        $this->assertSame('42', $queryParams['bar']['baz']);

        $requestTarget = $psrRequest->getRequestTarget();
        $this->assertSame('/testCreateRequest?bar[baz]=42&foo=1', urldecode($requestTarget));

        $parsedBody = $psrRequest->getParsedBody();
        $this->assertSame('Kévin Dunglas', $parsedBody['twitter']['@dunglas']);
        $this->assertSame('Les-Tilleuls.coop', $parsedBody['twitter']['@coopTilleuls']);
        $this->assertSame('2', $parsedBody['baz']);

        $attributes = $psrRequest->getAttributes();
        $this->assertSame($stdClass, $attributes['a1']);
        $this->assertSame('bar', $attributes['a2']['foo']);

        $cookies = $psrRequest->getCookieParams();
        $this->assertSame('foo', $cookies['c1']);
        $this->assertSame('bar', $cookies['c2']['c3']);

        $uploadedFiles = $psrRequest->getUploadedFiles();
        $this->assertSame('F1', $uploadedFiles['f1']->getStream()->__toString());
        $this->assertSame('f1.txt', $uploadedFiles['f1']->getClientFilename());
        $this->assertSame('text/plain', $uploadedFiles['f1']->getClientMediaType());
        $this->assertSame(\UPLOAD_ERR_OK, $uploadedFiles['f1']->getError());

        $this->assertSame('F2', $uploadedFiles['foo']['f2']->getStream()->__toString());
        $this->assertSame('f2.txt', $uploadedFiles['foo']['f2']->getClientFilename());
        $this->assertSame('text/plain', $uploadedFiles['foo']['f2']->getClientMediaType());
        $this->assertSame(\UPLOAD_ERR_OK, $uploadedFiles['foo']['f2']->getError());

        $serverParams = $psrRequest->getServerParams();
        $this->assertSame('POST', $serverParams['REQUEST_METHOD']);
        $this->assertSame('2.8', $serverParams['HTTP_X_SYMFONY']);
        $this->assertSame('POST', $psrRequest->getMethod());
        $this->assertSame(['2.8'], $psrRequest->getHeader('X-Symfony'));
    }

    public function testGetContentCanBeCalledAfterRequestCreation()
    {
        $header = ['HTTP_HOST' => 'dunglas.fr'];
        $request = new Request([], [], [], [], [], $header, 'Content');

        $psrRequest = self::buildHttpMessageFactory()->createRequest($request);

        $this->assertSame('Content', $psrRequest->getBody()->__toString());
        $this->assertSame('Content', $request->getContent());
    }

    private function createUploadedFile(string $content, string $originalName, string $mimeType, int $error): UploadedFile
    {
        $path = $this->createTempFile();
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, $mimeType, $error, true);
    }

    /**
     * @dataProvider provideFactories
     */
    public function testCreateResponse(PsrHttpFactory $factory)
    {
        $response = new Response(
            'Response content.',
            202,
            [
                'X-Symfony' => ['3.4'],
                ' X-Broken-Header' => 'abc',
            ]
        );
        $response->headers->setCookie(new Cookie('city', 'Lille', new \DateTime('Wed, 13 Jan 2021 22:23:01 GMT'), '/', null, false, true, false, 'lax'));

        $psrResponse = $factory->createResponse($response);
        $this->assertSame('Response content.', $psrResponse->getBody()->__toString());
        $this->assertSame(202, $psrResponse->getStatusCode());
        $this->assertSame(['3.4'], $psrResponse->getHeader('x-symfony'));
        $this->assertFalse($psrResponse->hasHeader(' X-Broken-Header'));
        $this->assertFalse($psrResponse->hasHeader('X-Broken-Header'));

        $cookieHeader = $psrResponse->getHeader('Set-Cookie');
        $this->assertIsArray($cookieHeader);
        $this->assertCount(1, $cookieHeader);
        $this->assertMatchesRegularExpression('{city=Lille; expires=Wed, 13.Jan.2021 22:23:01 GMT;( max-age=\d+;)? path=/; httponly}i', $cookieHeader[0]);
    }

    public function testCreateResponseFromStreamed()
    {
        $response = new StreamedResponse(function () {
            echo "Line 1\n";
            flush();

            echo "Line 2\n";
            flush();
        });

        $psrResponse = self::buildHttpMessageFactory()->createResponse($response);

        $this->assertSame("Line 1\nLine 2\n", $psrResponse->getBody()->__toString());
    }

    public function testCreateResponseFromBinaryFile()
    {
        $path = $this->createTempFile();
        file_put_contents($path, 'Binary');

        $response = new BinaryFileResponse($path);

        $psrResponse = self::buildHttpMessageFactory()->createResponse($response);

        $this->assertSame('Binary', $psrResponse->getBody()->__toString());
    }

    public function testCreateResponseFromBinaryFileWithRange()
    {
        $path = $this->createTempFile();
        file_put_contents($path, 'Binary');

        $request = new Request();
        $request->headers->set('Range', 'bytes=1-4');

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => 'plain/text']);
        $response->prepare($request);

        $psrResponse = self::buildHttpMessageFactory()->createResponse($response);

        $this->assertSame('inar', $psrResponse->getBody()->__toString());
        $this->assertSame('bytes 1-4/6', $psrResponse->getHeaderLine('Content-Range'));
    }

    public function testUploadErrNoFile()
    {
        $file = new UploadedFile(__FILE__, '', null, \UPLOAD_ERR_NO_FILE, true);

        $request = new Request(
            [],
            [],
            [],
            [],
            [
                'f1' => $file,
                'f2' => ['name' => null, 'type' => null, 'tmp_name' => null, 'error' => \UPLOAD_ERR_NO_FILE, 'size' => 0],
            ],
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'dunglas.fr',
                'HTTP_X_SYMFONY' => '2.8',
            ],
            'Content'
        );

        $psrRequest = self::buildHttpMessageFactory()->createRequest($request);

        $uploadedFiles = $psrRequest->getUploadedFiles();

        $this->assertSame(\UPLOAD_ERR_NO_FILE, $uploadedFiles['f1']->getError());
        $this->assertSame(\UPLOAD_ERR_NO_FILE, $uploadedFiles['f2']->getError());
    }

    public function testJsonContent()
    {
        $headers = [
            'HTTP_HOST' => 'http_host.fr',
            'CONTENT_TYPE' => 'application/json',
        ];
        $request = new Request([], [], [], [], [], $headers, '{"city":"Paris","country":"France"}');
        $psrRequest = self::buildHttpMessageFactory()->createRequest($request);

        $this->assertSame(['city' => 'Paris', 'country' => 'France'], $psrRequest->getParsedBody());
    }

    public function testEmptyJsonContent()
    {
        $headers = [
            'HTTP_HOST' => 'http_host.fr',
            'CONTENT_TYPE' => 'application/json',
        ];
        $request = new Request([], [], [], [], [], $headers, '{}');
        $psrRequest = self::buildHttpMessageFactory()->createRequest($request);

        $this->assertSame([], $psrRequest->getParsedBody());
    }

    public function testWrongJsonContent()
    {
        $headers = [
            'HTTP_HOST' => 'http_host.fr',
            'CONTENT_TYPE' => 'application/json',
        ];
        $request = new Request([], [], [], [], [], $headers, '{"city":"Paris"');
        $psrRequest = self::buildHttpMessageFactory()->createRequest($request);

        $this->assertNull($psrRequest->getParsedBody());
    }

    public static function provideFactories(): \Generator
    {
        yield 'Discovery' => [new PsrHttpFactory()];
        yield 'incomplete dependencies' => [new PsrHttpFactory(responseFactory: new Psr17Factory())];
        yield 'Nyholm' => [self::buildHttpMessageFactory()];
    }

    private static function buildHttpMessageFactory(): PsrHttpFactory
    {
        $factory = new Psr17Factory();

        return new PsrHttpFactory($factory, $factory, $factory, $factory);
    }

    private function createTempFile(): string
    {
        return tempnam($this->tmpDir, 'sftest');
    }
}
