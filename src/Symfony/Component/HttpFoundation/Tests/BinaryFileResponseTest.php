<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Stream;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Tests\File\FakeFile;

class BinaryFileResponseTest extends ResponseTestCase
{
    public function testConstruction()
    {
        $file = __DIR__.'/../README.md';
        $response = new BinaryFileResponse($file, 404, ['X-Header' => 'Foo'], true, null, true, true);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Foo', $response->headers->get('X-Header'));
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('Last-Modified'));
        $this->assertFalse($response->headers->has('Content-Disposition'));

        $response = BinaryFileResponse::create($file, 404, [], true, ResponseHeaderBag::DISPOSITION_INLINE);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->headers->has('ETag'));
        $this->assertEquals('inline; filename=README.md', $response->headers->get('Content-Disposition'));
    }

    public function testConstructWithNonAsciiFilename()
    {
        touch(sys_get_temp_dir().'/fööö.html');

        $response = new BinaryFileResponse(sys_get_temp_dir().'/fööö.html', 200, [], true, 'attachment');

        @unlink(sys_get_temp_dir().'/fööö.html');

        $this->assertSame('fööö.html', $response->getFile()->getFilename());
    }

    public function testSetContent()
    {
        $this->expectException('LogicException');
        $response = new BinaryFileResponse(__FILE__);
        $response->setContent('foo');
    }

    public function testGetContent()
    {
        $response = new BinaryFileResponse(__FILE__);
        $this->assertFalse($response->getContent());
    }

    public function testSetContentDispositionGeneratesSafeFallbackFilename()
    {
        $response = new BinaryFileResponse(__FILE__);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'föö.html');

        $this->assertSame('attachment; filename=f__.html; filename*=utf-8\'\'f%C3%B6%C3%B6.html', $response->headers->get('Content-Disposition'));
    }

    public function testSetContentDispositionGeneratesSafeFallbackFilenameForWronglyEncodedFilename()
    {
        $response = new BinaryFileResponse(__FILE__);

        $iso88591EncodedFilename = utf8_decode('föö.html');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $iso88591EncodedFilename);

        // the parameter filename* is invalid in this case (rawurldecode('f%F6%F6') does not provide a UTF-8 string but an ISO-8859-1 encoded one)
        $this->assertSame('attachment; filename=f__.html; filename*=utf-8\'\'f%F6%F6.html', $response->headers->get('Content-Disposition'));
    }

    /**
     * @dataProvider provideRanges
     */
    public function testRequests($requestRange, $offset, $length, $responseRange)
    {
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // do a request to get the ETag
        $request = Request::create('/');
        $response->prepare($request);
        $etag = $response->headers->get('ETag');

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', $etag);
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__.'/File/Fixtures/test.gif', 'r');
        fseek($file, $offset);
        $data = fread($file, $length);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals($responseRange, $response->headers->get('Content-Range'));
        $this->assertSame((string) $length, $response->headers->get('Content-Length'));
    }

    /**
     * @dataProvider provideRanges
     */
    public function testRequestsWithoutEtag($requestRange, $offset, $length, $responseRange)
    {
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);

        // do a request to get the LastModified
        $request = Request::create('/');
        $response->prepare($request);
        $lastModified = $response->headers->get('Last-Modified');

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', $lastModified);
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__.'/File/Fixtures/test.gif', 'r');
        fseek($file, $offset);
        $data = fread($file, $length);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals($responseRange, $response->headers->get('Content-Range'));
    }

    public function provideRanges()
    {
        return [
            ['bytes=1-4', 1, 4, 'bytes 1-4/35'],
            ['bytes=-5', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-30', 30, 1, 'bytes 30-30/35'],
            ['bytes=30-34', 30, 5, 'bytes 30-34/35'],
        ];
    }

    public function testRangeRequestsWithoutLastModifiedDate()
    {
        // prevent auto last modified
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream'], true, null, false, false);

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('If-Range', date('D, d M Y H:i:s').' GMT');
        $request->headers->set('Range', 'bytes=1-4');

        $this->expectOutputString(file_get_contents(__DIR__.'/File/Fixtures/test.gif'));
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull($response->headers->get('Content-Range'));
    }

    /**
     * @dataProvider provideFullFileRanges
     */
    public function testFullFileRequests($requestRange)
    {
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $file = fopen(__DIR__.'/File/Fixtures/test.gif', 'r');
        $data = fread($file, 35);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function provideFullFileRanges()
    {
        return [
            ['bytes=0-'],
            ['bytes=0-34'],
            ['bytes=-35'],
            // Syntactical invalid range-request should also return the full resource
            ['bytes=20-10'],
            ['bytes=50-40'],
        ];
    }

    public function testUnpreparedResponseSendsFullFile()
    {
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200);

        $data = file_get_contents(__DIR__.'/File/Fixtures/test.gif');

        $this->expectOutputString($data);
        $response = clone $response;
        $response->sendContent();

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider provideInvalidRanges
     */
    public function testInvalidRequests($requestRange)
    {
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream'])->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(416, $response->getStatusCode());
        $this->assertEquals('bytes */35', $response->headers->get('Content-Range'));
    }

    public function provideInvalidRanges()
    {
        return [
            ['bytes=-40'],
            ['bytes=30-40'],
        ];
    }

    /**
     * @dataProvider provideXSendfileFiles
     */
    public function testXSendfile($file)
    {
        $request = Request::create('/');
        $request->headers->set('X-Sendfile-Type', 'X-Sendfile');

        BinaryFileResponse::trustXSendfileTypeHeader();
        $response = BinaryFileResponse::create($file, 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->expectOutputString('');
        $response->sendContent();

        $this->assertStringContainsString('README.md', $response->headers->get('X-Sendfile'));
    }

    public function provideXSendfileFiles()
    {
        return [
            [__DIR__.'/../README.md'],
            ['file://'.__DIR__.'/../README.md'],
        ];
    }

    /**
     * @dataProvider getSampleXAccelMappings
     */
    public function testXAccelMapping($realpath, $mapping, $virtual)
    {
        $request = Request::create('/');
        $request->headers->set('X-Sendfile-Type', 'X-Accel-Redirect');
        $request->headers->set('X-Accel-Mapping', $mapping);

        $file = new FakeFile($realpath, __DIR__.'/File/Fixtures/test');

        BinaryFileResponse::trustXSendfileTypeHeader();
        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'application/octet-stream']);
        $reflection = new \ReflectionObject($response);
        $property = $reflection->getProperty('file');
        $property->setAccessible(true);
        $property->setValue($response, $file);

        $response->prepare($request);
        $this->assertEquals($virtual, $response->headers->get('X-Accel-Redirect'));
    }

    public function testDeleteFileAfterSend()
    {
        $request = Request::create('/');

        $path = __DIR__.'/File/Fixtures/to_delete';
        touch($path);
        $realPath = realpath($path);
        $this->assertFileExists($realPath);

        $response = new BinaryFileResponse($realPath, 200, ['Content-Type' => 'application/octet-stream']);
        $response->deleteFileAfterSend(true);

        $response->prepare($request);
        $response->sendContent();

        $this->assertFileNotExists($path);
    }

    public function testAcceptRangeOnUnsafeMethods()
    {
        $request = Request::create('/', 'POST');
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->assertEquals('none', $response->headers->get('Accept-Ranges'));
    }

    public function testAcceptRangeNotOverriden()
    {
        $request = Request::create('/', 'POST');
        $response = BinaryFileResponse::create(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);
        $response->headers->set('Accept-Ranges', 'foo');
        $response->prepare($request);

        $this->assertEquals('foo', $response->headers->get('Accept-Ranges'));
    }

    public function getSampleXAccelMappings()
    {
        return [
            ['/var/www/var/www/files/foo.txt', '/var/www/=/files/', '/files/var/www/files/foo.txt'],
            ['/home/Foo/bar.txt', '/var/www/=/files/,/home/Foo/=/baz/', '/baz/bar.txt'],
            ['/home/Foo/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', '/baz/bar.txt'],
            ['/tmp/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', null],
        ];
    }

    public function testStream()
    {
        $request = Request::create('/');
        $response = new BinaryFileResponse(new Stream(__DIR__.'/../README.md'), 200, ['Content-Type' => 'text/plain']);
        $response->prepare($request);

        $this->assertNull($response->headers->get('Content-Length'));
    }

    protected function provideResponse()
    {
        return new BinaryFileResponse(__DIR__.'/../README.md', 200, ['Content-Type' => 'application/octet-stream']);
    }

    public static function tearDownAfterClass(): void
    {
        $path = __DIR__.'/../Fixtures/to_delete';
        if (file_exists($path)) {
            @unlink($path);
        }
    }
}
