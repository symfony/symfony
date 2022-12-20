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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\Tests\Fixtures\TestClient;

/**
 * @group time-sensitive
 */
class HttpKernelBrowserTest extends TestCase
{
    public function testDoRequest()
    {
        $client = new HttpKernelBrowser(new TestHttpKernel());

        $client->request('GET', '/');
        self::assertEquals('Request: /', $client->getResponse()->getContent(), '->doRequest() uses the request handler to make the request');
        self::assertInstanceOf(\Symfony\Component\BrowserKit\Request::class, $client->getInternalRequest());
        self::assertInstanceOf(Request::class, $client->getRequest());
        self::assertInstanceOf(\Symfony\Component\BrowserKit\Response::class, $client->getInternalResponse());
        self::assertInstanceOf(Response::class, $client->getResponse());

        $client->request('GET', 'http://www.example.com/');
        self::assertEquals('Request: /', $client->getResponse()->getContent(), '->doRequest() uses the request handler to make the request');
        self::assertEquals('www.example.com', $client->getRequest()->getHost(), '->doRequest() uses the request handler to make the request');

        $client->request('GET', 'http://www.example.com/?parameter=http://example.com');
        self::assertEquals('http://www.example.com/?parameter='.urlencode('http://example.com'), $client->getRequest()->getUri(), '->doRequest() uses the request handler to make the request');
    }

    public function testGetScript()
    {
        $client = new TestClient(new TestHttpKernel());
        $client->insulate();
        $client->request('GET', '/');

        self::assertEquals('Request: /', $client->getResponse()->getContent(), '->getScript() returns a script that uses the request handler to make the request');
    }

    public function testFilterResponseConvertsCookies()
    {
        $client = new HttpKernelBrowser(new TestHttpKernel());

        $r = new \ReflectionObject($client);
        $m = $r->getMethod('filterResponse');
        $m->setAccessible(true);

        $response = new Response();
        $response->headers->setCookie($cookie1 = new Cookie('foo', 'bar', \DateTime::createFromFormat('j-M-Y H:i:s T', '15-Feb-2009 20:00:00 GMT')->format('U'), '/foo', 'http://example.com', true, true, false, null));
        $domResponse = $m->invoke($client, $response);
        self::assertSame((string) $cookie1, $domResponse->getHeader('Set-Cookie'));

        $response = new Response();
        $response->headers->setCookie($cookie1 = new Cookie('foo', 'bar', \DateTime::createFromFormat('j-M-Y H:i:s T', '15-Feb-2009 20:00:00 GMT')->format('U'), '/foo', 'http://example.com', true, true, false, null));
        $response->headers->setCookie($cookie2 = new Cookie('foo1', 'bar1', \DateTime::createFromFormat('j-M-Y H:i:s T', '15-Feb-2009 20:00:00 GMT')->format('U'), '/foo', 'http://example.com', true, true, false, null));
        $domResponse = $m->invoke($client, $response);
        self::assertSame((string) $cookie1, $domResponse->getHeader('Set-Cookie'));
        self::assertSame([(string) $cookie1, (string) $cookie2], $domResponse->getHeader('Set-Cookie', false));
    }

    public function testFilterResponseSupportsStreamedResponses()
    {
        $client = new HttpKernelBrowser(new TestHttpKernel());

        $r = new \ReflectionObject($client);
        $m = $r->getMethod('filterResponse');
        $m->setAccessible(true);

        $response = new StreamedResponse(function () {
            echo 'foo';
        });

        $domResponse = $m->invoke($client, $response);
        self::assertEquals('foo', $domResponse->getContent());
    }

    public function testUploadedFile()
    {
        $source = tempnam(sys_get_temp_dir(), 'source');
        file_put_contents($source, '1');
        $target = sys_get_temp_dir().'/sf.moved.file';
        @unlink($target);

        $kernel = new TestHttpKernel();
        $client = new HttpKernelBrowser($kernel);

        $files = [
            ['tmp_name' => $source, 'name' => 'original', 'type' => 'mime/original', 'size' => null, 'error' => \UPLOAD_ERR_OK],
            new UploadedFile($source, 'original', 'mime/original', \UPLOAD_ERR_OK, true),
        ];

        $file = null;
        foreach ($files as $file) {
            $client->request('POST', '/', [], ['foo' => $file]);

            $files = $client->getRequest()->files->all();

            self::assertCount(1, $files);

            $file = $files['foo'];

            self::assertEquals('original', $file->getClientOriginalName());
            self::assertEquals('mime/original', $file->getClientMimeType());
            self::assertEquals(1, $file->getSize());
        }

        $file->move(\dirname($target), basename($target));

        self::assertFileExists($target);
        unlink($target);
    }

    public function testUploadedFileWhenNoFileSelected()
    {
        $kernel = new TestHttpKernel();
        $client = new HttpKernelBrowser($kernel);

        $file = ['tmp_name' => '', 'name' => '', 'type' => '', 'size' => 0, 'error' => \UPLOAD_ERR_NO_FILE];

        $client->request('POST', '/', [], ['foo' => $file]);

        $files = $client->getRequest()->files->all();

        self::assertCount(1, $files);
        self::assertNull($files['foo']);
    }

    public function testUploadedFileWhenSizeExceedsUploadMaxFileSize()
    {
        if (UploadedFile::getMaxFilesize() > \PHP_INT_MAX) {
            self::markTestSkipped('Requires PHP_INT_MAX to be greater than "upload_max_filesize" and "post_max_size" ini settings');
        }

        $source = tempnam(sys_get_temp_dir(), 'source');

        $kernel = new TestHttpKernel();
        $client = new HttpKernelBrowser($kernel);

        $file = self::getMockBuilder(UploadedFile::class)
            ->setConstructorArgs([$source, 'original', 'mime/original', \UPLOAD_ERR_OK, true])
            ->setMethods(['getSize', 'getClientSize'])
            ->getMock()
        ;
        /* should be modified when the getClientSize will be removed */
        $file->expects(self::any())
            ->method('getSize')
            ->willReturn(\PHP_INT_MAX)
        ;
        $file->expects(self::any())
            ->method('getClientSize')
            ->willReturn(\PHP_INT_MAX)
        ;

        $client->request('POST', '/', [], [$file]);

        $files = $client->getRequest()->files->all();

        self::assertCount(1, $files);

        $file = $files[0];

        self::assertFalse($file->isValid());
        self::assertEquals(\UPLOAD_ERR_INI_SIZE, $file->getError());
        self::assertEquals('mime/original', $file->getClientMimeType());
        self::assertEquals('original', $file->getClientOriginalName());
        self::assertEquals(0, $file->getSize());

        unlink($source);
    }

    public function testAcceptHeaderNotSet()
    {
        $client = new HttpKernelBrowser(new TestHttpKernel());

        $client->request('GET', '/');
        self::assertFalse($client->getRequest()->headers->has('Accept'));

        $client->request('GET', '/', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertSame('application/ld+json', $client->getRequest()->headers->get('Accept'));
    }
}
