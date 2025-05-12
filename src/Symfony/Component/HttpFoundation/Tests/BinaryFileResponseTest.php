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
use Symfony\Component\HttpFoundation\File\File;
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

        $response = new BinaryFileResponse($file, 404, [], true, ResponseHeaderBag::DISPOSITION_INLINE);
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
        $this->expectException(\LogicException::class);
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

        $iso88591EncodedFilename = mb_convert_encoding('föö.html', 'ISO-8859-1', 'UTF-8');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $iso88591EncodedFilename);

        // the parameter filename* is invalid in this case (rawurldecode('f%F6%F6') does not provide a UTF-8 string but an ISO-8859-1 encoded one)
        $this->assertSame('attachment; filename=f__.html; filename*=utf-8\'\'f%F6%F6.html', $response->headers->get('Content-Disposition'));
    }

    /**
     * @dataProvider provideRanges
     */
    public function testRequests($requestRange, $offset, $length, $responseRange)
    {
        $response = (new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']))->setAutoEtag();

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
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);

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

    public static function provideRanges()
    {
        return [
            ['bytes=1-4', 1, 4, 'bytes 1-4/35'],
            ['bytes=-5', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-30', 30, 1, 'bytes 30-30/35'],
            ['bytes=30-34', 30, 5, 'bytes 30-34/35'],
            ['bytes=30-40', 30, 5, 'bytes 30-34/35'],
        ];
    }

    public function testRangeRequestsWithoutLastModifiedDate()
    {
        // prevent auto last modified
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream'], true, null, false, false);

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
        $response = (new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']))->setAutoEtag();

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

    public static function provideFullFileRanges()
    {
        return [
            ['bytes=0-'],
            ['bytes=0-34'],
            ['bytes=-35'],
            // Syntactical invalid range-request should also return the full resource
            ['bytes=20-10'],
            ['bytes=50-40'],
            // range units other than bytes must be ignored
            ['unknown=10-20'],
        ];
    }

    public function testRangeOnPostMethod()
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('Range', 'bytes=10-20');
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);

        $file = fopen(__DIR__.'/File/Fixtures/test.gif', 'r');
        $data = fread($file, 35);
        fclose($file);

        $this->expectOutputString($data);
        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('35', $response->headers->get('Content-Length'));
        $this->assertNull($response->headers->get('Content-Range'));
    }

    public function testUnpreparedResponseSendsFullFile()
    {
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200);

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
        $response = (new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']))->setAutoEtag();

        // prepare a request for a range of the testing file
        $request = Request::create('/');
        $request->headers->set('Range', $requestRange);

        $response = clone $response;
        $response->prepare($request);
        $response->sendContent();

        $this->assertEquals(416, $response->getStatusCode());
        $this->assertEquals('bytes */35', $response->headers->get('Content-Range'));
    }

    public static function provideInvalidRanges()
    {
        return [
            ['bytes=-40'],
            ['bytes=40-50'],
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
        $response = new BinaryFileResponse($file, 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->expectOutputString('');
        $response->sendContent();

        $this->assertStringContainsString('README.md', $response->headers->get('X-Sendfile'));
    }

    public static function provideXSendfileFiles()
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
        $property->setValue($response, $file);

        $response->prepare($request);
        $header = $response->headers->get('X-Accel-Redirect');

        if ($virtual) {
            // Making sure the path doesn't contain characters unsupported by nginx
            $this->assertMatchesRegularExpression('/^([^?%]|%[0-9A-F]{2})*$/', $header);
            $header = rawurldecode($header);
        }

        $this->assertEquals($virtual, $header);
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

        $this->assertFileDoesNotExist($path);
    }

    public function testAcceptRangeOnUnsafeMethods()
    {
        $request = Request::create('/', 'POST');
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);
        $response->prepare($request);

        $this->assertEquals('none', $response->headers->get('Accept-Ranges'));
    }

    public function testAcceptRangeNotOverridden()
    {
        $request = Request::create('/', 'POST');
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);
        $response->headers->set('Accept-Ranges', 'foo');
        $response->prepare($request);

        $this->assertEquals('foo', $response->headers->get('Accept-Ranges'));
    }

    public static function getSampleXAccelMappings()
    {
        return [
            ['/var/www/var/www/files/foo.txt', '/var/www/=/files/', '/files/var/www/files/foo.txt'],
            ['/home/Foo/bar.txt', '/var/www/=/files/,/home/Foo/=/baz/', '/baz/bar.txt'],
            ['/home/Foo/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', '/baz/bar.txt'],
            ['/tmp/bar.txt', '"/var/www/"="/files/", "/home/Foo/"="/baz/"', null],
            ['/var/www/var/www/files/foo%.txt', '/var/www/=/files/', '/files/var/www/files/foo%.txt'],
        ];
    }

    public function testStream()
    {
        $request = Request::create('/');
        $response = new BinaryFileResponse(new Stream(__DIR__.'/../README.md'), 200, ['Content-Type' => 'text/plain']);
        $response->prepare($request);

        $this->assertNull($response->headers->get('Content-Length'));
    }

    public function testPrepareNotAddingContentTypeHeaderIfNoContentResponse()
    {
        $request = Request::create('/');
        $request->headers->set('If-Modified-Since', date('D, d M Y H:i:s').' GMT');

        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif', 200, ['Content-Type' => 'application/octet-stream']);
        $response->setLastModified(new \DateTimeImmutable('-1 day'));
        $response->isNotModified($request);

        $response->prepare($request);

        $this->assertSame(BinaryFileResponse::HTTP_NOT_MODIFIED, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Content-Type'));
    }

    public function testContentTypeIsCorrectlyDetected()
    {
        $file = new File(__DIR__.'/File/Fixtures/test.gif');

        try {
            $file->getMimeType();
        } catch (\LogicException $e) {
            $this->markTestSkipped('Guessing the mime type is not possible');
        }

        $response = new BinaryFileResponse($file);

        $request = Request::create('/');
        $response->prepare($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
    }

    public function testContentTypeIsNotGuessedWhenTheFileWasNotModified()
    {
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif');
        $response->setAutoLastModified();

        $request = Request::create('/');
        $request->headers->set('If-Modified-Since', $response->getLastModified()->format('D, d M Y H:i:s').' GMT');
        $isNotModified = $response->isNotModified($request);
        $this->assertTrue($isNotModified);
        $response->prepare($request);

        $this->assertSame(304, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Content-Type'));
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

    public function testCreateFromTemporaryFile()
    {
        $file = new \SplTempFileObject();
        $file->fwrite('foo,bar');

        $response = new BinaryFileResponse($file, 201, [
            'Content-Type' => 'text/csv',
        ]);

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertEquals('attachment; filename=temp', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->setAutoLastModified();
        $response->prepare(new Request());
        $this->assertSame('7', $response->headers->get('Content-Length'));
        $response->sendContent();
        $string = ob_get_clean();
        $this->assertSame('foo,bar', $string);
    }

    public function testSetChunkSizeTooSmall()
    {
        $response = new BinaryFileResponse(__DIR__.'/File/Fixtures/test.gif');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The chunk size of a BinaryFileResponse cannot be less than 1.');

        $response->setChunkSize(0);
    }

    public function testCreateFromTemporaryFileWithoutMimeType()
    {
        $file = new \SplTempFileObject();
        $file->fwrite('foo,bar');

        $response = new BinaryFileResponse($file);
        $response->prepare(new Request());

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }
}
