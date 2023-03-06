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
        $this->assertEquals('bar', $request->query->get('foo'), '->initialize() takes an array of query parameters as its first argument');

        $request->initialize([], ['foo' => 'bar']);
        $this->assertEquals('bar', $request->request->get('foo'), '->initialize() takes an array of request parameters as its second argument');

        $request->initialize([], [], ['foo' => 'bar']);
        $this->assertEquals('bar', $request->attributes->get('foo'), '->initialize() takes an array of attributes as its third argument');

        $request->initialize([], [], [], [], [], ['HTTP_FOO' => 'bar']);
        $this->assertEquals('bar', $request->headers->get('FOO'), '->initialize() takes an array of HTTP headers as its sixth argument');
    }

    public function testGetLocale()
    {
        $request = new Request();
        $request->setLocale('pl');
        $locale = $request->getLocale();
        $this->assertEquals('pl', $locale);
    }

    public function testGetUser()
    {
        $request = Request::create('http://user:password@test.com');
        $user = $request->getUser();

        $this->assertEquals('user', $user);
    }

    public function testGetPassword()
    {
        $request = Request::create('http://user:password@test.com');
        $password = $request->getPassword();

        $this->assertEquals('password', $password);
    }

    public function testIsNoCache()
    {
        $request = new Request();
        $isNoCache = $request->isNoCache();

        $this->assertFalse($isNoCache);
    }

    /**
     * @group legacy
     */
    public function testGetContentType()
    {
        $this->expectDeprecation('Since symfony/http-foundation 6.2: The "Symfony\Component\HttpFoundation\Request::getContentType()" method is deprecated, use "getContentTypeFormat()" instead.');
        $request = new Request();

        $contentType = $request->getContentType();

        $this->assertNull($contentType);
    }

    public function testGetContentTypeFormat()
    {
        $request = new Request();
        $this->assertNull($request->getContentTypeFormat());

        $server = ['HTTP_CONTENT_TYPE' => 'application/json'];
        $request = new Request([], [], [], [], [], $server);
        $this->assertEquals('json', $request->getContentTypeFormat());

        $server = ['HTTP_CONTENT_TYPE' => 'text/html'];
        $request = new Request([], [], [], [], [], $server);
        $this->assertEquals('html', $request->getContentTypeFormat());
    }

    public function testSetDefaultLocale()
    {
        $request = new Request();
        $request->setDefaultLocale('pl');
        $locale = $request->getLocale();

        $this->assertEquals('pl', $locale);
    }

    public function testCreate()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo', 'GET', ['bar' => 'baz']);
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo?bar=foo', 'GET', ['bar' => 'baz']);
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('https://test.com/foo?foo.bar=baz');
        $this->assertEquals('https://test.com/foo?foo.bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('foo.bar=baz', $request->getQueryString());
        $this->assertEquals(443, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertTrue($request->isSecure());
        $this->assertSame(['foo_bar' => 'baz'], $request->query->all());

        $request = Request::create('test.com:90/foo');
        $this->assertEquals('http://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertFalse($request->isSecure());

        $request = Request::create('https://test.com:90/foo');
        $this->assertEquals('https://test.com:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $request = Request::create('https://127.0.0.1:90/foo');
        $this->assertEquals('https://127.0.0.1:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('127.0.0.1', $request->getHost());
        $this->assertEquals('127.0.0.1:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $request = Request::create('https://[::1]:90/foo');
        $this->assertEquals('https://[::1]:90/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('[::1]', $request->getHost());
        $this->assertEquals('[::1]:90', $request->getHttpHost());
        $this->assertEquals(90, $request->getPort());
        $this->assertTrue($request->isSecure());

        $request = Request::create('https://[::1]/foo');
        $this->assertEquals('https://[::1]/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('[::1]', $request->getHost());
        $this->assertEquals('[::1]', $request->getHttpHost());
        $this->assertEquals(443, $request->getPort());
        $this->assertTrue($request->isSecure());

        $json = '{"jsonrpc":"2.0","method":"echo","id":7,"params":["Hello World"]}';
        $request = Request::create('http://example.com/jsonrpc', 'POST', [], [], [], [], $json);
        $this->assertEquals($json, $request->getContent());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com');
        $this->assertEquals('http://test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com?test=1');
        $this->assertEquals('http://test.com/?test=1', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('test=1', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com:90/?test=1');
        $this->assertEquals('http://test.com:90/?test=1', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('test=1', $request->getQueryString());
        $this->assertEquals(90, $request->getPort());
        $this->assertEquals('test.com:90', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://username:password@test.com');
        $this->assertEquals('http://test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals('username', $request->getUser());
        $this->assertEquals('password', $request->getPassword());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://username@test.com');
        $this->assertEquals('http://test.com/', $request->getUri());
        $this->assertEquals('/', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals('username', $request->getUser());
        $this->assertSame('', $request->getPassword());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/?foo');
        $this->assertEquals('/?foo', $request->getRequestUri());
        $this->assertEquals(['foo' => ''], $request->query->all());

        // assume rewrite rule: (.*) --> app/app.php; app/ is a symlink to a symfony web/ directory
        $request = Request::create('http://test.com/apparthotel-1234', 'GET', [], [], [],
            [
                'DOCUMENT_ROOT' => '/var/www/www.test.com',
                'SCRIPT_FILENAME' => '/var/www/www.test.com/app/app.php',
                'SCRIPT_NAME' => '/app/app.php',
                'PHP_SELF' => '/app/app.php/apparthotel-1234',
            ]);
        $this->assertEquals('http://test.com/apparthotel-1234', $request->getUri());
        $this->assertEquals('/apparthotel-1234', $request->getPathInfo());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals(80, $request->getPort());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertFalse($request->isSecure());

        // Fragment should not be included in the URI
        $request = Request::create('http://test.com/foo#bar');
        $this->assertEquals('http://test.com/foo', $request->getUri());
    }

    public function testCreateWithRequestUri()
    {
        $request = Request::create('http://test.com:80/foo');
        $request->server->set('REQUEST_URI', 'http://test.com:80/foo');
        $this->assertEquals('http://test.com/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com:8080/foo');
        $request->server->set('REQUEST_URI', 'http://test.com:8080/foo');
        $this->assertEquals('http://test.com:8080/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com:8080', $request->getHttpHost());
        $this->assertEquals(8080, $request->getPort());
        $this->assertFalse($request->isSecure());

        $request = Request::create('http://test.com/foo?bar=foo', 'GET', ['bar' => 'baz']);
        $request->server->set('REQUEST_URI', 'http://test.com/foo?bar=foo');
        $this->assertEquals('http://test.com/foo?bar=baz', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('bar=baz', $request->getQueryString());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        $request = Request::create('https://test.com:443/foo');
        $request->server->set('REQUEST_URI', 'https://test.com:443/foo');
        $this->assertEquals('https://test.com/foo', $request->getUri());
        $this->assertEquals('/foo', $request->getPathInfo());
        $this->assertEquals('test.com', $request->getHost());
        $this->assertEquals('test.com', $request->getHttpHost());
        $this->assertEquals(443, $request->getPort());
        $this->assertTrue($request->isSecure());

        // Fragment should not be included in the URI
        $request = Request::create('http://test.com/foo#bar');
        $request->server->set('REQUEST_URI', 'http://test.com/foo#bar');
        $this->assertEquals('http://test.com/foo', $request->getUri());
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

        $this->assertSame($expected, $request->getRequestUri(), $message);
        $this->assertSame($expected, $request->server->get('REQUEST_URI'), 'Normalize the request URI.');
    }

    public static function getRequestUriData()
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
        $this->assertSame($expected, $request->getRequestUri(), $message);
        $this->assertSame($expected, $request->server->get('REQUEST_URI'), 'Normalize the request URI.');
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
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(443, $request->getPort());
        $this->assertTrue($request->isSecure());
        $this->assertEquals('fabien', $request->getUser());
        $this->assertEquals('pa$$', $request->getPassword());
        $this->assertEquals('', $request->getQueryString());
        $this->assertEquals('application/json', $request->headers->get('CONTENT_TYPE'));

        // URI has precedence over server
        $request = Request::create('http://thomas:pokemon@example.net:8080/?foo=bar', 'GET', [], [], [], [
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
        ]);
        $this->assertEquals('example.net', $request->getHost());
        $this->assertEquals(8080, $request->getPort());
        $this->assertFalse($request->isSecure());
        $this->assertEquals('thomas', $request->getUser());
        $this->assertEquals('pokemon', $request->getPassword());
        $this->assertEquals('foo=bar', $request->getQueryString());
    }

    public function testDuplicate()
    {
        $request = new Request(['foo' => 'bar'], ['foo' => 'bar'], ['foo' => 'bar'], [], [], ['HTTP_FOO' => 'bar']);
        $dup = $request->duplicate();

        $this->assertEquals($request->query->all(), $dup->query->all(), '->duplicate() duplicates a request an copy the current query parameters');
        $this->assertEquals($request->request->all(), $dup->request->all(), '->duplicate() duplicates a request an copy the current request parameters');
        $this->assertEquals($request->attributes->all(), $dup->attributes->all(), '->duplicate() duplicates a request an copy the current attributes');
        $this->assertEquals($request->headers->all(), $dup->headers->all(), '->duplicate() duplicates a request an copy the current HTTP headers');

        $dup = $request->duplicate(['foo' => 'foobar'], ['foo' => 'foobar'], ['foo' => 'foobar'], [], [], ['HTTP_FOO' => 'foobar']);

        $this->assertEquals(['foo' => 'foobar'], $dup->query->all(), '->duplicate() overrides the query parameters if provided');
        $this->assertEquals(['foo' => 'foobar'], $dup->request->all(), '->duplicate() overrides the request parameters if provided');
        $this->assertEquals(['foo' => 'foobar'], $dup->attributes->all(), '->duplicate() overrides the attributes if provided');
        $this->assertEquals(['foo' => ['foobar']], $dup->headers->all(), '->duplicate() overrides the HTTP header if provided');
    }

    public function testDuplicateWithFormat()
    {
        $request = new Request([], [], ['_format' => 'json']);
        $dup = $request->duplicate();

        $this->assertEquals('json', $dup->getRequestFormat());
        $this->assertEquals('json', $dup->attributes->get('_format'));

        $request = new Request();
        $request->setRequestFormat('xml');
        $dup = $request->duplicate();

        $this->assertEquals('xml', $dup->getRequestFormat());
    }

    public function testGetPreferredFormat()
    {
        $request = new Request();
        $this->assertNull($request->getPreferredFormat(null));
        $this->assertSame('html', $request->getPreferredFormat());
        $this->assertSame('json', $request->getPreferredFormat('json'));

        $request->setRequestFormat('atom');
        $request->headers->set('Accept', 'application/ld+json');
        $this->assertSame('atom', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        $this->assertSame('xml', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/xml');
        $this->assertSame('xml', $request->getPreferredFormat());

        $request = new Request();
        $request->headers->set('Accept', 'application/json;q=0.8,application/xml;q=0.9');
        $this->assertSame('xml', $request->getPreferredFormat());
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetFormatFromMimeType($format, $mimeTypes)
    {
        $request = new Request();
        foreach ($mimeTypes as $mime) {
            $this->assertEquals($format, $request->getFormat($mime));
        }
        $request->setFormat($format, $mimeTypes);
        foreach ($mimeTypes as $mime) {
            $this->assertEquals($format, $request->getFormat($mime));

            if (null !== $format) {
                $this->assertEquals($mimeTypes[0], $request->getMimeType($format));
            }
        }
    }

    public function testGetFormatFromMimeTypeWithParameters()
    {
        $request = new Request();
        $this->assertEquals('json', $request->getFormat('application/json; charset=utf-8'));
        $this->assertEquals('json', $request->getFormat('application/json;charset=utf-8'));
        $this->assertEquals('json', $request->getFormat('application/json ; charset=utf-8'));
        $this->assertEquals('json', $request->getFormat('application/json ;charset=utf-8'));
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypeFromFormat($format, $mimeTypes)
    {
        $request = new Request();
        $this->assertEquals($mimeTypes[0], $request->getMimeType($format));
    }

    /**
     * @dataProvider getFormatToMimeTypeMapProvider
     */
    public function testGetMimeTypesFromFormat($format, $mimeTypes)
    {
        $this->assertEquals($mimeTypes, Request::getMimeTypes($format));
    }

    public function testGetMimeTypesFromInexistentFormat()
    {
        $request = new Request();
        $this->assertNull($request->getMimeType('foo'));
        $this->assertEquals([], Request::getMimeTypes('foo'));
    }

    public function testGetFormatWithCustomMimeType()
    {
        $request = new Request();
        $request->setFormat('custom', 'application/vnd.foo.api;myversion=2.3');
        $this->assertEquals('custom', $request->getFormat('application/vnd.foo.api;myversion=2.3'));
    }

    public static function getFormatToMimeTypeMapProvider()
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

        $this->assertEquals('http://host:8080/index.php/path/info?query=string', $request->getUri(), '->getUri() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://host/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://servername/index.php/path/info?query=string', $request->getUri(), '->getUri() with default port without HOST_HEADER');

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
        $this->assertEquals('http://host:8080/path/info?query=string', $request->getUri(), '->getUri() with rewrite');

        // Use std port number
        //  http://host/path/info?query=string
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://host/path/info?query=string', $request->getUri(), '->getUri() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://servername/path/info?query=string', $request->getUri(), '->getUri() with rewrite, default port without HOST_HEADER');

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

        $this->assertEquals(
            'http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string',
            $request->getUri()
        );

        // with user info

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string', $request->getUri());

        $server['PHP_AUTH_PW'] = 'symfony';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://host:8080/ba%20se/index_dev.php/foo%20bar/in+fo?query=string', $request->getUri());
    }

    public function testGetUriForPath()
    {
        $request = Request::create('http://test.com/foo?bar=baz');
        $this->assertEquals('http://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('http://test.com:90/foo?bar=baz');
        $this->assertEquals('http://test.com:90/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com/foo?bar=baz');
        $this->assertEquals('https://test.com/some/path', $request->getUriForPath('/some/path'));

        $request = Request::create('https://test.com:90/foo?bar=baz');
        $this->assertEquals('https://test.com:90/some/path', $request->getUriForPath('/some/path'));

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

        $this->assertEquals('http://host:8080/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with non default port');

        // Use std port number
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://host/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://servername/index.php/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with default port without HOST_HEADER');

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
        $this->assertEquals('http://host:8080/some/path', $request->getUriForPath('/some/path'), '->getUri() with rewrite');

        // Use std port number
        //  http://host/path/info?query=string
        $server['HTTP_HOST'] = 'host';
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://host/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite and default port');

        // Without HOST HEADER
        unset($server['HTTP_HOST']);
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '80';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'), '->getUriForPath() with rewrite, default port without HOST_HEADER');
        $this->assertEquals('servername', $request->getHttpHost());

        // with user info

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'));

        $server['PHP_AUTH_PW'] = 'symfony';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername/some/path', $request->getUriForPath('/some/path'));
    }

    /**
     * @dataProvider getRelativeUriForPathData
     */
    public function testGetRelativeUriForPath($expected, $pathinfo, $path)
    {
        $this->assertEquals($expected, Request::create($pathinfo)->getRelativeUriForPath($path));
    }

    public static function getRelativeUriForPathData()
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
        $this->assertEquals('fabien', $request->getUserInfo());

        $server['PHP_AUTH_USER'] = '0';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('0', $request->getUserInfo());

        $server['PHP_AUTH_PW'] = '0';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('0:0', $request->getUserInfo());
    }

    public function testGetSchemeAndHttpHost()
    {
        $request = new Request();

        $server = [];
        $server['SERVER_NAME'] = 'servername';
        $server['SERVER_PORT'] = '90';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_USER'] = 'fabien';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_USER'] = '0';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername:90', $request->getSchemeAndHttpHost());

        $server['PHP_AUTH_PW'] = '0';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('http://servername:90', $request->getSchemeAndHttpHost());
    }

    /**
     * @dataProvider getQueryStringNormalizationData
     */
    public function testGetQueryString($query, $expectedQuery, $msg)
    {
        $request = new Request();

        $request->server->set('QUERY_STRING', $query);
        $this->assertSame($expectedQuery, $request->getQueryString(), $msg);
    }

    public static function getQueryStringNormalizationData()
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

        $this->assertNull($request->getQueryString(), '->getQueryString() returns null for non-existent query string');

        $request->server->set('QUERY_STRING', '');
        $this->assertNull($request->getQueryString(), '->getQueryString() returns null for empty query string');
    }

    public function testGetHost()
    {
        $request = new Request();

        $request->initialize(['foo' => 'bar']);
        $this->assertEquals('', $request->getHost(), '->getHost() return empty string if not initialized');

        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.example.com']);
        $this->assertEquals('www.example.com', $request->getHost(), '->getHost() from Host Header');

        // Host header with port number
        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.example.com:8080']);
        $this->assertEquals('www.example.com', $request->getHost(), '->getHost() from Host Header with port number');

        // Server values
        $request->initialize([], [], [], [], [], ['SERVER_NAME' => 'www.example.com']);
        $this->assertEquals('www.example.com', $request->getHost(), '->getHost() from server name');

        $request->initialize([], [], [], [], [], ['SERVER_NAME' => 'www.example.com', 'HTTP_HOST' => 'www.host.com']);
        $this->assertEquals('www.host.com', $request->getHost(), '->getHost() value from Host header has priority over SERVER_NAME ');
    }

    public function testGetPort()
    {
        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);
        $port = $request->getPort();

        $this->assertEquals(80, $port, 'Without trusted proxies FORWARDED_PROTO and FORWARDED_PORT are ignored.');

        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '8443',
        ]);
        $this->assertEquals(80, $request->getPort(), 'With PROTO and PORT on untrusted connection server value takes precedence.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertEquals(8443, $request->getPort(), 'With PROTO and PORT set PORT takes precedence.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $this->assertEquals(80, $request->getPort(), 'With only PROTO set getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertEquals(443, $request->getPort(), 'With only PROTO set getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'http',
        ]);
        $this->assertEquals(80, $request->getPort(), 'If X_FORWARDED_PROTO is set to HTTP getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertEquals(80, $request->getPort(), 'If X_FORWARDED_PROTO is set to HTTP getPort() returns port of the original request.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'On',
        ]);
        $this->assertEquals(80, $request->getPort(), 'With only PROTO set and value is On, getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertEquals(443, $request->getPort(), 'With only PROTO set and value is On, getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => '1',
        ]);
        $this->assertEquals(80, $request->getPort(), 'With only PROTO set and value is 1, getPort() ignores trusted headers on untrusted connection.');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertEquals(443, $request->getPort(), 'With only PROTO set and value is 1, getPort() defaults to 443.');

        $request = Request::create('http://example.com', 'GET', [], [], [], [
            'HTTP_X_FORWARDED_PROTO' => 'something-else',
        ]);
        $port = $request->getPort();
        $this->assertEquals(80, $port, 'With only PROTO set and value is not recognized, getPort() defaults to 80.');
    }

    public function testGetHostWithFakeHttpHostValue()
    {
        $this->expectException(\RuntimeException::class);
        $request = new Request();
        $request->initialize([], [], [], [], [], ['HTTP_HOST' => 'www.host.com?query=string']);
        $request->getHost();
    }

    public function testGetSetMethod()
    {
        $request = new Request();

        $this->assertEquals('GET', $request->getMethod(), '->getMethod() returns GET if no method is defined');

        $request->setMethod('get');
        $this->assertEquals('GET', $request->getMethod(), '->getMethod() returns an uppercased string');

        $request->setMethod('PURGE');
        $this->assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method even if it is not a standard one');

        $request->setMethod('POST');
        $this->assertEquals('POST', $request->getMethod(), '->getMethod() returns the method POST if no _method is defined');

        $request->setMethod('POST');
        $request->request->set('_method', 'purge');
        $this->assertEquals('POST', $request->getMethod(), '->getMethod() does not return the method from _method if defined and POST but support not enabled');

        $request = new Request();
        $request->setMethod('POST');
        $request->request->set('_method', 'purge');

        $this->assertFalse(Request::getHttpMethodParameterOverride(), 'httpMethodParameterOverride should be disabled by default');

        Request::enableHttpMethodParameterOverride();

        $this->assertTrue(Request::getHttpMethodParameterOverride(), 'httpMethodParameterOverride should be enabled now but it is not');

        $this->assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method from _method if defined and POST');
        $this->disableHttpMethodParameterOverride();

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', 'purge');
        $this->assertEquals('POST', $request->getMethod(), '->getMethod() does not return the method from _method if defined and POST but support not enabled');

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', 'purge');
        Request::enableHttpMethodParameterOverride();
        $this->assertEquals('PURGE', $request->getMethod(), '->getMethod() returns the method from _method if defined and POST');
        $this->disableHttpMethodParameterOverride();

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        $this->assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override even though _method is set if defined and POST');

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('X-HTTP-METHOD-OVERRIDE', 'delete');
        $this->assertEquals('DELETE', $request->getMethod(), '->getMethod() returns the method from X-HTTP-Method-Override if defined and POST');

        $request = new Request();
        $request->setMethod('POST');
        $request->query->set('_method', ['delete', 'patch']);
        $this->assertSame('POST', $request->getMethod(), '->getMethod() returns the request method if invalid type is defined in query');
    }

    /**
     * @dataProvider getClientIpsProvider
     */
    public function testGetClientIp($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor, $trustedProxies);

        $this->assertEquals($expected[0], $request->getClientIp());
    }

    /**
     * @dataProvider getClientIpsProvider
     */
    public function testGetClientIps($expected, $remoteAddr, $httpForwardedFor, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpTests($remoteAddr, $httpForwardedFor, $trustedProxies);

        $this->assertEquals($expected, $request->getClientIps());
    }

    /**
     * @dataProvider getClientIpsForwardedProvider
     */
    public function testGetClientIpsForwarded($expected, $remoteAddr, $httpForwarded, $trustedProxies)
    {
        $request = $this->getRequestInstanceForClientIpsForwardedTests($remoteAddr, $httpForwarded, $trustedProxies);

        $this->assertEquals($expected, $request->getClientIps());
    }

    public static function getClientIpsForwardedProvider()
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

    public static function getClientIpsProvider()
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
        $this->expectException(ConflictingHeadersException::class);
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

        $this->assertSame(array_reverse(explode(',', $httpXForwardedFor)), $request->getClientIps());
    }

    public static function getClientIpsWithConflictingHeadersProvider()
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

        $this->assertSame($expectedIps, $clientIps);
    }

    public static function getClientIpsWithAgreeingHeadersProvider()
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
        $this->assertEquals('', $req->getContent());
        $this->assertEquals('', $req->getContent());
    }

    public function testGetContentReturnsResource()
    {
        $req = new Request();
        $retval = $req->getContent(true);
        $this->assertIsResource($retval);
        $this->assertEquals('', fread($retval, 1));
        $this->assertTrue(feof($retval));
    }

    public function testGetContentReturnsResourceWhenContentSetInConstructor()
    {
        $req = new Request([], [], [], [], [], [], 'MyContent');
        $resource = $req->getContent(true);

        $this->assertIsResource($resource);
        $this->assertEquals('MyContent', stream_get_contents($resource));
    }

    public function testContentAsResource()
    {
        $resource = fopen('php://memory', 'r+');
        fwrite($resource, 'My other content');
        rewind($resource);

        $req = new Request([], [], [], [], [], [], $resource);
        $this->assertEquals('My other content', stream_get_contents($req->getContent(true)));
        $this->assertEquals('My other content', $req->getContent());
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

        $this->assertSame($a, $b);
    }

    public static function getContentCanBeCalledTwiceWithResourcesProvider()
    {
        return [
            'Fetch then fetch' => [false, false],
            'Fetch then resource' => [false, true],
            'Resource then fetch' => [true, false],
            'Resource then resource' => [true, true],
        ];
    }

    public static function provideOverloadedMethods()
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
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage('Request body is empty.');
        $req->toArray();
    }

    public function testToArrayNonJson()
    {
        $req = new Request([], [], [], [], [], [], 'foobar');
        $this->expectException(JsonException::class);
        $this->expectExceptionMessageMatches('|Could not decode request body.+|');
        $req->toArray();
    }

    public function testToArray()
    {
        $req = new Request([], [], [], [], [], [], json_encode([]));
        $this->assertEquals([], $req->toArray());
        $req = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));
        $this->assertEquals(['foo' => 'bar'], $req->toArray());
    }

    public function testGetPayload()
    {
        $req = new Request([], [], [], [], [], [], json_encode(['foo' => 'bar']));
        $this->assertSame(['foo' => 'bar'], $req->getPayload()->all());
        $req->getPayload()->set('new', 'key');
        $this->assertSame(['foo' => 'bar'], $req->getPayload()->all());

        $req = new Request([], ['foo' => 'bar'], [], [], [], [], json_encode(['baz' => 'qux']));
        $this->assertSame(['foo' => 'bar'], $req->getPayload()->all());
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
        $this->assertEquals('bar1', $request->query->get('foo1'), '::fromGlobals() uses values from $_GET');
        $this->assertEquals('bar2', $request->request->get('foo2'), '::fromGlobals() uses values from $_POST');
        $this->assertEquals('bar3', $request->cookies->get('foo3'), '::fromGlobals() uses values from $_COOKIE');
        $this->assertEquals(['bar4'], $request->files->get('foo4'), '::fromGlobals() uses values from $_FILES');
        $this->assertEquals('bar5', $request->server->get('foo5'), '::fromGlobals() uses values from $_SERVER');
        $this->assertInstanceOf(InputBag::class, $request->request);
        $this->assertInstanceOf(ParameterBag::class, $request->request);

        unset($_GET['foo1'], $_POST['foo2'], $_COOKIE['foo3'], $_FILES['foo4'], $_SERVER['foo5']);

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $request = RequestContentProxy::createFromGlobals();
        $this->assertEquals($normalizedMethod, $request->getMethod());
        $this->assertEquals('mycontent', $request->request->get('content'));
        $this->assertInstanceOf(InputBag::class, $request->request);
        $this->assertInstanceOf(ParameterBag::class, $request->request);

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);

        Request::createFromGlobals();
        Request::enableHttpMethodParameterOverride();
        $_POST['_method'] = $method;
        $_POST['foo6'] = 'bar6';
        $_SERVER['REQUEST_METHOD'] = 'PoSt';
        $request = Request::createFromGlobals();
        $this->assertEquals($normalizedMethod, $request->getMethod());
        $this->assertEquals('POST', $request->getRealMethod());
        $this->assertEquals('bar6', $request->request->get('foo6'));

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

        $this->assertEquals(['foo' => 'bar'], $_GET);

        $request->initialize([], ['foo' => 'bar']);
        $request->overrideGlobals();

        $this->assertEquals(['foo' => 'bar'], $_POST);

        $this->assertArrayNotHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        $request->headers->set('X_FORWARDED_PROTO', 'https');

        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_PROTO);
        $this->assertFalse($request->isSecure());
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertTrue($request->isSecure());

        $request->overrideGlobals();

        $this->assertArrayHasKey('HTTP_X_FORWARDED_PROTO', $_SERVER);

        $request->headers->set('CONTENT_TYPE', 'multipart/form-data');
        $request->headers->set('CONTENT_LENGTH', 12345);

        $request->overrideGlobals();

        $this->assertArrayHasKey('CONTENT_TYPE', $_SERVER);
        $this->assertArrayHasKey('CONTENT_LENGTH', $_SERVER);

        $request->initialize(['foo' => 'bar', 'baz' => 'foo']);
        $request->query->remove('baz');

        $request->overrideGlobals();

        $this->assertEquals(['foo' => 'bar'], $_GET);
        $this->assertEquals('foo=bar', $_SERVER['QUERY_STRING']);
        $this->assertEquals('foo=bar', $request->server->get('QUERY_STRING'));

        // restore initial $_SERVER array
        $_SERVER = $server;
    }

    public function testGetScriptName()
    {
        $request = new Request();
        $this->assertEquals('', $request->getScriptName());

        $server = [];
        $server['SCRIPT_NAME'] = '/index.php';

        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/index.php', $request->getScriptName());

        $server = [];
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/frontend.php', $request->getScriptName());

        $server = [];
        $server['SCRIPT_NAME'] = '/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/frontend.php';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/index.php', $request->getScriptName());
    }

    public function testGetBasePath()
    {
        $request = new Request();
        $this->assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $request->initialize([], [], [], [], [], $server);
        $this->assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['SCRIPT_NAME'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['PHP_SELF'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('', $request->getBasePath());

        $server = [];
        $server['SCRIPT_FILENAME'] = '/some/where/index.php';
        $server['ORIG_SCRIPT_NAME'] = '/index.php';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('', $request->getBasePath());
    }

    public function testGetPathInfo()
    {
        $request = new Request();
        $this->assertEquals('/', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '/path/info';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/path/info', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '/path%20test/info';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/path%20test/info', $request->getPathInfo());

        $server = [];
        $server['REQUEST_URI'] = '?a=b';
        $request->initialize([], [], [], [], [], $server);

        $this->assertEquals('/', $request->getPathInfo());
    }

    public function testGetParameterPrecedence()
    {
        $request = new Request();
        $request->attributes->set('foo', 'attr');
        $request->query->set('foo', 'query');
        $request->request->set('foo', 'body');

        $this->assertSame('attr', $request->get('foo'));

        $request->attributes->remove('foo');
        $this->assertSame('query', $request->get('foo'));

        $request->query->remove('foo');
        $this->assertSame('body', $request->get('foo'));

        $request->request->remove('foo');
        $this->assertNull($request->get('foo'));
    }

    public function testGetPreferredLanguage()
    {
        $request = new Request();
        $this->assertNull($request->getPreferredLanguage());
        $this->assertNull($request->getPreferredLanguage([]));
        $this->assertEquals('fr', $request->getPreferredLanguage(['fr']));
        $this->assertEquals('fr', $request->getPreferredLanguage(['fr', 'en']));
        $this->assertEquals('en', $request->getPreferredLanguage(['en', 'fr']));
        $this->assertEquals('fr-ch', $request->getPreferredLanguage(['fr-ch', 'fr-fr']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals('en', $request->getPreferredLanguage(['en', 'en-us']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8');
        $this->assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, fr-fr; q=0.6, fr; q=0.5');
        $this->assertEquals('en', $request->getPreferredLanguage(['fr', 'en']));
    }

    public function testIsXmlHttpRequest()
    {
        $request = new Request();
        $this->assertFalse($request->isXmlHttpRequest());

        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($request->isXmlHttpRequest());

        $request->headers->remove('X-Requested-With');
        $this->assertFalse($request->isXmlHttpRequest());
    }

    /**
     * @requires extension intl
     */
    public function testIntlLocale()
    {
        $request = new Request();

        $request->setDefaultLocale('fr');
        $this->assertEquals('fr', $request->getLocale());
        $this->assertEquals('fr', \Locale::getDefault());

        $request->setLocale('en');
        $this->assertEquals('en', $request->getLocale());
        $this->assertEquals('en', \Locale::getDefault());

        $request->setDefaultLocale('de');
        $this->assertEquals('en', $request->getLocale());
        $this->assertEquals('en', \Locale::getDefault());
    }

    public function testGetCharsets()
    {
        $request = new Request();
        $this->assertEquals([], $request->getCharsets());
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        $this->assertEquals([], $request->getCharsets()); // testing caching

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1, US-ASCII, UTF-8; q=0.8, ISO-10646-UCS-2; q=0.6');
        $this->assertEquals(['ISO-8859-1', 'US-ASCII', 'UTF-8', 'ISO-10646-UCS-2'], $request->getCharsets());

        $request = new Request();
        $request->headers->set('Accept-Charset', 'ISO-8859-1,utf-8;q=0.7,*;q=0.7');
        $this->assertEquals(['ISO-8859-1', 'utf-8', '*'], $request->getCharsets());
    }

    public function testGetEncodings()
    {
        $request = new Request();
        $this->assertEquals([], $request->getEncodings());
        $request->headers->set('Accept-Encoding', 'gzip,deflate,sdch');
        $this->assertEquals([], $request->getEncodings()); // testing caching

        $request = new Request();
        $request->headers->set('Accept-Encoding', 'gzip,deflate,sdch');
        $this->assertEquals(['gzip', 'deflate', 'sdch'], $request->getEncodings());

        $request = new Request();
        $request->headers->set('Accept-Encoding', 'gzip;q=0.4,deflate;q=0.9,compress;q=0.7');
        $this->assertEquals(['deflate', 'compress', 'gzip'], $request->getEncodings());
    }

    public function testGetAcceptableContentTypes()
    {
        $request = new Request();
        $this->assertEquals([], $request->getAcceptableContentTypes());
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        $this->assertEquals([], $request->getAcceptableContentTypes()); // testing caching

        $request = new Request();
        $request->headers->set('Accept', 'application/vnd.wap.wmlscriptc, text/vnd.wap.wml, application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html, multipart/mixed, */*');
        $this->assertEquals(['application/vnd.wap.wmlscriptc', 'text/vnd.wap.wml', 'application/vnd.wap.xhtml+xml', 'application/xhtml+xml', 'text/html', 'multipart/mixed', '*/*'], $request->getAcceptableContentTypes());
    }

    public function testGetLanguages()
    {
        $request = new Request();
        $this->assertEquals([], $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $this->assertEquals(['zh', 'en_US', 'en'], $request->getLanguages());

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en-us; q=0.6, en; q=0.8');
        $this->assertEquals(['zh', 'en', 'en_US'], $request->getLanguages()); // Test out of order qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, en, en-us');
        $this->assertEquals(['zh', 'en', 'en_US'], $request->getLanguages()); // Test equal weighting without qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh; q=0.6, en, en-us; q=0.6');
        $this->assertEquals(['en', 'zh', 'en_US'], $request->getLanguages()); // Test equal weighting with qvalues

        $request = new Request();
        $request->headers->set('Accept-language', 'zh, i-cherokee; q=0.6');
        $this->assertEquals(['zh', 'cherokee'], $request->getLanguages());
    }

    public function testGetAcceptHeadersReturnString()
    {
        $request = new Request();
        $request->headers->set('Accept', '123');
        $request->headers->set('Accept-Charset', '123');
        $request->headers->set('Accept-Encoding', '123');
        $request->headers->set('Accept-Language', '123');

        $this->assertSame(['123'], $request->getAcceptableContentTypes());
        $this->assertSame(['123'], $request->getCharsets());
        $this->assertSame(['123'], $request->getEncodings());
        $this->assertSame(['123'], $request->getLanguages());
    }

    public function testGetRequestFormat()
    {
        $request = new Request();
        $this->assertEquals('html', $request->getRequestFormat());

        // Ensure that setting different default values over time is possible,
        // aka. setRequestFormat determines the state.
        $this->assertEquals('json', $request->getRequestFormat('json'));
        $this->assertEquals('html', $request->getRequestFormat('html'));

        $request = new Request();
        $this->assertNull($request->getRequestFormat(null));

        $request = new Request();
        $this->assertNull($request->setRequestFormat('foo'));
        $this->assertEquals('foo', $request->getRequestFormat(null));

        $request = new Request(['_format' => 'foo']);
        $this->assertEquals('html', $request->getRequestFormat());
    }

    public function testHasSession()
    {
        $request = new Request();

        $this->assertFalse($request->hasSession());
        $this->assertFalse($request->hasSession(true));

        $request->setSessionFactory(function () {});
        $this->assertTrue($request->hasSession());
        $this->assertFalse($request->hasSession(true));

        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->assertTrue($request->hasSession());
        $this->assertTrue($request->hasSession(true));
    }

    public function testGetSession()
    {
        $request = new Request();

        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->assertTrue($request->hasSession());

        $this->assertInstanceOf(Session::class, $request->getSession());
    }

    public function testHasPreviousSession()
    {
        $request = new Request();

        $this->assertFalse($request->hasPreviousSession());
        $request->cookies->set('MOCKSESSID', 'foo');
        $this->assertFalse($request->hasPreviousSession());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->assertTrue($request->hasPreviousSession());
    }

    public function testToString()
    {
        $request = new Request();

        $request->headers->set('Accept-language', 'zh, en-us; q=0.8, en; q=0.6');
        $request->cookies->set('Foo', 'Bar');

        $asString = (string) $request;

        $this->assertStringContainsString('Accept-Language: zh, en-us; q=0.8, en; q=0.6', $asString);
        $this->assertStringContainsString('Cookie: Foo=Bar', $asString);

        $request->cookies->set('Another', 'Cookie');

        $asString = (string) $request;

        $this->assertStringContainsString('Cookie: Foo=Bar; Another=Cookie', $asString);

        $request->cookies->set('foo.bar', [1, 2]);

        $asString = (string) $request;

        $this->assertStringContainsString('foo.bar%5B0%5D=1; foo.bar%5B1%5D=2', $asString);
    }

    public function testIsMethod()
    {
        $request = new Request();
        $request->setMethod('POST');
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertFalse($request->isMethod('GET'));
        $this->assertFalse($request->isMethod('get'));

        $request->setMethod('GET');
        $this->assertTrue($request->isMethod('GET'));
        $this->assertTrue($request->isMethod('get'));
        $this->assertFalse($request->isMethod('POST'));
        $this->assertFalse($request->isMethod('post'));
    }

    /**
     * @dataProvider getBaseUrlData
     */
    public function testGetBaseUrl($uri, $server, $expectedBaseUrl, $expectedPathInfo)
    {
        $request = Request::create($uri, 'GET', [], [], [], $server);

        $this->assertSame($expectedBaseUrl, $request->getBaseUrl(), 'baseUrl');
        $this->assertSame($expectedPathInfo, $request->getPathInfo(), 'pathInfo');
    }

    public static function getBaseUrlData()
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

        $this->assertSame($expect, $me->invoke($request, $string, $prefix));
    }

    public static function urlencodedStringPrefixData()
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
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // disabling proxy trusting
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // request is forwarded by a non-trusted proxy
        Request::setTrustedProxies(['2.2.2.2'], Request::HEADER_X_FORWARDED_FOR);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $this->assertEquals('1.1.1.1', $request->getClientIp());
        $this->assertEquals('foo.example.com', $request->getHost());
        $this->assertEquals(443, $request->getPort());
        $this->assertTrue($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.4', '2.2.2.2'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // check various X_FORWARDED_PROTO header values
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_X_FORWARDED_PROTO);
        $request->headers->set('X_FORWARDED_PROTO', 'ssl');
        $this->assertTrue($request->isSecure());

        $request->headers->set('X_FORWARDED_PROTO', 'https, http');
        $this->assertTrue($request->isSecure());
    }

    public function testTrustedProxiesForwarded()
    {
        $request = Request::create('http://example.com/');
        $request->server->set('REMOTE_ADDR', '3.3.3.3');
        $request->headers->set('FORWARDED', 'for=1.1.1.1, host=foo.example.com:8080, proto=https, for=2.2.2.2, host=real.example.com:8080');

        // no trusted proxies
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // disabling proxy trusting
        Request::setTrustedProxies([], Request::HEADER_FORWARDED);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // request is forwarded by a non-trusted proxy
        Request::setTrustedProxies(['2.2.2.2'], Request::HEADER_FORWARDED);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_FORWARDED);
        $this->assertEquals('1.1.1.1', $request->getClientIp());
        $this->assertEquals('foo.example.com', $request->getHost());
        $this->assertEquals(8080, $request->getPort());
        $this->assertTrue($request->isSecure());

        // trusted proxy via setTrustedProxies()
        Request::setTrustedProxies(['3.3.3.4', '2.2.2.2'], Request::HEADER_FORWARDED);
        $this->assertEquals('3.3.3.3', $request->getClientIp());
        $this->assertEquals('example.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());
        $this->assertFalse($request->isSecure());

        // check various X_FORWARDED_PROTO header values
        Request::setTrustedProxies(['3.3.3.3', '2.2.2.2'], Request::HEADER_FORWARDED);
        $request->headers->set('FORWARDED', 'proto=ssl');
        $this->assertTrue($request->isSecure());

        $request->headers->set('FORWARDED', 'proto=https, proto=http');
        $this->assertTrue($request->isSecure());
    }

    /**
     * @dataProvider iisRequestUriProvider
     */
    public function testIISRequestUri($headers, $server, $expectedRequestUri)
    {
        $request = new Request();
        $request->headers->replace($headers);
        $request->server->replace($server);

        $this->assertEquals($expectedRequestUri, $request->getRequestUri(), '->getRequestUri() is correct');

        $subRequestUri = '/bar/foo';
        $subRequest = Request::create($subRequestUri, 'get', [], [], [], $request->server->all());
        $this->assertEquals($subRequestUri, $subRequest->getRequestUri(), '->getRequestUri() is correct in sub request');
    }

    public static function iisRequestUriProvider()
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
        $this->assertEquals('evil.com', $request->getHost());

        // add a trusted domain and all its subdomains
        Request::setTrustedHosts(['^([a-z]{9}\.)?trusted\.com$']);

        // untrusted host
        $request->headers->set('host', 'evil.com');
        try {
            $request->getHost();
            $this->fail('Request::getHost() should throw an exception when host is not trusted.');
        } catch (SuspiciousOperationException $e) {
            $this->assertEquals('Untrusted Host "evil.com".', $e->getMessage());
        }

        // trusted hosts
        $request->headers->set('host', 'trusted.com');
        $this->assertEquals('trusted.com', $request->getHost());
        $this->assertEquals(80, $request->getPort());

        $request->server->set('HTTPS', true);
        $request->headers->set('host', 'trusted.com');
        $this->assertEquals('trusted.com', $request->getHost());
        $this->assertEquals(443, $request->getPort());
        $request->server->set('HTTPS', false);

        $request->headers->set('host', 'trusted.com:8000');
        $this->assertEquals('trusted.com', $request->getHost());
        $this->assertEquals(8000, $request->getPort());

        $request->headers->set('host', 'subdomain.trusted.com');
        $this->assertEquals('subdomain.trusted.com', $request->getHost());
    }

    public function testSetTrustedHostsDoesNotBreakOnSpecialCharacters()
    {
        Request::setTrustedHosts(['localhost(\.local){0,1}#,example.com', 'localhost']);

        $request = Request::create('/');
        $request->headers->set('host', 'localhost');
        $this->assertSame('localhost', $request->getHost());
    }

    public function testFactory()
    {
        Request::setFactory(fn (array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null) => new NewRequest());

        $this->assertEquals('foo', Request::create('/')->getFoo());

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
        $this->assertEquals($host, $request->getHost());
        $this->assertLessThan(5, microtime(true) - $start);
    }

    /**
     * @dataProvider getHostValidities
     */
    public function testHostValidity($host, $isValid, $expectedHost = null, $expectedPort = null)
    {
        $request = Request::create('/');
        $request->headers->set('host', $host);

        if ($isValid) {
            $this->assertSame($expectedHost ?: $host, $request->getHost());
            if ($expectedPort) {
                $this->assertSame($expectedPort, $request->getPort());
            }
        } else {
            $this->expectException(SuspiciousOperationException::class);
            $this->expectExceptionMessage('Invalid Host');

            $request->getHost();
        }
    }

    public static function getHostValidities()
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

    public static function getLongHostNames()
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
        $this->assertEquals($idempotent, $request->isMethodIdempotent());
    }

    public static function methodIdempotentProvider()
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
        $this->assertEquals($safe, $request->isMethodSafe());
    }

    public static function methodSafeProvider()
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
        $this->assertEquals($cacheable, $request->isMethodCacheable());
    }

    public static function methodCacheableProvider()
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

        $this->assertSame($expected, $request->getProtocolVersion());
    }

    public static function protocolVersionProvider()
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

    public static function nonstandardRequestsData()
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
        $expectedBaseUrl ??= $expectedBasePath;

        $server = [
            'HTTP_HOST' => 'host:8080',
            'SERVER_PORT' => '8080',
            'QUERY_STRING' => $queryString,
            'PHP_SELF' => '/hello/app.php',
            'SCRIPT_FILENAME' => '/some/path/app.php',
            'REQUEST_URI' => $requestUri,
        ];

        $request = new Request([], [], [], [], [], $server);

        $this->assertEquals($expectedPathInfo, $request->getPathInfo());
        $this->assertEquals($expectedUri, $request->getUri());
        $this->assertEquals($queryString, $request->getQueryString());
        $this->assertEquals(8080, $request->getPort());
        $this->assertEquals('host:8080', $request->getHttpHost());
        $this->assertEquals($expectedBaseUrl, $request->getBaseUrl());
        $this->assertEquals($expectedBasePath, $request->getBasePath());
    }

    public function testTrustedHost()
    {
        Request::setTrustedProxies(['1.1.1.1'], -1);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost:8080');
        $request->headers->set('X-Forwarded-Host', 'localhost:8080');

        $this->assertSame('localhost:8080', $request->getHttpHost());
        $this->assertSame(8080, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host="[::1]:443"');
        $request->headers->set('X-Forwarded-Host', '[::1]:443');
        $request->headers->set('X-Forwarded-Port', 443);

        $this->assertSame('[::1]:443', $request->getHttpHost());
        $this->assertSame(443, $request->getPort());
    }

    public function testTrustedPrefix()
    {
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_TRAEFIK);

        // test with index deployed under root
        $request = Request::create('/method');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('X-Forwarded-Prefix', '/myprefix');
        $request->headers->set('Forwarded', 'host=localhost:8080');

        $this->assertSame('/myprefix', $request->getBaseUrl());
        $this->assertSame('/myprefix', $request->getBasePath());
        $this->assertSame('/method', $request->getPathInfo());
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

        $this->assertSame('/prefix/public', $request->getBaseUrl());
        $this->assertSame('/prefix/public', $request->getBasePath());
        $this->assertSame('/method', $request->getPathInfo());
    }

    public function testTrustedPrefixEmpty()
    {
        // check that there is no error, if no prefix is provided
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_TRAEFIK);
        $request = Request::create('/method');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $this->assertSame('', $request->getBaseUrl());
    }

    public function testTrustedPort()
    {
        Request::setTrustedProxies(['1.1.1.1'], -1);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost:8080');
        $request->headers->set('X-Forwarded-Port', 8080);

        $this->assertSame(8080, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host=localhost');
        $request->headers->set('X-Forwarded-Port', 80);

        $this->assertSame(80, $request->getPort());

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('Forwarded', 'host="[::1]"');
        $request->headers->set('X-Forwarded-Proto', 'https');
        $request->headers->set('X-Forwarded-Port', 443);

        $this->assertSame(443, $request->getPort());
    }

    public function testTrustedPortDoesNotDefaultToZero()
    {
        Request::setTrustedProxies(['1.1.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.1.1.1');
        $request->headers->set('X-Forwarded-Host', 'test.example.com');
        $request->headers->set('X-Forwarded-Port', '');

        $this->assertSame(80, $request->getPort());
    }

    /**
     * @dataProvider trustedProxiesRemoteAddr
     */
    public function testTrustedProxiesRemoteAddr($serverRemoteAddr, $trustedProxies, $result)
    {
        $_SERVER['REMOTE_ADDR'] = $serverRemoteAddr;
        Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame($result, Request::getTrustedProxies());
    }

    public static function trustedProxiesRemoteAddr()
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

        $this->assertEquals($safePreferenceExpected, $request->preferSafeContent());
    }

    public static function preferSafeContentData()
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

    public function testReservedFlags()
    {
        foreach ((new \ReflectionClass(Request::class))->getConstants() as $constant => $value) {
            $this->assertNotSame(0b10000000, $value, sprintf('The constant "%s" should not use the reserved value "0b10000000".', $constant));
        }
    }

    /**
     * @group legacy
     */
    public function testInvalidUriCreationDeprecated()
    {
        $this->expectDeprecation('Since symfony/http-foundation 6.3: Calling "Symfony\Component\HttpFoundation\Request::create()" with an invalid URI is deprecated.');
        Request::create('/invalid-path:123');
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
