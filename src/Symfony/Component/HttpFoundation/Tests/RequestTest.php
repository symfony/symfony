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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RequestTest extends TestCase
{
    use ExpectDeprecationTrait;

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
        Request::setTrustedHosts([]);
    }

    public function testInitialize()
    {
        $request = new Request();

        $request->initialize(['foo' => 'bar']);
        self::assertEquals('bar', $request->query->get('foo'), '->initialize() takes an array of query parameters as its first argument');

        $request->initialize([], ['foo' => 'bar']);
        self::assertEquals('bar', $request->request->get('foo'), '->initialize() takes an array of request parameters as its second argument');

        $request->initialize([], [], ['foo' => 'bar']);
        self::assertEquals('bar', $request->attributes->get('foo'), '->initialize() takes an array of attributes as its third argument');

        $request->initialize([], [], [], [], [], ['HTTP_FOO' => 'bar']);
        self::assertEquals('bar', $request->headers->get('FOO'), '->initialize() takes an array of HTTP headers as its sixth argument');
    }

    public function testGetLocale()
    {
        $request = new Request();
        $request->setLocale('pl');
        $locale = $request->getLocale();
        self::assertEquals('pl', $locale);
    }

    public function testGetUser()
    {
        $request = Request::create('http://user:password@test.com');
        $user = $request->getUser();

        self::assertEquals('user', $user);
    }

    public function testGetPassword()
    {
        $request = Request::create('http://user:password@test.com');
        $password = $request->getPassword();

        self::assertEquals('password', $password);
    }

    public function testIsNoCache()
    {
        $request = new Request();
        $isNoCache = $request->isNoCache();

        self::assertFalse($isNoCache);
    }

    public function testGetContentType()
    {
        $request = new Request();
        $contentType = $request->getContentType();

        self::assertNull($contentType);
    }

    public function testSetDefaultLocale()
    {
        $request = new Request();
        $request->setDefaultLocale('pl');
        $locale = $request->getLocale();

        self::assertEquals('pl', $locale);
    }

    public function testCreate()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        self::assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('bar=baz', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo', 'GET', ['bar' => 'baz']);
        self::assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('bar=baz', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo?bar=foo', 'GET', ['bar' => 'baz']);
        self::assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('bar=baz', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('https://test.com/foo?foo.bar=baz');
        self::assertEquals('https://test.com/foo?foo.bar=baz', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('foo.bar=baz', $request->getQueryString());
        self::assertEquals(443, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertTrue($request->isSecure());
        self::assertSame(['foo_bar' => 'baz'], $request->query->all());

        $request = Request::create('test.com:90/foo');
        self::assertEquals('http://test.com:90/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com:90', $request->getHttpHost());
        self::assertEquals(90, $request->getPort());
        self::assertFalse($request->isSecure());

        $request = Request::create('https://test.com:90/foo');
        self::assertEquals('https://test.com:90/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com:90', $request->getHttpHost());
        self::assertEquals(90, $request->getPort());
        self::assertTrue($request->isSecure());

        $request = Request::create('https://127.0.0.1:90/foo');
        self::assertEquals('https://127.0.0.1:90/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('127.0.0.1', $request->getHost());
        self::assertEquals('127.0.0.1:90', $request->getHttpHost());
        self::assertEquals(90, $request->getPort());
        self::assertTrue($request->isSecure());

        $request = Request::create('https://[::1]:90/foo');
        self::assertEquals('https://[::1]:90/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('[::1]', $request->getHost());
        self::assertEquals('[::1]:90', $request->getHttpHost());
        self::assertEquals(90, $request->getPort());
        self::assertTrue($request->isSecure());

        $request = Request::create('https://[::1]/foo');
        self::assertEquals('https://[::1]/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('[::1]', $request->getHost());
        self::assertEquals('[::1]', $request->getHttpHost());
        self::assertEquals(443, $request->getPort());
        self::assertTrue($request->isSecure());

        $json = '{"jsonrpc":"2.0","method":"echo","id":7,"params":["Hello World"]}';
        $request = Request::create('http://example.com/jsonrpc', 'POST', [], [], [], [], $json);
        self::assertEquals($json, $request->getContent());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com');
        self::assertEquals('http://test.com/', $request->getUri());
        self::assertEquals('/', $request->getPathInfo());
        self::assertEquals('', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com?test=1');
        self::assertEquals('http://test.com/?test=1', $request->getUri());
        self::assertEquals('/', $request->getPathInfo());
        self::assertEquals('test=1', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com:90/?test=1');
        self::assertEquals('http://test.com:90/?test=1', $request->getUri());
        self::assertEquals('/', $request->getPathInfo());
        self::assertEquals('test=1', $request->getQueryString());
        self::assertEquals(90, $request->getPort());
        self::assertEquals('test.com:90', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://username:password@test.com');
        self::assertEquals('http://test.com/', $request->getUri());
        self::assertEquals('/', $request->getPathInfo());
        self::assertEquals('', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertEquals('username', $request->getUser());
        self::assertEquals('password', $request->getPassword());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://username@test.com');
        self::assertEquals('http://test.com/', $request->getUri());
        self::assertEquals('/', $request->getPathInfo());
        self::assertEquals('', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertEquals('username', $request->getUser());
        self::assertSame('', $request->getPassword());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com/?foo');
        self::assertEquals('/?foo', $request->getRequestUri());
        self::assertEquals(['foo' => ''], $request->query->all());

        // assume rewrite rule: (.*) --> app/app.php; app/ is a symlink to a symfony web/ directory
        $request = Request::create('http://test.com/apparthotel-1234', 'GET', [], [], [],
            [
                'DOCUMENT_ROOT' => '/var/www/www.test.com',
                'SCRIPT_FILENAME' => '/var/www/www.test.com/app/app.php',
                'SCRIPT_NAME' => '/app/app.php',
                'PHP_SELF' => '/app/app.php/apparthotel-1234',
            ]);
        self::assertEquals('http://test.com/apparthotel-1234', $request->getUri());
        self::assertEquals('/apparthotel-1234', $request->getPathInfo());
        self::assertEquals('', $request->getQueryString());
        self::assertEquals(80, $request->getPort());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertFalse($request->isSecure());

        // Fragment should not be included in the URI
        $request = Request::create('http://test.com/foo#bar');
        self::assertEquals('http://test.com/foo', $request->getUri());
    }

    public function testCreateWithRequestUri()
    {
        $request = Request::create('http://test.com:80/foo');
        $request->server->set('REQUEST_URI', 'http://test.com:80/foo');
        self::assertEquals('http://test.com/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com:8080/foo');
        $request->server->set('REQUEST_URI', 'http://test.com:8080/foo');
        self::assertEquals('http://test.com:8080/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com:8080', $request->getHttpHost());
        self::assertEquals(8080, $request->getPort());
        self::assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo?bar=foo', 'GET', ['bar' => 'baz']);
        $request->server->set('REQUEST_URI', 'http://test.com/foo?bar=foo');
        self::assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('bar=baz', $request->getQueryString());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        $request = Request::create('https://test.com:443/foo');
        $request->server->set('REQUEST_URI', 'https://test.com:443/foo');
        self::assertEquals('https://test.com/foo', $request->getUri());
        self::assertEquals('/foo', $request->getPathInfo());
        self::assertEquals('test.com', $request->getHost());
        self::assertEquals('test.com', $request->getHttpHost());
        self::assertEquals(443, $request->getPort());
        self::assertTrue($request->isSecure());

        // Fragment should not be included in the URI
        $request = Request::create('http://test.com/foo#bar');
        $request->server->set('REQUEST_URI', 'http://test.com/foo#bar');
        self::assertEquals('http://test.com/foo', $request->getUri());
    }

    /**
     * @dataProvider getRequestUriData
     */
    public function testGetRequestUri($serverRequestUri, $expected, $message)
    {
        $request = new Request();
        $request->server->add([
            'REQUEST_URI' => $serverRequestUri,

            // For having http://test.com
            'SERVER_NAME' => 'test.com',
            'SERVER_PORT' => 80,
        ]);

        self::assertSame($expected, $request->getRequestUri(), $message);
        self::assertSame($expected, $request->server->get('REQUEST_URI'), 'Normalize the request URI.');
    }

    public function getRequestUriData()
    {
        $message = 'Do not modify the path.';
        yield ['/foo', '/foo', $message];
        yield ['//bar/foo', '//bar/foo', $message];
        yield ['///bar/foo', '///bar/foo', $message];

        $message = 'Handle when the scheme, host are on REQUEST_URI.';
        yield ['http://test.com/foo?bar=baz', '/foo?bar=baz', $message];

        $message = 'Handle when the scheme, host and port are on REQUEST_URI.';
        yield ['http://test.com:80/foo', '/foo', $message];
        yield ['https://test.com:8080/foo', '/foo', $message];
        yield ['https://test.com:443/foo', '/foo', $message];

        $message = 'Fragment should not be included in the URI';
        yield ['http://test.com/foo#bar', '/foo', $message];
        yield ['/foo#bar', '/foo', $message];
    }

    public function testGetRequestUriWithoutRequiredHeader()
    {
        $expected = '';

        $request = new Request();

        $message = 'Fallback to empty URI when headers are missing.';
        self::assertSame($expected, $request->getRequestUri(), $message);
        self::assertSame($expected, $request->server->get('REQUEST_URI'), 'Normalize the request URI.');
    }

    public function testCreateCheckPrecedence()
    {
        // server is used by default
        $request = Request::create('/', 'DELETE', [], [], [], [
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
            'PHP_AUTH_USER' => 'fabien',
            'PHP_AUTH_PW' => 'pa$$',
            'QUERY_STRING' => 'foo=bar',
            'CONTENT_TYPE' => 'application/json',
        ]);
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(443, $request->getPort());
        self::assertTrue($request->isSecure());
        self::assertEquals('fabien', $request->getUser());
        self::assertEquals('pa$$', $request->getPassword());
        self::assertEquals('', $request->getQueryString());
        self::assertEquals('application/json', $request->headers->get('CONTENT_TYPE'));

        // URI has precedence over server
        $request = Request::create('http://thomas:pokemon@example.net:8080/?foo=bar', 'GET', [], [], [], [
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ]);
        self::assertEquals('example.net', $request->getHost());
        self::assertEquals(8080, $request->getPort());
        self::assertFalse($request->isSecure());
        self::assertEquals('thomas', $request->getUser());
        self::assertEquals('pokemon', $request->getPassword());
        self::assertEquals('foo=bar', $request->getQueryString());
    }

    public function testDuplicate()
    {
        $request = new Request(['foo' => 'bar'], ['foo' => 'bar'], ['foo' => 'bar'], [], [], ['HTTP_FOO' => 'bar']);
        $dup = $request->duplicate();

        self::assertEquals($request->query->all(), $dup->query->all(), '->duplicate() duplicates a request an copy the current query parameters');
        self::assertEquals($request->request->all(), $dup->request->all(), '->duplicate() duplicates a request an copy the current request parameters');
        self::assertEquals($request->attributes->all(), $dup->attributes->all(), '->duplicate() duplicates a request an copy the current attributes');
        self::assertEquals($request->headers->all(), $dup->headers->all(), '->duplicate() duplicates a request an copy the current HTTP headers');

        $dup = $request->duplicate(['foo' => 'foobar'], ['foo' => 'foobar'], ['foo' => 'foobar'], [], [], ['HTTP_FOO' => 'foobar']);

        self::assertEquals(['foo' => 'foobar'], $dup->query->all(), '->duplicate() overrides the query parameters if provided');
        self::assertEquals(['foo' => 'foobar'], $dup->request->all(), '->duplicate() overrides the request parameters if provided');
        self::assertEquals(['foo' => 'foobar'], $dup->attributes->all(), '->duplicate() overrides the attributes if provided');
        self::assertEquals(['foo' => ['foobar']], $dup->headers->all(), '->duplicate() overrides the HTTP header if provided');
    }

    public function testDuplicateWithFormat()
    {
        $request = new Request([], [], ['_format' => 'json']);
        $dup = $request->duplicate();

        self::assertEquals('json', $dup->getRequestFormat());
        self::assertEquals('json', $dup->attributes->get('_format'));

        $request = new Request();
        $request->setRequestFormat('xml');
        $dup = $request->duplicate();

        self::assertEquals('xml', $dup->getRequestFormat());
    }

    public function testGetPreferredFormat()
    {
        $request = new Request();
        self::assertNull($request->getPreferredFormat(null));
        self::assertSame('html', $request->getPreferredFormat());
        self::assertSame('json', $request->getPreferredFormat('json'));

        $request->setRequestFormat('atom');
        $request->headers->set('Accept', 'application/ld+json');
        self::assertSame('atom', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        self::assertSame('xml', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        self::assertSame('xml', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/json;q=0.8,application/xml;q=0.9');
        self::assertSame('xml', $request->getPreferredFormat());
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetFormatFromMimeType($format, $mimeTypes)
    {
        $request = new Request();
        foreach ($mimeTypes as $mime) {
            self::assertEquals($format, $request->getFormat($mime));
        }
        $request->setFormat($format, $mimeTypes);
        foreach ($mimeTypes as $mime) {
            self::assertEquals($format, $request->getFormat($mime));

            if (null !== $format) {
                self::assertEquals($mimeTypes[0], $request->getMimeType($format));
            }
        }
    }

    public function testGetFormatFromMimeTypeWithParameters()
    {
        $request = new Request();
        self::assertEquals('json', $request->getFormat('application/json; charset=utf-8'));
        self::assertEquals('json', $request->getFormat('application/json;charset=utf-8'));
        self::assertEquals('json', $request->getFormat('application/json ; charset=utf-8'));
        self::assertEquals('json', $request->getFormat('application/json ;charset=utf-8'));
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypeFromFormat($format, $mimeTypes)
    {
        $request = new Request();
        self::assertEquals($mimeTypes[0], $request->getMimeType($format));
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypesFromFormat($format, $mimeTypes)
    {
        self::assertEquals($mimeTypes, Request::getMimeTypes($format));
    }

    public function testGetMimeTypesFromInexistentFormat()
    {
        $request = new Request();
        self::assertNull($request->getMimeType('foo'));
        self::assertEquals([], Request::getMimeTypes('foo'));
    }

    public function testGetFormatWithCustomMimeType()
    {
        $request = new Request();
        $request->setFormat('custom', 'application/vnd.foo.api;myversion=2.3');
        self::assertEquals('custom', $request->getFormat('application/vnd.foo.api;myversion=2.3'));
    }

    public function getFormatToMimeTypeMapProvider()
    {
        return [
            ['txt', ['text/plain']],
            ['js', ['application/javascript', 'application/x-javascript', 'text/javascript']],
            ['css', ['text/css']],
            ['json', ['application/json', 'application/x-json']],
            ['jsonld', ['application/ld+json']],
            ['xml', ['text/xml', 'application/xml', 'application/x-xml']],
            ['rdf', ['application/rdf+xml']],
            ['atom', ['application/atom+xml']],
            ['form', ['application/x-www-form-urlencoded', 'multipart/form-data']],
        ];
    }

    public function testGetUri()
    {
        $server = [];

        // Standard Request on non default PORT
        // http://host:8080/index.php/path/info?query=string

        $server['HTTP_HOST'] = 'host:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/index.php/path/info?query=string';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PATH_INFO'] = '/path/info';
        $server['PATH_TRANSLATED'] = 'redirect:/index.php/path/info';
        $server['PHP_SELF'] = '/index_dev.php/path/info';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request = new Request();

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host:8080/index.php/path/info?query=string', $request->getUri(), '->getUri() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://servername/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://host:8080/path/info?query=string
        $server = [];
        $server['HTTP_HOST'] = 'host:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['REDIRECT_QUERY_STRING'] = 'query=string';
        $server['REDIRECT_URL'] = '/path/info';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/path/info?toto=test&1=1';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PHP_SELF'] = '/index.php';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://host:8080/path/info?query=string', $request->getUri(), '->getUri() with rewrite');

        // Use std port number
        //  http://host/path/info?query=string
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host/path/info?query=string', $request->getUri(), '->getUri() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://servername/path/info?query=string', $request->getUri(), '->getUri() with rewrite, default port without HOST_HEADER');

        // With encoded characters

        $server = [
            'HTTP_HOST' => 'host:8080',
            'SERVER_NAME' => 'servername',
            'SERVER_PORT' => '8080',
            'QUERY_STRING' => 'query=string',
            'REQUEST_URI' => '/ba%20se/index_dev.php/foo%20bar/in+fo?query=string',
            'SCRIPT_NAME' => '/ba se/index_dev.php',
            'PATH_TRANSLATED' => 'redirect:/index.php/foo bar/in+fo',
            'PHP_SELF' => '/ba se/index_dev.php/path/info',
            'SCRIPT_FILENAME' => '/some/where/ba se/index_dev.php',
        ];

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string', $request->getUri());

        // with user info

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string', $request->getUri());

        $server['PHP_AUTH_PW'] = 'symfony';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string', $request->getUri());
    }

    public function testGetUriForPath()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        self::assertEquals('http://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('http://test.com:90/foo?bar=baz');
        self::assertEquals('http://test.com:90/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com/foo?bar=baz');
        self::assertEquals('https://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com:90/foo?bar=baz');
        self::assertEquals('https://test.com:90/some/path', $request->getUriForPath('/some/path'));

        $server = [];

        // Standard Request on non default PORT
        // http://host:8080/index.php/path/info?query=string

        $server['HTTP_HOST'] = 'host:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/index.php/path/info?query=string';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PATH_INFO'] = '/path/info';
        $server['PATH_TRANSLATED'] = 'redirect:/index.php/path/info';
        $server['PHP_SELF'] = '/index_dev.php/path/info';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request = new Request();

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host:8080/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://servername/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port without HOST_HEADER');

        // Request with URL REWRITING (hide index.php)
        //   RewriteCond %{REQUEST_FILENAME} !-f
        //   RewriteRule ^(.*)$ index.php [QSA,L]
        // http://host:8080/path/info?query=string
        $server = [];
        $server['HTTP_HOST'] = 'host:8080';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '8080';

        $server['REDIRECT_QUERY_STRING'] = 'query=string';
        $server['REDIRECT_URL'] = '/path/info';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['QUERY_STRING'] = 'query=string';
        $server['REQUEST_URI'] = '/path/info?toto=test&1=1';
        $server['SCRIPT_NAME'] = '/index.php';
        $server['PHP_SELF'] = '/index.php';
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';

        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://host:8080/some/path', $request->getUriForPath('/some/path'), '->getUri() with rewrite');

        // Use std port number
        //  http://host/path/info?query=string
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://host/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite, default port without HOST_HEADER');
        self::assertEquals('servername', $request->getHttpHost());

        // with user info

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'));

        $server['PHP_AUTH_PW'] = 'symfony';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'));
    }

    /**
     * @dataProvider getRelativeUriForPathData
     */
    public function testGetRelativeUriForPath($expected, $pathinfo, $path)
    {
        self::assertEquals($expected, Request::create($pathinfo)->getRelativeUriForPath($path));
    }

    public function getRelativeUriForPathData()
    {
        return [
            ['me.png', '/foo', '/me.png'],
            ['../me.png', '/foo/bar', '/me.png'],
            ['me.png', '/foo/bar', '/foo/me.png'],
            ['../baz/me.png', '/foo/bar/b', '/foo/baz/me.png'],
            ['../../fooz/baz/me.png', '/foo/bar/b', '/fooz/baz/me.png'],
            ['baz/me.png', '/foo/bar/b', 'baz/me.png'],
        ];
    }

    public function testGetUserInfo()
    {
        $request = new Request();

        $server = ['PHP_AUTH_USER' => 'fabien'];
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('fabien', $request->getUserInfo());

        $server['PHP_AUTH_USER'] = '0';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('0', $request->getUserInfo());

        $server['PHP_AUTH_PW'] = '0';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('0:0', $request->getUserInfo());
    }

    public function testGetSchemeAndHttpHost()
    {
        $request = new Request();

        $server = [];
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '90';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_USER'] = '0';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_PW'] = '0';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('http://servername:90', $request->getSchemeAndHttpHost());
    }

    /**
     * @dataProvider getQueryStringNormalizationData
     */
    public function testGetQueryString($query, $expectedQuery, $msg)
    {
        $request = new Request();

        $request->server->set('QUERY_STRING', $query);
        self::assertSame($expectedQuery, $request->getQueryString(), $msg);
    }

    public function getQueryStringNormalizationData()
    {
        return [
            ['foo', 'foo=', 'works with valueless parameters'],
            ['foo=', 'foo=', 'includes a dangling equal sign'],
            ['bar=&foo=bar', 'bar=&foo=bar', '->works with empty parameters'],
            ['foo=bar&bar=', 'bar=&foo=bar', 'sorts keys alphabetically'],

            // GET parameters, that are submitted from an HTML form, encode spaces as "+" by default (as defined in enctype application/x-www-form-urlencoded).
            // PHP also converts "+" to spaces when filling the global _GET or when using the function parse_str.
            ['baz=Foo%20Baz&bar=Foo+Bar', 'bar=Foo%20Bar&baz=Foo%20Baz', 'normalizes spaces in both encodings "%20" and "+"'],

            ['foo[]=1&foo[]=2', 'foo%5B0%5D=1&foo%5B1%5D=2', 'allows array notation'],
            ['foo=1&foo=2', 'foo=2', 'merges repeated parameters'],
            ['pa%3Dram=foo%26bar%3Dbaz&test=test', 'pa%3Dram=foo%26bar%3Dbaz&test=test', 'works with encoded delimiters'],
            ['0', '0=', 'allows "0"'],
            ['Foo Bar&Foo%20Baz', 'Foo%20Bar=&Foo%20Baz=', 'normalizes encoding in keys'],
            ['bar=Foo Bar&baz=Foo%20Baz', 'bar=Foo%20Bar&baz=Foo%20Baz', 'normalizes encoding in values'],
            ['foo=bar&&&test&&', 'foo=bar&test=', 'removes unneeded delimiters'],
            ['formula=e=m*c^2', 'formula=e%3Dm%2Ac%5E2', 'correctly treats only the first "=" as delimiter and the next as value'],

            // Ignore pairs with empty key, even if there was a value, e.g. "=value", as such nameless values cannot be retrieved anyway.
            // PHP also does not include them when building _GET.
            ['foo=bar&=a=b&=x=y', 'foo=bar', 'removes params with empty key'],

            // Don't reorder nested query string keys
            ['foo[]=Z&foo[]=A', 'foo%5B0%5D=Z&foo%5B1%5D=A', 'keeps order of values'],
            ['foo[Z]=B&foo[A]=B', 'foo%5BZ%5D=B&foo%5BA%5D=B', 'keeps order of keys'],

            ['utf8=✓', 'utf8=%E2%9C%93', 'encodes UTF-8'],
        ];
    }

    public function testGetQueryStringReturnsNull()
    {
        $request = new Request();

        self::assertNull($request->getQueryString(), '->getQueryString() returns null for non-existent query string');

        $request->server->set('QUERY_STRING', '');
        self::assertNull($request->getQueryString(), '->getQueryString() returns null for empty query string');
    }

    public function testGetHost()
    {
        $request = new Request();

        $request->initialize(['foo' => 'bar']);
        self::assertEquals('', $request->getHost(), '->getHost() return empty string if not initialized');

        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.example.com']);
        self::assertEquals('www.example.com', $request->getHost(), '->getHost() from Host Header');

        // Host header with port number
        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.example.com:8080']);
        self::assertEquals('www.example.com', $request->getHost(), '->getHost() from Host Header with port number');

        // Server values
        $request->initialize([], [], [], [], [], ['SERVER_NAME' => 'www.example.com']);
        self::assertEquals('www.example.com', $request->getHost(), '->getHost() from server name');

        $request->initialize([], [], [], [], [], ['SERVER_NAME' => 'www.example.com', 'HTTP_HOST' => 'www.host.com']);
        self::assertEquals('www.host.com', $request->getHost(), '->getHost() value from Host header has priority over SERVER_NAME ');
    }

    public function testGetPort()
    {
        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
        $port = $request->getPort();

        self::assertEquals(80, $port, 'Without trusted proxies FORWARDED_PROTO and FORWARDED_PORT are ignored.');

        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ]);
        self::assertEquals(80, $request->getPort(), 'With PROTO and PORT on untrusted connection server value takes precedence.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertEquals(8443, $request->getPort(), 'With PROTO and PORT set PORT takes precedence.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        self::assertEquals(80, $request->getPort(), 'With only PROTO set getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertEquals(443, $request->getPort(), 'With only PROTO set getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]);
        self::assertEquals(80, $request->getPort(), 'If X_FORWARDED_PROTO is set to HTTP getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertEquals(80, $request->getPort(), 'If X_FORWARDED_PROTO is set to HTTP getPort() returns port of the original request.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'On',
        ]);
        self::assertEquals(80, $request->getPort(), 'With only PROTO set and value is On, getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertEquals(443, $request->getPort(), 'With only PROTO set and value is On, getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => '1',
        ]);
        self::assertEquals(80, $request->getPort(), 'With only PROTO set and value is 1, getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertEquals(443, $request->getPort(), 'With only PROTO set and value is 1, getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'something-else',
        ]);
        $port = $request->getPort();
        self::assertEquals(80, $port, 'With only PROTO set and value is not recognized, getPort() defaults to 80.');
    }

    public function testGetHostWithFakeHttpHostValue()
    {
        self::expectException(\RuntimeException::class);
        $request = new Request();
        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.host.com?query=string']);
        $request->getHost();
    }

    public function testGetSetMethod()
    {
        $request = new Request();

        self::assertEquals('GET', $request->getMethod(), '->getMethod() returns GET if no method is defined');

        $request->setMethod('get');
        self::assertEquals('GET', $request->getMethod(), '->getMethod() returns an uppercased string');

        $request->setMethod('PURGE');
        self::assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method even if it is not a standard one');

        $request->setMethod('POST');
        self::assertEquals('POST', $request->getMethod(), '->getMethod() returns the method POST if no _method is defined');

        $request->setMethod('POST');
        $request->request->set('_method', 'purge');
        self::assertEquals('POST', $request->getMethod(), '->getMethod() does not return the method from _method if defined and POST but support not enabled');

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_method', 'purge');

        self::assertFalse(Request::getHttpMethodParameterOverride(), 'httpMethodParameterOverride should be disabled by default');

        Request::enableHttpMethodParameterOverride();

        self::assertTrue(Request::getHttpMethodParameterOverride(), 'httpMethodParameterOverride should be enabled now but it is not');

        self::assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method from _method if defined and POST');
        $this->disableHttpMethodParameterOverride();

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', 'purge');
        self::assertEquals('POST', $request->getMethod(), '->getMethod() does not return the method from _method if defined and POST but support not enabled');

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', 'purge');
        Request::enableHttpMethodParameterOverride();
        self::assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method from _method if defined and POST');
        $this->disableHttpMethodParameterOverride();

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        self::assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override even though _method is set if defined and POST');

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        self::assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override if defined and POST');

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', ['delete', 'patch']);
        self::assertSame('POST', $request->getMethod(), '->getMethod() returns the request method if invalid type is defined in query');
    }

    /**
     * @dataProvider getClientIpsProvider
     */
    public function testGetClientIp($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor, $trustedProxies);

        self::assertEquals($expected[0], $request->getClientIp());
    }

    /**
     * @dataProvider getClientIpsProvider
     */
    public function testGetClientIps($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor, $trustedProxies);

        self::assertEquals($expected, $request->getClientIps());
    }

    /**
     * @dataProvider getClientIpsForwardedProvider
     */
    public function testGetClientIpsForwarded($expected, $remoteAddr, $httpForwarded, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpsForwardedTests($remoteAddr, $httpForwarded, $trustedProxies);

        self::assertEquals($expected, $request->getClientIps());
    }

    public function getClientIpsForwardedProvider()
    {
        //              $expected                                  $remoteAddr  $httpForwarded                                       $trustedProxies
        return [
            [['127.0.0.1'],                              '127.0.0.1', 'for="_gazonk"',                                      null],
            [['127.0.0.1'],                              '127.0.0.1', 'for="_gazonk"',                                      ['127.0.0.1']],
            [['88.88.88.88'],                            '127.0.0.1', 'for="88.88.88.88:80"',                               ['127.0.0.1']],
            [['192.0.2.60'],                             '::1',       'for=192.0.2.60;proto=http;by=203.0.113.43',          ['::1']],
            [['2620:0:1cfe:face:b00c::3', '192.0.2.43'], '::1',       'for=192.0.2.43, for="[2620:0:1cfe:face:b00c::3]"',   ['::1']],
            [['2001:db8:cafe::17'],                      '::1',       'for="[2001:db8:cafe::17]:4711',                      ['::1']],
        ];
    }

    public function getClientIpsProvider()
    {
        //        $expected                          $remoteAddr                 $httpForwardedFor            $trustedProxies
        return [
            // simple IPv4
            [['88.88.88.88'],              '88.88.88.88',              null,                        null],
            // trust the IPv4 remote addr
            [['88.88.88.88'],              '88.88.88.88',              null,                        ['88.88.88.88']],

            // simple IPv6
            [['::1'],                      '::1',                      null,                        null],
            // trust the IPv6 remote addr
            [['::1'],                      '::1',                      null,                        ['::1']],

            // forwarded for with remote IPv4 addr not trusted
            [['127.0.0.1'],                '127.0.0.1',                '88.88.88.88',               null],
            // forwarded for with remote IPv4 addr trusted + comma
            [['88.88.88.88'],              '127.0.0.1',                '88.88.88.88,',              ['127.0.0.1']],
            // forwarded for with remote IPv4 and all FF addrs trusted
            [['88.88.88.88'],              '127.0.0.1',                '88.88.88.88',               ['127.0.0.1', '88.88.88.88']],
            // forwarded for with remote IPv4 range trusted
            [['88.88.88.88'],              '123.45.67.89',             '88.88.88.88',               ['123.45.67.0/24']],

            // forwarded for with remote IPv6 addr not trusted
            [['1620:0:1cfe:face:b00c::3'], '1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3',  null],
            // forwarded for with remote IPv6 addr trusted
            [['2620:0:1cfe:face:b00c::3'], '1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3',  ['1620:0:1cfe:face:b00c::3']],
            // forwarded for with remote IPv6 range trusted
            [['88.88.88.88'],              '2a01:198:603:0:396e:4789:8e99:890f', '88.88.88.88',     ['2a01:198:603:0::/65']],

            // multiple forwarded for with remote IPv4 addr trusted
            [['88.88.88.88', '87.65.43.21', '127.0.0.1'], '123.45.67.89', '127.0.0.1, 87.65.43.21, 88.88.88.88', ['123.45.67.89']],
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted
            [['87.65.43.21', '127.0.0.1'], '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', ['123.45.67.89', '88.88.88.88']],
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted but in the middle
            [['88.88.88.88', '127.0.0.1'], '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', ['123.45.67.89', '87.65.43.21']],
            // multiple forwarded for with remote IPv4 addr and all reverse proxies trusted
            [['127.0.0.1'],                '123.45.67.89',             '127.0.0.1, 87.65.43.21, 88.88.88.88', ['123.45.67.89', '87.65.43.21', '88.88.88.88', '127.0.0.1']],

            // multiple forwarded for with remote IPv6 addr trusted
            [['2620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3'], '1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', ['1620:0:1cfe:face:b00c::3']],
            // multiple forwarded for with remote IPv6 addr and some reverse proxies trusted
            [['3620:0:1cfe:face:b00c::3'], '1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', ['1620:0:1cfe:face:b00c::3', '2620:0:1cfe:face:b00c::3']],
            // multiple forwarded for with remote IPv4 addr and some reverse proxies trusted but in the middle
            [['2620:0:1cfe:face:b00c::3', '4620:0:1cfe:face:b00c::3'], '1620:0:1cfe:face:b00c::3', '4620:0:1cfe:face:b00c::3,3620:0:1cfe:face:b00c::3,2620:0:1cfe:face:b00c::3', ['1620:0:1cfe:face:b00c::3', '3620:0:1cfe:face:b00c::3']],

            // client IP with port
            [['88.88.88.88'], '127.0.0.1', '88.88.88.88:12345, 127.0.0.1', ['127.0.0.1']],

            // invalid forwarded IP is ignored
            [['88.88.88.88'], '127.0.0.1', 'unknown,88.88.88.88', ['127.0.0.1']],
            [['88.88.88.88'], '127.0.0.1', '}__test|O:21:&quot;JDatabaseDriverMysqli&quot;:3:{s:2,88.88.88.88', ['127.0.0.1']],
        ];
    }

    /**
     * @dataProvider getClientIpsWithConflictingHeadersProvider
     */
    public function testGetClientIpsWithConflictingHeaders($httpForwarded, $httpXForwardedFor)
    {
        self::expectException(ConflictingHeadersException::class);
        $request = new Request();

        $server = [
            'REMOTE_ADDR' => '88.88.88.88',
            'HTTP_FORWARDED' => $httpForwarded,
            'HTTP_X_FORWARDED_FOR' => $httpXForwardedFor,
        ];

        Request::setTrustedProxies(['88.88.88.88'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);

        $request->initialize([], [], [], [], [], $server);

        $request->getClientIps();
    }

    /**
     * @dataProvider getClientIpsWithConflictingHeadersProvider
     */
    public function testGetClientIpsOnlyXHttpForwardedForTrusted($httpForwarded, $httpXForwardedFor)
    {
        $request = new Request();

        $server = [
            'REMOTE_ADDR' => '88.88.88.88',
            'HTTP_FORWARDED' => $httpForwarded,
            'HTTP_X_FORWARDED_FOR' => $httpXForwardedFor,
        ];

        Request::setTrustedProxies(['88.88.88.88'], Request::HEADER_X_FORWARDED_FOR);

        $request->initialize([], [], [], [], [], $server);

        self::assertSame(array_reverse(explode(',', $httpXForwardedFor)), $request->getClientIps());
    }

    public function getClientIpsWithConflictingHeadersProvider()
    {
        //        $httpForwarded                   $httpXForwardedFor
        return [
            ['for=87.65.43.21',                 '192.0.2.60'],
            ['for=87.65.43.21, for=192.0.2.60', '192.0.2.60'],
            ['for=192.0.2.60',                  '192.0.2.60,87.65.43.21'],
            ['for="::face", for=192.0.2.60',    '192.0.2.60,192.0.2.43'],
            ['for=87.65.43.21, for=192.0.2.60', '192.0.2.60,87.65.43.21'],
        ];
    }

    /**
     * @dataProvider getClientIpsWithAgreeingHeadersProvider
     */
    public function testGetClientIpsWithAgreeingHeaders($httpForwarded, $httpXForwardedFor, $expectedIps)
    {
        $request = new Request();

        $server = [
            'REMOTE_ADDR' => '88.88.88.88',
            'HTTP_FORWARDED' => $httpForwarded,
            'HTTP_X_FORWARDED_FOR' => $httpXForwardedFor,
        ];

        Request::setTrustedProxies(['88.88.88.88'], -1);

        $request->initialize([], [], [], [], [], $server);

        $clientIps = $request->getClientIps();

        self::assertSame($expectedIps, $clientIps);
    }

    public function getClientIpsWithAgreeingHeadersProvider()
    {
        //        $httpForwarded                               $httpXForwardedFor
        return [
            ['for="192.0.2.60"',                          '192.0.2.60',             ['192.0.2.60']],
            ['for=192.0.2.60, for=87.65.43.21',           '192.0.2.60,87.65.43.21', ['87.65.43.21', '192.0.2.60']],
            ['for="[::face]", for=192.0.2.60',            '::face,192.0.2.60',      ['192.0.2.60', '::face']],
            ['for="192.0.2.60:80"',                       '192.0.2.60',             ['192.0.2.60']],
            ['for=192.0.2.60;proto=http;by=203.0.113.43', '192.0.2.60',             ['192.0.2.60']],
            ['for="[2001:db8:cafe::17]:4711"',            '2001:db8:cafe::17',      ['2001:db8:cafe::17']],
        ];
    }

    public function testGetContentWorksTwiceInDefaultMode()
    {
        $req = new Request();
        self::assertEquals('', $req->getContent());
        self::assertEquals('', $req->getContent());
    }

    public function testGetContentReturnsResource()
    {
        $req = new Request();
        $retval = $req->getContent(true);
        self::assertIsResource($retval);
        self::assertEquals('', fread($retval, 1));
        self::assertTrue(feof($retval));
    }

    public function testGetContentReturnsResourceWhenContentSetInConstructor()
    {
        $req = new Request([], [], [], [], [], [], 'MyContent');
        $resource = $req->getContent(true);

        self::assertIsResource($resource);
        self::assertEquals('MyContent', stream_get_contents($resource));
    }

    public function testContentAsResource()
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'My other content');
        rewind($resource);

        $req = new Request([], [], [], [], [], [], $resource);
        self::assertEquals('My other content', stream_get_contents($req->getContent(true)));
        self::assertEquals('My other content', $req->getContent());
    }

    public function getContentCantBeCalledTwiceWithResourcesProvider()
    {
        return [
            'Resource then fetch' => [true, false],
            'Resource then resource' => [true, true],
        ];
    }

    /**
     * @dataProvider getContentCanBeCalledTwiceWithResourcesProvider
     */
    public function testGetContentCanBeCalledTwiceWithResources($first, $second)
    {
        $req = new Request();
        $a = $req->getContent($first);
        $b = $req->getContent($second);

        if ($first) {
            $a = stream_get_contents($a);
        }

        if ($second) {
            $b = stream_get_contents($b);
        }

        self::assertSame($a, $b);
    }

    public function getContentCanBeCalledTwiceWithResourcesProvider()
    {
        return [
            'Fetch then fetch' => [false, false],
            'Fetch then resource' => [false, true],
            'Resource then fetch' => [true, false],
            'Resource then resource' => [true, true],
        ];
    }

    public function provideOverloadedMethods()
    {
        return [
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            ['put'],
            ['delete'],
            ['patch'],
        ];
    }

    public function testToArrayEmpty()
    {
        $req = new Request();
        self::expectException(JsonException::class);
        self::expectExceptionMessage('Request body is empty.');
        $req->toArray();
    }

    public function testToArrayNonJson()
    {
        $req = new Request([], [], [], [], [], [], 'foobar');
        self::expectException(JsonException::class);
        self::expectExceptionMessageMatches('|Could not decode request body.+|');
        $req->toArray();
    }

    public function testToArray()
    {
        $req = new Request([], [], [], [], [], [], json_encode([]));
        self::assertEquals([], $req->toArray());
        $req = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));
        self::assertEquals(['foo' => 'bar'], $req->toArray());
    }

    /**
     * @dataProvider provideOverloadedMethods
     */
    public function testCreateFromGlobals($method)
    {
        $normalizedMethod = strtoupper($method);

        $_GET['foo1'] = 'bar1';
        $_POST['foo2'] = 'bar2';
        $_COOKIE['foo3'] = 'bar3';
        $_FILES['foo4'] = ['bar4'];
        $_SERVER['foo5'] = 'bar5';

        $request = Request::createFromGlobals();
        self::assertEquals('bar1', $request->query->get('foo1'), '::fromGlobals() uses values from $_GET');
        self::assertEquals('bar2', $request->request->get('foo2'), '::fromGlobals() uses values from $_POST');
        self::assertEquals('bar3', $request->cookies->get('foo3'), '::fromGlobals() uses values from $_COOKIE');
        self::assertEquals(['bar4'], $request->files->get('foo4'), '::fromGlobals() uses values from $_FILES');
        self::assertEquals('bar5', $request->server->get('foo5'), '::fromGlobals() uses values from $_SERVER');
        self::assertInstanceOf(InputBag::class, $request->request);
        self::assertInstanceOf(ParameterBag::class, $request->request);

        unset($_GET['foo1'], $_POST['foo2'], $_COOKIE['foo3'], $_FILES['foo4'], $_SERVER['foo5']);

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request = RequestContentProxy::createFromGlobals();
        self::assertEquals($normalizedMethod, $request->getMethod());
        self::assertEquals('mycontent', $request->request->get('content'));
        self::assertInstanceOf(InputBag::class, $request->request);
        self::assertInstanceOf(ParameterBag::class, $request->request);

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);

        Request::createFromGlobals();
        Request::enableHttpMethodParameterOverride();
        $_POST['_method'] = $method;
        $_POST['foo6'] = 'bar6';
        $_SERVER['REQUEST_METHOD'] = 'PoSt';
        $request = Request::createFromGlobals();
        self::assertEquals($normalizedMethod, $request->getMethod());
        self::assertEquals('POST', $request->getRealMethod());
        self::assertEquals('bar6', $request->request->get('foo6'));

        unset($_POST['_method'], $_POST['foo6'], $_SERVER['REQUEST_METHOD']);
        $this->disableHttpMethodParameterOverride();
    }

    public function testOverrideGlobals()
    {
        $request = new Request();
        $request->initialize(['foo' => 'bar']);

        // as the Request::overrideGlobals really work, it erase $_SERVER, so we must backup it
        $server = $_SERVER;

        $request->overrideGlobals();

        self::assertEquals(['foo' => 'bar'], $_GET);

        $request->initialize([], ['foo' => 'bar']);
        $request->overrideGlobals();

        self::assertEquals(['foo' => 'bar'], $_POST);

        self::assertArrayNotHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        $request->headers->set('X_FORWARDED_PROTO', 'https');

        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_PROTO);
        self::assertFalse($request->isSecure());
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertTrue($request->isSecure());

        $request->overrideGlobals();

        self::assertArrayHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        $request->headers->set('CONTENT_TYPE', 'multipart/form-data');
        $request->headers->set('CONTENT_LENGTH', 12345);

        $request->overrideGlobals();

        self::assertArrayHasKey('CONTENT_TYPE', $_SERVER);
        self::assertArrayHasKey('CONTENT_LENGTH', $_SERVER);

        $request->initialize(['foo' => 'bar', 'baz' => 'foo']);
        $request->query->remove('baz');

        $request->overrideGlobals();

        self::assertEquals(['foo' => 'bar'], $_GET);
        self::assertEquals('foo=bar', $_SERVER['QUERY_STRING']);
        self::assertEquals('foo=bar', $request->server->get('QUERY_STRING'));

        // restore initial $_SERVER array
        $_SERVER = $server;
    }

    public function testGetScriptName()
    {
        $request = new Request();
        self::assertEquals('', $request->getScriptName());

        $server = [];
        $server['SCRIPT_NAME'] = '/index.php';

        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/index.php', $request->getScriptName());

        $server = [];
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/frontend.php', $request->getScriptName());

        $server = [];
        $server['SCRIPT_NAME'] = '/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/index.php', $request->getScriptName());
    }

    public function testGetBasePath()
    {
        $request = new Request();
        self::assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $request->initialize([], [], [], [], [], $server);
        self::assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['SCRIPT_NAME'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['PHP_SELF'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('', $request->getBasePath());
    }

    public function testGetPathInfo()
    {
        $request = new Request();
        self::assertEquals('/', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '/path/info';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/path/info', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '/path%20test/info';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/path%20test/info', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '?a=b';
        $request->initialize([], [], [], [], [], $server);

        self::assertEquals('/', $request->getPathInfo());
    }

    public function testGetParameterPrecedence()
    {
        $request = new Request();
        $request->attributes->set('foo', 'attr');
        $request->query->set('foo', 'query');
        $request->request->set('foo', 'body');

        self::assertSame('attr', $request->get('foo'));

        $request->attributes->remove('foo');
        self::assertSame('query', $request->get('foo'));

        $request->query->remove('foo');
        self::assertSame('body', $request->get('foo'));

        $request->request->remove('foo');
        self::assertNull($request->get('foo'));
    }

    public function testGetPreferredLanguage()
    {
        $request = new Request();
        self::assertNull($request->getPreferredLanguage());
        self::assertNull($request->getPreferredLanguage([]));
        self::assertEquals('fr', $request->getPreferredLanguage(['fr']));
        self::assertEquals('fr', $request->getPreferredLanguage(['fr', 'en']));
        self::assertEquals('en', $request->getPreferredLanguage(['en', 'fr']));
        self::assertEquals('fr-ch', $request->getPreferredLanguage(['fr-ch', 'fr-fr']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        self::assertEquals('en', $request->getPreferredLanguage(['en', 'en-us']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        self::assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8');
        self::assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, fr-fr; q=0.6, fr; q=0.5');
        self::assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));
    }

    public function testIsXmlHttpRequest()
    {
        $request = new Request();
        self::assertFalse($request->isXmlHttpRequest());

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        self::assertTrue($request->isXmlHttpRequest());

        $request->headers->remove('X-Requested-With');
        self::assertFalse($request->isXmlHttpRequest());
    }

    /**
     * @requires extension intl
     */
    public function testIntlLocale()
    {
        $request = new Request();

        $request->setDefaultLocale('fr');
        self::assertEquals('fr', $request->getLocale());
        self::assertEquals('fr', \Locale::getDefault());

        $request->setLocale('en');
        self::assertEquals('en', $request->getLocale());
        self::assertEquals('en', \Locale::getDefault());

        $request->setDefaultLocale('de');
        self::assertEquals('en', $request->getLocale());
        self::assertEquals('en', \Locale::getDefault());
    }

    public function testGetCharsets()
    {
        $request = new Request();
        self::assertEquals([], $request->getCharsets());
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        self::assertEquals([], $request->getCharsets()); // testing caching

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        self::assertEquals(['ISO-8859-1', 'US-ASCII', 'UTF-8', 'ISO-10646-UCS-2'], $request->getCharsets());

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1,utf-8;q=0.7,*;q=0.7');
        self::assertEquals(['ISO-8859-1', 'utf-8', '*'], $request->getCharsets());
    }

    public function testGetEncodings()
    {
        $request = new Request();
        self::assertEquals([], $request->getEncodings());
        $request->headers->set('Accept-Encoding', 'gzip,deflate,sdch');
        self::assertEquals([], $request->getEncodings()); // testing caching

        $request = new Request();
        $request->headers->set('Accept-Encoding', 'gzip,deflate,sdch');
        self::assertEquals(['gzip', 'deflate', 'sdch'], $request->getEncodings());

        $request = new Request();
        $request->headers->set('Accept-Encoding', 'gzip;q=0.4,deflate;q=0.9,compress;q=0.7');
        self::assertEquals(['deflate', 'compress', 'gzip'], $request->getEncodings());
    }

    public function testGetAcceptableContentTypes()
    {
        $request = new Request();
        self::assertEquals([], $request->getAcceptableContentTypes());
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        self::assertEquals([], $request->getAcceptableContentTypes()); // testing caching

        $request = new Request();
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        self::assertEquals(['application/vnd.wap.wmlscriptc', 'text/vnd.wap.wml', 'application/vnd.wap.xhtml+xml', 'application/xhtml+xml', 'text/html', 'multipart/mixed', '*/*'], $request->getAcceptableContentTypes());
    }

    public function testGetLanguages()
    {
        $request = new Request();
        self::assertEquals([], $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        self::assertEquals(['zh', 'en_US', 'en'], $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.6, en; q=0.8');
        self::assertEquals(['zh', 'en', 'en_US'], $request->getLanguages()); // Test out of order qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en, en-us');
        self::assertEquals(['zh', 'en', 'en_US'], $request->getLanguages()); // Test equal weighting without qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh; q=0.6, en, en-us; q=0.6');
        self::assertEquals(['en', 'zh', 'en_US'], $request->getLanguages()); // Test equal weighting with qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, i-cherokee; q=0.6');
        self::assertEquals(['zh', 'cherokee'], $request->getLanguages());
    }

    public function testGetAcceptHeadersReturnString()
    {
        $request = new Request();
        $request->headers->set('Accept', '123');
        $request->headers->set('Accept-Charset', '123');
        $request->headers->set('Accept-Encoding', '123');
        $request->headers->set('Accept-Language', '123');

        self::assertSame(['123'], $request->getAcceptableContentTypes());
        self::assertSame(['123'], $request->getCharsets());
        self::assertSame(['123'], $request->getEncodings());
        self::assertSame(['123'], $request->getLanguages());
    }

    public function testGetRequestFormat()
    {
        $request = new Request();
        self::assertEquals('html', $request->getRequestFormat());

        // Ensure that setting different default values over time is possible,
        // aka. setRequestFormat determines the state.
        self::assertEquals('json', $request->getRequestFormat('json'));
        self::assertEquals('html', $request->getRequestFormat('html'));

        $request = new Request();
        self::assertNull($request->getRequestFormat(null));

        $request = new Request();
        self::assertNull($request->setRequestFormat('foo'));
        self::assertEquals('foo', $request->getRequestFormat(null));

        $request = new Request(['_format' => 'foo']);
        self::assertEquals('html', $request->getRequestFormat());
    }

    public function testHasSession()
    {
        $request = new Request();

        self::assertFalse($request->hasSession());
        self::assertFalse($request->hasSession(true));

        $request->setSessionFactory(function () {});
        self::assertTrue($request->hasSession());
        self::assertFalse($request->hasSession(true));

        $request->setSession(new Session(new MockArraySessionStorage()));
        self::assertTrue($request->hasSession());
        self::assertTrue($request->hasSession(true));
    }

    public function testGetSession()
    {
        $request = new Request();

        $request->setSession(new Session(new MockArraySessionStorage()));
        self::assertTrue($request->hasSession());

        $session = $request->getSession();
        self::assertObjectHasAttribute('storage', $session);
        self::assertObjectHasAttribute('flashName', $session);
        self::assertObjectHasAttribute('attributeName', $session);
    }

    public function testHasPreviousSession()
    {
        $request = new Request();

        self::assertFalse($request->hasPreviousSession());
        $request->cookies->set('MOCKSESSID', 'foo');
        self::assertFalse($request->hasPreviousSession());
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::assertTrue($request->hasPreviousSession());
    }

    public function testToString()
    {
        $request = new Request();

        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $request->cookies->set('Foo', 'Bar');

        $asString = (string) $request;

        self::assertStringContainsString('Accept-Language: zh, en-us; q=0.8, en; q=0.6', $asString);
        self::assertStringContainsString('Cookie: Foo=Bar', $asString);

        $request->cookies->set('Another', 'Cookie');

        $asString = (string) $request;

        self::assertStringContainsString('Cookie: Foo=Bar; Another=Cookie', $asString);

        $request->cookies->set('foo.bar', [1, 2]);

        $asString = (string) $request;

        self::assertStringContainsString('foo.bar%5B0%5D=1; foo.bar%5B1%5D=2', $asString);
    }

    public function testIsMethod()
    {
        $request = new Request();
        $request->setMethod('POST');
        self::assertTrue($request->isMethod('POST'));
        self::assertTrue($request->isMethod('post'));
        self::assertFalse($request->isMethod('GET'));
        self::assertFalse($request->isMethod('get'));

        $request->setMethod('GET');
        self::assertTrue($request->isMethod('GET'));
        self::assertTrue($request->isMethod('get'));
        self::assertFalse($request->isMethod('POST'));
        self::assertFalse($request->isMethod('post'));
    }

    /**
     * @dataProvider getBaseUrlData
     */
    public function testGetBaseUrl($uri, $server, $expectedBaseUrl, $expectedPathInfo)
    {
        $request = Request::create($uri, 'GET', [], [], [], $server);

        self::assertSame($expectedBaseUrl, $request->getBaseUrl(), 'baseUrl');
        self::assertSame($expectedPathInfo, $request->getPathInfo(), 'pathInfo');
    }

    public function getBaseUrlData()
    {
        return [
            [
                '/fruit/strawberry/1234index.php/blah',
                [
                    'SCRIPT_FILENAME' => 'E:/Sites/cc-new/public_html/fruit/index.php',
                    'SCRIPT_NAME' => '/fruit/index.php',
                    'PHP_SELF' => '/fruit/index.php',
                ],
                '/fruit',
                '/strawberry/1234index.php/blah',
            ],
            [
                '/fruit/strawberry/1234index.php/blah',
                [
                    'SCRIPT_FILENAME' => 'E:/Sites/cc-new/public_html/index.php',
                    'SCRIPT_NAME' => '/index.php',
                    'PHP_SELF' => '/index.php',
                ],
                '',
                '/fruit/strawberry/1234index.php/blah',
            ],
            [
                '/foo%20bar/',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME' => '/foo bar/app.php',
                    'PHP_SELF' => '/foo bar/app.php',
                ],
                '/foo%20bar',
                '/',
            ],
            [
                '/foo%20bar/home',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME' => '/foo bar/app.php',
                    'PHP_SELF' => '/foo bar/app.php',
                ],
                '/foo%20bar',
                '/home',
            ],
            [
                '/foo%20bar/app.php/home',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME' => '/foo bar/app.php',
                    'PHP_SELF' => '/foo bar/app.php',
                ],
                '/foo%20bar/app.php',
                '/home',
            ],
            [
                '/foo%20bar/app.php/home%3Dbaz',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo bar/app.php',
                    'SCRIPT_NAME' => '/foo bar/app.php',
                    'PHP_SELF' => '/foo bar/app.php',
                ],
                '/foo%20bar/app.php',
                '/home%3Dbaz',
            ],
            [
                '/foo/bar+baz',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo/app.php',
                    'SCRIPT_NAME' => '/foo/app.php',
                    'PHP_SELF' => '/foo/app.php',
                ],
                '/foo',
                '/bar+baz',
            ],
            [
                '/sub/foo/bar',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo/app.php',
                    'SCRIPT_NAME' => '/foo/app.php',
                    'PHP_SELF' => '/foo/app.php',
                ],
                '',
                '/sub/foo/bar',
            ],
            [
                '/sub/foo/app.php/bar',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo/app.php',
                    'SCRIPT_NAME' => '/foo/app.php',
                    'PHP_SELF' => '/foo/app.php',
                ],
                '/sub/foo/app.php',
                '/bar',
            ],
            [
                '/sub/foo/bar/baz',
                [
                    'SCRIPT_FILENAME' => '/home/John Doe/public_html/foo/app2.phpx',
                    'SCRIPT_NAME' => '/foo/app2.phpx',
                    'PHP_SELF' => '/foo/app2.phpx',
                ],
                '',
                '/sub/foo/bar/baz',
            ],
            [
                '/foo/api/bar',
                [
                    'SCRIPT_FILENAME' => '/var/www/api/index.php',
                    'SCRIPT_NAME' => '/api/index.php',
                    'PHP_SELF' => '/api/index.php',
                ],
                '',
                '/foo/api/bar',
            ],
        ];
    }

    /**
     * @dataProvider urlencodedStringPrefixData
     */
    public function testUrlencodedStringPrefix($string, $prefix, $expect)
    {
        $request = new Request();

        $me = new \ReflectionMethod($request, 'getUrlencodedPrefix');
        $me->setAccessible(true);

        self::assertSame($expect, $me->invoke($request, $string, $prefix));
    }

    public function urlencodedStringPrefixData()
    {
        return [
            ['foo', 'foo', 'foo'],
            ['fo%6f', 'foo', 'fo%6f'],
            ['foo/bar', 'foo', 'foo'],
            ['fo%6f/bar', 'foo', 'fo%6f'],
            ['f%6f%6f/bar', 'foo', 'f%6f%6f'],
            ['%66%6F%6F/bar', 'foo', '%66%6F%6F'],
            ['fo+o/bar', 'fo+o', 'fo+o'],
            ['fo%2Bo/bar', 'fo+o', 'fo%2Bo'],
        ];
    }

    private function disableHttpMethodParameterOverride()
    {
        $class = new \ReflectionClass(Request::class);
        $property = $class->getProperty('httpMethodParameterOverride');
        $property->setAccessible(true);
        $property->setValue(false);
    }

    private function getRequestInstanceForClientIpTests(string $remoteAddr, ?string $httpForwardedFor, ?array $trustedProxies): Request
    {
        $request = new Request();

        $server = ['REMOTE_ADDR' => $remoteAddr];
        if (null !== $httpForwardedFor) {
            $server['HTTP_X_FORWARDED_FOR'] = $httpForwardedFor;
        }

        if ($trustedProxies) {
            Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);
        }

        $request->initialize([], [], [], [], [], $server);

        return $request;
    }

    private function getRequestInstanceForClientIpsForwardedTests(string $remoteAddr, ?string $httpForwarded, ?array $trustedProxies): Request
    {
        $request = new Request();

        $server = ['REMOTE_ADDR' => $remoteAddr];

        if (null !== $httpForwarded) {
            $server['HTTP_FORWARDED'] = $httpForwarded;
        }

        if ($trustedProxies) {
            Request::setTrustedProxies($trustedProxies, Request::HEADER_FORWARDED);
        }

        $request->initialize([], [], [], [], [], $server);

        return $request;
    }

    public function testTrustedProxiesXForwardedFor()
    {
        $request = Request::create('http://example.com/');
        $request->server->set('REMOTE_ADDR', '3.3.3.3');
        $request->headers->set('X_FORWARDED_FOR', '1.1.1.1, 2.2.2.2');
        $request->headers->set('X_FORWARDED_HOST', 'foo.example.com:1234, real.example.com:8080');
        $request->headers->set('X_FORWARDED_PROTO', 'https');
        $request->headers->set('X_FORWARDED_PORT', 443);

        // no trusted proxies
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // disabling proxy trusting
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // request is forwarded by a non-trusted proxy
        Request::setTrustedProxies(['2.2.2.2'], Request::HEADER_X_FORWARDED_FOR);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        self::assertEquals('1.1.1.1', $request->getClientIp());
        self::assertEquals('foo.example.com', $request->getHost());
        self::assertEquals(443, $request->getPort());
        self::assertTrue($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.4', '2.2.2.2'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // check various X_FORWARDED_PROTO header values
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_X_FORWARDED_PROTO);
        $request->headers->set('X_FORWARDED_PROTO', 'ssl');
        self::assertTrue($request->isSecure());

        $request->headers->set('X_FORWARDED_PROTO', 'https, http');
        self::assertTrue($request->isSecure());
    }

    public function testTrustedProxiesForwarded()
    {
        $request = Request::create('http://example.com/');
        $request->server->set('REMOTE_ADDR', '3.3.3.3');
        $request->headers->set('FORWARDED', 'for=1.1.1.1, host=foo.example.com:8080, proto=https, for=2.2.2.2, host=real.example.com:8080');

        // no trusted proxies
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // disabling proxy trusting
        Request::setTrustedProxies([], Request::HEADER_FORWARDED);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // request is forwarded by a non-trusted proxy
        Request::setTrustedProxies(['2.2.2.2'], Request::HEADER_FORWARDED);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_FORWARDED);
        self::assertEquals('1.1.1.1', $request->getClientIp());
        self::assertEquals('foo.example.com', $request->getHost());
        self::assertEquals(8080, $request->getPort());
        self::assertTrue($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.4', '2.2.2.2'], Request::HEADER_FORWARDED);
        self::assertEquals('3.3.3.3', $request->getClientIp());
        self::assertEquals('example.com', $request->getHost());
        self::assertEquals(80, $request->getPort());
        self::assertFalse($request->isSecure());

        // check various X_FORWARDED_PROTO header values
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_FORWARDED);
        $request->headers->set('FORWARDED', 'proto=ssl');
        self::assertTrue($request->isSecure());

        $request->headers->set('FORWARDED', 'proto=https, proto=http');
        self::assertTrue($request->isSecure());
    }

    /**
     * @dataProvider iisRequestUriProvider
     */
    public function testIISRequestUri($headers, $server, $expectedRequestUri)
    {
        $request = new Request();
        $request->headers->replace($headers);
        $request->server->replace($server);

        self::assertEquals($expectedRequestUri, $request->getRequestUri(), '->getRequestUri() is correct');

        $subRequestUri = '/bar/foo';
        $subRequest = Request::create($subRequestUri, 'get', [], [], [], $request->server->all());
        self::assertEquals($subRequestUri, $subRequest->getRequestUri(), '->getRequestUri() is correct in sub request');
    }

    public function iisRequestUriProvider()
    {
        return [
            [
                [],
                [
                    'IIS_WasUrlRewritten' => '1',
                    'UNENCODED_URL' => '/foo/bar',
                ],
                '/foo/bar',
            ],
            [
                [],
                [
                    'ORIG_PATH_INFO' => '/foo/bar',
                ],
                '/foo/bar',
            ],
            [
                [],
                [
                    'ORIG_PATH_INFO' => '/foo/bar',
                    'QUERY_STRING' => 'foo=bar',
                ],
                '/foo/bar?foo=bar',
            ],
        ];
    }

    public function testTrustedHosts()
    {
        // create a request
        $request = Request::create('/');

        // no trusted host set -> no host check
        $request->headers->set('host', 'evil.com');
        self::assertEquals('evil.com', $request->getHost());

        // add a trusted domain and all its subdomains
        Request::setTrustedHosts(['^([a-z]{9}\.)?trusted\.com$']);

        // untrusted host
        $request->headers->set('host', 'evil.com');
        try {
            $request->getHost();
            self::fail('Request::getHost() should throw an exception when host is not trusted.');
        } catch (SuspiciousOperationException $e) {
            self::assertEquals('Untrusted Host "evil.com".', $e->getMessage());
        }

        // trusted hosts
        $request->headers->set('host', 'trusted.com');
        self::assertEquals('trusted.com', $request->getHost());
        self::assertEquals(80, $request->getPort());

        $request->server->set('HTTPS', true);
        $request->headers->set('host', 'trusted.com');
        self::assertEquals('trusted.com', $request->getHost());
        self::assertEquals(443, $request->getPort());
        $request->server->set('HTTPS', false);

        $request->headers->set('host', 'trusted.com:8000');
        self::assertEquals('trusted.com', $request->getHost());
        self::assertEquals(8000, $request->getPort());

        $request->headers->set('host', 'subdomain.trusted.com');
        self::assertEquals('subdomain.trusted.com', $request->getHost());
    }

    public function testSetTrustedHostsDoesNotBreakOnSpecialCharacters()
    {
        Request::setTrustedHosts(['localhost(\.local){0,1}#,example.com', 'localhost']);

        $request = Request::create('/');
        $request->headers->set('host', 'localhost');
        self::assertSame('localhost', $request->getHost());
    }

    public function testFactory()
    {
        Request::setFactory(function (array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null) {
            return new NewRequest();
        });

        self::assertEquals('foo', Request::create('/')->getFoo());

        Request::setFactory(null);
    }

    /**
     * @dataProvider getLongHostNames
     */
    public function testVeryLongHosts($host)
    {
        $start = microtime(true);

        $request = Request::create('/');
        $request->headers->set('host', $host);
        self::assertEquals($host, $request->getHost());
        self::assertLessThan(5, microtime(true) - $start);
    }

    /**
     * @dataProvider getHostValidities
     */
    public function testHostValidity($host, $isValid, $expectedHost = null, $expectedPort = null)
    {
        $request = Request::create('/');
        $request->headers->set('host', $host);

        if ($isValid) {
            self::assertSame($expectedHost ?: $host, $request->getHost());
            if ($expectedPort) {
                self::assertSame($expectedPort, $request->getPort());
            }
        } else {
            self::expectException(SuspiciousOperationException::class);
            self::expectExceptionMessage('Invalid Host');

            $request->getHost();
        }
    }

    public function getHostValidities()
    {
        return [
            ['.a', false],
            ['a..', false],
            ['a.', true],
            ["\xE9", false],
            ['[::1]', true],
            ['[::1]:80', true, '[::1]', 80],
            [str_repeat('.', 101), false],
        ];
    }

    public function getLongHostNames()
    {
        return [
            ['a'.str_repeat('.a', 40000)],
            [str_repeat(':', 101)],
        ];
    }

    /**
     * @dataProvider methodIdempotentProvider
     */
    public function testMethodIdempotent($method, $idempotent)
    {
        $request = new Request();
        $request->setMethod($method);
        self::assertEquals($idempotent, $request->isMethodIdempotent());
    }

    public function methodIdempotentProvider()
    {
        return [
            ['HEAD', true],
            ['GET', true],
            ['POST', false],
            ['PUT', true],
            ['PATCH', false],
            ['DELETE', true],
            ['PURGE', true],
            ['OPTIONS', true],
            ['TRACE', true],
            ['CONNECT', false],
        ];
    }

    /**
     * @dataProvider methodSafeProvider
     */
    public function testMethodSafe($method, $safe)
    {
        $request = new Request();
        $request->setMethod($method);
        self::assertEquals($safe, $request->isMethodSafe());
    }

    public function methodSafeProvider()
    {
        return [
            ['HEAD', true],
            ['GET', true],
            ['POST', false],
            ['PUT', false],
            ['PATCH', false],
            ['DELETE', false],
            ['PURGE', false],
            ['OPTIONS', true],
            ['TRACE', true],
            ['CONNECT', false],
        ];
    }

    /**
     * @dataProvider methodCacheableProvider
     */
    public function testMethodCacheable($method, $cacheable)
    {
        $request = new Request();
        $request->setMethod($method);
        self::assertEquals($cacheable, $request->isMethodCacheable());
    }

    public function methodCacheableProvider()
    {
        return [
            ['HEAD', true],
            ['GET', true],
            ['POST', false],
            ['PUT', false],
            ['PATCH', false],
            ['DELETE', false],
            ['PURGE', false],
            ['OPTIONS', false],
            ['TRACE', false],
            ['CONNECT', false],
        ];
    }

    /**
     * @dataProvider protocolVersionProvider
     */
    public function testProtocolVersion($serverProtocol, $trustedProxy, $via, $expected)
    {
        if ($trustedProxy) {
            Request::setTrustedProxies(['1.1.1.1'], -1);
        }

        $request = new Request();
        $request->server->set('SERVER_PROTOCOL', $serverProtocol);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');

        if (null !== $via) {
            $request->headers->set('Via', $via);
        }

        self::assertSame($expected, $request->getProtocolVersion());
    }

    public function protocolVersionProvider()
    {
        return [
            'untrusted with empty via' => ['HTTP/2.0', false, '', 'HTTP/2.0'],
            'untrusted without via' => ['HTTP/2.0', false, null, 'HTTP/2.0'],
            'untrusted with via' => ['HTTP/2.0', false, '1.0 fred, 1.1 nowhere.com (Apache/1.1)', 'HTTP/2.0'],
            'trusted with empty via' => ['HTTP/2.0', true, '', 'HTTP/2.0'],
            'trusted without via' => ['HTTP/2.0', true, null, 'HTTP/2.0'],
            'trusted with via' => ['HTTP/2.0', true, '1.0 fred, 1.1 nowhere.com (Apache/1.1)', 'HTTP/1.0'],
            'trusted with via and protocol name' => ['HTTP/2.0', true, 'HTTP/1.0 fred, HTTP/1.1 nowhere.com (Apache/1.1)', 'HTTP/1.0'],
            'trusted with broken via' => ['HTTP/2.0', true, 'HTTP/1^0 foo', 'HTTP/2.0'],
            'trusted with partially-broken via' => ['HTTP/2.0', true, '1.0 fred, foo', 'HTTP/1.0'],
        ];
    }

    public function nonstandardRequestsData()
    {
        return [
            ['',  '', '/', 'http://host:8080/', ''],
            ['/', '', '/', 'http://host:8080/', ''],

            ['hello/app.php/x',  '', '/x', 'http://host:8080/hello/app.php/x', '/hello', '/hello/app.php'],
            ['/hello/app.php/x', '', '/x', 'http://host:8080/hello/app.php/x', '/hello', '/hello/app.php'],

            ['',      'a=b', '/', 'http://host:8080/?a=b'],
            ['?a=b',  'a=b', '/', 'http://host:8080/?a=b'],
            ['/?a=b', 'a=b', '/', 'http://host:8080/?a=b'],

            ['x',      'a=b', '/x', 'http://host:8080/x?a=b'],
            ['x?a=b',  'a=b', '/x', 'http://host:8080/x?a=b'],
            ['/x?a=b', 'a=b', '/x', 'http://host:8080/x?a=b'],

            ['hello/x',  '', '/x', 'http://host:8080/hello/x', '/hello'],
            ['/hello/x', '', '/x', 'http://host:8080/hello/x', '/hello'],

            ['hello/app.php/x',      'a=b', '/x', 'http://host:8080/hello/app.php/x?a=b', '/hello', '/hello/app.php'],
            ['hello/app.php/x?a=b',  'a=b', '/x', 'http://host:8080/hello/app.php/x?a=b', '/hello', '/hello/app.php'],
            ['/hello/app.php/x?a=b', 'a=b', '/x', 'http://host:8080/hello/app.php/x?a=b', '/hello', '/hello/app.php'],
        ];
    }

    /**
     * @dataProvider nonstandardRequestsData
     */
    public function testNonstandardRequests($requestUri, $queryString, $expectedPathInfo, $expectedUri, $expectedBasePath = '', $expectedBaseUrl = null)
    {
        if (null === $expectedBaseUrl) {
            $expectedBaseUrl = $expectedBasePath;
        }

        $server = [
            'HTTP_HOST' => 'host:8080',
            'SERVER_PORT' => '8080',
            'QUERY_STRING' => $queryString,
            'PHP_SELF' => '/hello/app.php',
            'SCRIPT_FILENAME' => '/some/path/app.php',
            'REQUEST_URI' => $requestUri,
        ];

        $request = new Request([], [], [], [], [], $server);

        self::assertEquals($expectedPathInfo, $request->getPathInfo());
        self::assertEquals($expectedUri, $request->getUri());
        self::assertEquals($queryString, $request->getQueryString());
        self::assertEquals(8080, $request->getPort());
        self::assertEquals('host:8080', $request->getHttpHost());
        self::assertEquals($expectedBaseUrl, $request->getBaseUrl());
        self::assertEquals($expectedBasePath, $request->getBasePath());
    }

    public function testTrustedHost()
    {
        Request::setTrustedProxies(['1.1.1.1'], -1);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost:8080');
        $request->headers->set('X-Forwarded-Host', 'localhost:8080');

        self::assertSame('localhost:8080', $request->getHttpHost());
        self::assertSame(8080, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host="[::1]:443"');
        $request->headers->set('X-Forwarded-Host', '[::1]:443');
        $request->headers->set('X-Forwarded-Port', 443);

        self::assertSame('[::1]:443', $request->getHttpHost());
        self::assertSame(443, $request->getPort());
    }

    public function testTrustedPrefix()
    {
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_TRAEFIK);

        // test with index deployed under root
        $request = Request::create('/method');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('X-Forwarded-Prefix', '/myprefix');
        $request->headers->set('Forwarded', 'host=localhost:8080');

        self::assertSame('/myprefix', $request->getBaseUrl());
        self::assertSame('/myprefix', $request->getBasePath());
        self::assertSame('/method', $request->getPathInfo());
    }

    public function testTrustedPrefixWithSubdir()
    {
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_TRAEFIK);

        $server = [
            'SCRIPT_FILENAME' => '/var/hidden/app/public/public/index.php',
            'SCRIPT_NAME' => '/public/index.php',
            'PHP_SELF' => '/public/index.php',
        ];

        // test with index file deployed in subdir, i.e. local dev server (insecure!!)
        $request = Request::create('/public/method', 'GET', [], [], [], $server);
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('X-Forwarded-Prefix', '/prefix');
        $request->headers->set('Forwarded', 'host=localhost:8080');

        self::assertSame('/prefix/public', $request->getBaseUrl());
        self::assertSame('/prefix/public', $request->getBasePath());
        self::assertSame('/method', $request->getPathInfo());
    }

    public function testTrustedPrefixEmpty()
    {
        // check that there is no error, if no prefix is provided
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_TRAEFIK);
        $request = Request::create('/method');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        self::assertSame('', $request->getBaseUrl());
    }

    public function testTrustedPort()
    {
        Request::setTrustedProxies(['1.1.1.1'], -1);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost:8080');
        $request->headers->set('X-Forwarded-Port', 8080);

        self::assertSame(8080, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost');
        $request->headers->set('X-Forwarded-Port', 80);

        self::assertSame(80, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host="[::1]"');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', 443);

        self::assertSame(443, $request->getPort());
    }

    public function testTrustedPortDoesNotDefaultToZero()
    {
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('X-Forwarded-Host', 'test.example.com');
        $request->headers->set('X-Forwarded-Port', '');

        self::assertSame(80, $request->getPort());
    }

    /**
     * @dataProvider trustedProxiesRemoteAddr
     */
    public function testTrustedProxiesRemoteAddr($serverRemoteAddr, $trustedProxies, $result)
    {
        $_SERVER['REMOTE_ADDR'] = $serverRemoteAddr;
        Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);
        self::assertSame($result, Request::getTrustedProxies());
    }

    public function trustedProxiesRemoteAddr()
    {
        return [
            ['1.1.1.1', ['REMOTE_ADDR'], ['1.1.1.1']],
            ['1.1.1.1', ['REMOTE_ADDR', '2.2.2.2'], ['1.1.1.1', '2.2.2.2']],
            [null, ['REMOTE_ADDR'], []],
            [null, ['REMOTE_ADDR', '2.2.2.2'], ['2.2.2.2']],
        ];
    }

    /**
     * @dataProvider preferSafeContentData
     */
    public function testPreferSafeContent($server, bool $safePreferenceExpected)
    {
        $request = new Request([], [], [], [], [], $server);

        self::assertEquals($safePreferenceExpected, $request->preferSafeContent());
    }

    public function preferSafeContentData()
    {
        return [
            [[], false],
            [
                [
                    'HTTPS' => 'on',
                ],
                false,
            ],
            [
                [
                    'HTTPS' => 'off',
                    'HTTP_PREFER' => 'safe',
                ],
                false,
            ],
            [
                [
                    'HTTPS' => 'on',
                    'HTTP_PREFER' => 'safe',
                ],
                true,
            ],
            [
                [
                    'HTTPS' => 'on',
                    'HTTP_PREFER' => 'unknown-preference',
                ],
                false,
            ],
            [
                [
                    'HTTPS' => 'on',
                    'HTTP_PREFER' => 'unknown-preference=42, safe',
                ],
                true,
            ],
            [
                [
                    'HTTPS' => 'on',
                    'HTTP_PREFER' => 'safe, unknown-preference=42',
                ],
                true,
            ],
        ];
    }

    /**
     * @group legacy
     */
    public function testXForwarededAllConstantDeprecated()
    {
        $this->expectDeprecation('Since symfony/http-foundation 5.2: The "HEADER_X_FORWARDED_ALL" constant is deprecated, use either "HEADER_X_FORWARDED_FOR | HEADER_X_FORWARDED_HOST | HEADER_X_FORWARDED_PORT | HEADER_X_FORWARDED_PROTO" or "HEADER_X_FORWARDED_AWS_ELB" or "HEADER_X_FORWARDED_TRAEFIK" constants instead.');

        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_ALL);
    }

    public function testReservedFlags()
    {
        foreach ((new \ReflectionClass(Request::class))->getConstants() as $constant => $value) {
            self::assertNotSame(0b10000000, $value, sprintf('The constant "%s" should not use the reserved value "0b10000000".', $constant));
        }
    }
}

class RequestContentProxy extends Request
{
    public function getContent($asResource = false)
    {
        return http_build_query(['_method' => 'PUT', 'content' => 'mycontent'], '', '&');
    }
}

class NewRequest extends Request
{
    public function getFoo()
    {
        return 'foo';
    }
}
