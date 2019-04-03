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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
abstract class AbstractHttpMessageFactoryTest extends TestCase
{
    private $factory;
    private $tmpDir;

    /**
     * @return HttpMessageFactoryInterface
     */
    abstract protected function buildHttpMessageFactory();

    public function setup()
    {
        $this->factory = $this->buildHttpMessageFactory();
        $this->tmpDir = sys_get_temp_dir();
    }

    public function testCreateRequest()
    {
        $stdClass = new \stdClass();
        $request = new Request(
            array(
                'foo' => '1',
                'bar' => array('baz' => '42'),
            ),
            array(
                'twitter' => array(
                    '@dunglas' => 'Kévin Dunglas',
                    '@coopTilleuls' => 'Les-Tilleuls.coop',
                ),
                'baz' => '2',
            ),
            array(
                'a1' => $stdClass,
                'a2' => array('foo' => 'bar'),
            ),
            array(
                'c1' => 'foo',
                'c2' => array('c3' => 'bar'),
            ),
            array(
                'f1' => $this->createUploadedFile('F1', 'f1.txt', 'text/plain', UPLOAD_ERR_OK),
                'foo' => array('f2' => $this->createUploadedFile('F2', 'f2.txt', 'text/plain', UPLOAD_ERR_OK)),
            ),
            array(
                'REQUEST_METHOD' => 'POST',
                'HTTP_HOST' => 'dunglas.fr',
                'HTTP_X_SYMFONY' => '2.8',
                'REQUEST_URI' => '/testCreateRequest?foo=1&bar[baz]=42',
                'QUERY_STRING' => 'foo=1&bar[baz]=42',
            ),
            'Content'
        );

        $psrRequest = $this->factory->createRequest($request);

        $this->assertEquals('Content', $psrRequest->getBody()->__toString());

        $queryParams = $psrRequest->getQueryParams();
        $this->assertEquals('1', $queryParams['foo']);
        $this->assertEquals('42', $queryParams['bar']['baz']);

        $requestTarget = $psrRequest->getRequestTarget();
        $this->assertEquals('/testCreateRequest?foo=1&bar[baz]=42', urldecode($requestTarget));

        $parsedBody = $psrRequest->getParsedBody();
        $this->assertEquals('Kévin Dunglas', $parsedBody['twitter']['@dunglas']);
        $this->assertEquals('Les-Tilleuls.coop', $parsedBody['twitter']['@coopTilleuls']);
        $this->assertEquals('2', $parsedBody['baz']);

        $attributes = $psrRequest->getAttributes();
        $this->assertEquals($stdClass, $attributes['a1']);
        $this->assertEquals('bar', $attributes['a2']['foo']);

        $cookies = $psrRequest->getCookieParams();
        $this->assertEquals('foo', $cookies['c1']);
        $this->assertEquals('bar', $cookies['c2']['c3']);

        $uploadedFiles = $psrRequest->getUploadedFiles();
        $this->assertEquals('F1', $uploadedFiles['f1']->getStream()->__toString());
        $this->assertEquals('f1.txt', $uploadedFiles['f1']->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['f1']->getClientMediaType());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFiles['f1']->getError());

        $this->assertEquals('F2', $uploadedFiles['foo']['f2']->getStream()->__toString());
        $this->assertEquals('f2.txt', $uploadedFiles['foo']['f2']->getClientFilename());
        $this->assertEquals('text/plain', $uploadedFiles['foo']['f2']->getClientMediaType());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFiles['foo']['f2']->getError());

        $serverParams = $psrRequest->getServerParams();
        $this->assertEquals('POST', $serverParams['REQUEST_METHOD']);
        $this->assertEquals('2.8', $serverParams['HTTP_X_SYMFONY']);
        $this->assertEquals('POST', $psrRequest->getMethod());
        $this->assertEquals(array('2.8'), $psrRequest->getHeader('X-Symfony'));
    }

    public function testGetContentCanBeCalledAfterRequestCreation()
    {
        $header = array('HTTP_HOST' => 'dunglas.fr');
        $request = new Request(array(), array(), array(), array(), array(), $header, 'Content');

        $psrRequest = $this->factory->createRequest($request);

        $this->assertEquals('Content', $psrRequest->getBody()->__toString());
        $this->assertEquals('Content', $request->getContent());
    }

    private function createUploadedFile($content, $originalName, $mimeType, $error)
    {
        $path = tempnam($this->tmpDir, uniqid());
        file_put_contents($path, $content);

        if (class_exists('Symfony\Component\HttpFoundation\HeaderUtils')) {
            // Symfony 4.1+
            return new UploadedFile($path, $originalName, $mimeType, $error, true);
        }
        return new UploadedFile($path, $originalName, $mimeType, filesize($path), $error, true);
    }

    public function testCreateResponse()
    {
        $response = new Response(
            'Response content.',
            202,
            array('X-Symfony' => array('3.4'))
        );
        $response->headers->setCookie(new Cookie('city', 'Lille', new \DateTime('Wed, 13 Jan 2021 22:23:01 GMT'), '/', null, false, true, false, 'lax'));

        $psrResponse = $this->factory->createResponse($response);
        $this->assertEquals('Response content.', $psrResponse->getBody()->__toString());
        $this->assertEquals(202, $psrResponse->getStatusCode());
        $this->assertEquals(array('3.4'), $psrResponse->getHeader('X-Symfony'));

        $cookieHeader = $psrResponse->getHeader('Set-Cookie');
        $this->assertInternalType('array', $cookieHeader);
        $this->assertCount(1, $cookieHeader);
        $this->assertRegExp('{city=Lille; expires=Wed, 13-Jan-2021 22:23:01 GMT;( max-age=\d+;)? path=/; httponly}i', $cookieHeader[0]);
    }

    public function testCreateResponseFromStreamed()
    {
        $response = new StreamedResponse(function () {
            echo "Line 1\n";
            flush();

            echo "Line 2\n";
            flush();
        });

        $psrResponse = $this->factory->createResponse($response);

        $this->assertEquals("Line 1\nLine 2\n", $psrResponse->getBody()->__toString());
    }

    public function testCreateResponseFromBinaryFile()
    {
        $path = tempnam($this->tmpDir, uniqid());
        file_put_contents($path, 'Binary');

        $response = new BinaryFileResponse($path);

        $psrResponse = $this->factory->createResponse($response);

        $this->assertEquals('Binary', $psrResponse->getBody()->__toString());
    }

    public function testUploadErrNoFile()
    {
        if (class_exists('Symfony\Component\HttpFoundation\HeaderUtils')) {
            // Symfony 4.1+
            $file = new UploadedFile('', '', null, UPLOAD_ERR_NO_FILE, true);
        } else {
            $file = new UploadedFile('', '', null, 0, UPLOAD_ERR_NO_FILE, true);
        }
        $this->assertEquals(0, $file->getSize());
        $this->assertEquals(UPLOAD_ERR_NO_FILE, $file->getError());
        $this->assertFalse($file->getSize(), 'SplFile::getSize() returns false on error');

        $request = new Request(array(), array(), array(), array(),
          array(
            'f1' => $file,
            'f2' => array('name' => null, 'type' => null, 'tmp_name' => null, 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0),
          ),
          array(
            'REQUEST_METHOD' => 'POST',
            'HTTP_HOST' => 'dunglas.fr',
            'HTTP_X_SYMFONY' => '2.8',
          ),
          'Content'
        );

        $psrRequest = $this->factory->createRequest($request);

        $uploadedFiles = $psrRequest->getUploadedFiles();

        $this->assertEquals(UPLOAD_ERR_NO_FILE, $uploadedFiles['f1']->getError());
        $this->assertEquals(UPLOAD_ERR_NO_FILE, $uploadedFiles['f2']->getError());
    }
}
