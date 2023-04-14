<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Exception\BadMethodCallException;
use Symfony\Component\BrowserKit\Exception\InvalidArgumentException;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

class AbstractBrowserTest extends TestCase
{
    public function getBrowser(array $server = [], History $history = null, CookieJar $cookieJar = null)
    {
        return new TestClient($server, $history, $cookieJar);
    }

    public function testGetHistory()
    {
        $client = $this->getBrowser([], $history = new History());
        $this->assertSame($history, $client->getHistory(), '->getHistory() returns the History');
    }

    public function testGetCookieJar()
    {
        $client = $this->getBrowser([], null, $cookieJar = new CookieJar());
        $this->assertSame($cookieJar, $client->getCookieJar(), '->getCookieJar() returns the CookieJar');
    }

    public function testGetRequest()
    {
        $client = $this->getBrowser();
        $client->request('GET', 'http://example.com/');

        $this->assertEquals('http://example.com/', $client->getRequest()->getUri(), '->getCrawler() returns the Request of the last request');
    }

    public function testGetRequestNull()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The "request()" method must be called before "Symfony\\Component\\BrowserKit\\AbstractBrowser::getRequest()".');

        $client = $this->getBrowser();
        $this->assertNull($client->getRequest());
    }

    public function testXmlHttpRequest()
    {
        $client = $this->getBrowser();
        $client->xmlHttpRequest('GET', 'http://example.com/', [], [], [], null, true);
        $this->assertSame('XMLHttpRequest', $client->getRequest()->getServer()['HTTP_X_REQUESTED_WITH']);
        $this->assertFalse($client->getServerParameter('HTTP_X_REQUESTED_WITH', false));
    }

    public function testJsonRequest()
    {
        $client = $this->getBrowser();
        $client->jsonRequest('GET', 'http://example.com/', ['param' => 1], [], true);
        $this->assertSame('application/json', $client->getRequest()->getServer()['CONTENT_TYPE']);
        $this->assertSame('application/json', $client->getRequest()->getServer()['HTTP_ACCEPT']);
        $this->assertFalse($client->getServerParameter('CONTENT_TYPE', false));
        $this->assertFalse($client->getServerParameter('HTTP_ACCEPT', false));
        $this->assertSame('{"param":1}', $client->getRequest()->getContent());
    }

    public function testGetRequestWithIpAsHttpHost()
    {
        $client = $this->getBrowser();
        $client->request('GET', 'https://example.com/foo', [], [], ['HTTP_HOST' => '127.0.0.1']);

        $this->assertSame('https://example.com/foo', $client->getRequest()->getUri());
        $headers = $client->getRequest()->getServer();
        $this->assertSame('127.0.0.1', $headers['HTTP_HOST']);
    }

    public function testGetResponse()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('foo'));
        $client->request('GET', 'http://example.com/');

        $this->assertSame('foo', $client->getResponse()->getContent(), '->getCrawler() returns the Response of the last request');
        $this->assertInstanceOf(Response::class, $client->getResponse(), '->getCrawler() returns the Response of the last request');
    }

    public function testGetResponseNull()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The "request()" method must be called before "Symfony\\Component\\BrowserKit\\AbstractBrowser::getResponse()".');

        $client = $this->getBrowser();
        $this->assertNull($client->getResponse());
    }

    public function testGetInternalResponseNull()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The "request()" method must be called before "Symfony\\Component\\BrowserKit\\AbstractBrowser::getInternalResponse()".');

        $client = $this->getBrowser();
        $this->assertNull($client->getInternalResponse());
    }

    public function testGetContent()
    {
        $json = '{"jsonrpc":"2.0","method":"echo","id":7,"params":["Hello World"]}';

        $client = $this->getBrowser();
        $client->request('POST', 'http://example.com/jsonrpc', [], [], [], $json);
        $this->assertSame($json, $client->getRequest()->getContent());
    }

    public function testGetCrawler()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('foo'));
        $crawler = $client->request('GET', 'http://example.com/');

        $this->assertSame($crawler, $client->getCrawler(), '->getCrawler() returns the Crawler of the last request');
    }

    public function testGetCrawlerNull()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The "request()" method must be called before "Symfony\\Component\\BrowserKit\\AbstractBrowser::getCrawler()".');

        $client = $this->getBrowser();
        $this->assertNull($client->getCrawler());
    }

    public function testRequestHttpHeaders()
    {
        $client = $this->getBrowser();
        $client->request('GET', '/');
        $headers = $client->getRequest()->getServer();
        $this->assertSame('localhost', $headers['HTTP_HOST'], '->request() sets the HTTP_HOST header');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com');
        $headers = $client->getRequest()->getServer();
        $this->assertSame('www.example.com', $headers['HTTP_HOST'], '->request() sets the HTTP_HOST header');

        $client->request('GET', 'https://www.example.com');
        $headers = $client->getRequest()->getServer();
        $this->assertTrue($headers['HTTPS'], '->request() sets the HTTPS header');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com:8080');
        $headers = $client->getRequest()->getServer();
        $this->assertSame('www.example.com:8080', $headers['HTTP_HOST'], '->request() sets the HTTP_HOST header with port');
    }

    public function testRequestURIConversion()
    {
        $client = $this->getBrowser();
        $client->request('GET', '/foo');
        $this->assertSame('http://localhost/foo', $client->getRequest()->getUri(), '->request() converts the URI to an absolute one');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com');
        $this->assertSame('http://www.example.com', $client->getRequest()->getUri(), '->request() does not change absolute URIs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/');
        $client->request('GET', '/foo');
        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo');
        $client->request('GET', '#');
        $this->assertSame('http://www.example.com/foo#', $client->getRequest()->getUri(), '->request() uses the previous request for #');
        $client->request('GET', '#');
        $this->assertSame('http://www.example.com/foo#', $client->getRequest()->getUri(), '->request() uses the previous request for #');
        $client->request('GET', '#foo');
        $this->assertSame('http://www.example.com/foo#foo', $client->getRequest()->getUri(), '->request() uses the previous request for #');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/');
        $client->request('GET', 'bar');
        $this->assertSame('http://www.example.com/foo/bar', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->request('GET', 'bar');
        $this->assertSame('http://www.example.com/foo/bar', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/');
        $client->request('GET', 'http');
        $this->assertSame('http://www.example.com/foo/http', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo');
        $client->request('GET', 'http/bar');
        $this->assertSame('http://www.example.com/http/bar', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/');
        $client->request('GET', 'http');
        $this->assertSame('http://www.example.com/http', $client->getRequest()->getUri(), '->request() uses the previous request for relative URLs');

        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo');
        $client->request('GET', '?');
        $this->assertSame('http://www.example.com/foo?', $client->getRequest()->getUri(), '->request() uses the previous request for ?');
        $client->request('GET', '?');
        $this->assertSame('http://www.example.com/foo?', $client->getRequest()->getUri(), '->request() uses the previous request for ?');
        $client->request('GET', '?foo=bar');
        $this->assertSame('http://www.example.com/foo?foo=bar', $client->getRequest()->getUri(), '->request() uses the previous request for ?');
    }

    public function testRequestReferer()
    {
        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->request('GET', 'bar');
        $server = $client->getRequest()->getServer();
        $this->assertSame('http://www.example.com/foo/foobar', $server['HTTP_REFERER'], '->request() sets the referer');
    }

    public function testRequestRefererCanBeOverridden()
    {
        $client = new TestClient();
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->request('GET', 'bar', [], [], ['HTTP_REFERER' => 'xyz']);
        $server = $client->getRequest()->getServer();
        $this->assertSame('xyz', $server['HTTP_REFERER'], '->request() allows referer to be overridden');
    }

    public function testRequestHistory()
    {
        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->request('GET', 'bar');

        $this->assertSame('http://www.example.com/foo/bar', $client->getHistory()->current()->getUri(), '->request() updates the History');
        $this->assertSame('http://www.example.com/foo/foobar', $client->getHistory()->back()->getUri(), '->request() updates the History');
    }

    public function testRequestCookies()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><a href="/foo">foo</a></html>', 200, ['Set-Cookie' => 'foo=bar']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame(['foo' => 'bar'], $client->getCookieJar()->allValues('http://www.example.com/foo/foobar'), '->request() updates the CookieJar');

        $client->request('GET', 'bar');
        $this->assertSame(['foo' => 'bar'], $client->getCookieJar()->allValues('http://www.example.com/foo/foobar'), '->request() updates the CookieJar');
    }

    public function testRequestSecureCookies()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><a href="/foo">foo</a></html>', 200, ['Set-Cookie' => 'foo=bar; path=/; secure']));
        $client->request('GET', 'https://www.example.com/foo/foobar');

        $this->assertTrue($client->getCookieJar()->get('foo', '/', 'www.example.com')->isSecure());
    }

    public function testClick()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><a href="/foo">foo</a></html>'));
        $crawler = $client->request('GET', 'http://www.example.com/foo/foobar');

        $client->click($crawler->filter('a')->link());

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->click() clicks on links');
    }

    public function testClickLink()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><a href="/foo">foo</a></html>'));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->clickLink('foo');

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->click() clicks on links');
    }

    public function testClickLinkNotFound()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><a href="/foo">foobar</a></html>'));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->expectException(\InvalidArgumentException::class);
        $client->clickLink('foo');
    }

    public function testClickForm()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><form action="/foo"><input type="submit" /></form></html>'));
        $crawler = $client->request('GET', 'http://www.example.com/foo/foobar');

        $client->click($crawler->filter('input')->form());

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->click() Form submit forms');
    }

    public function testSubmit()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><form action="/foo"><input type="submit" /></form></html>'));
        $crawler = $client->request('GET', 'http://www.example.com/foo/foobar');

        $client->submit($crawler->filter('input')->form());

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->submit() submit forms');
    }

    public function testSubmitForm()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><form name="signup" action="/foo"><input type="text" name="username" value="the username" /><input type="password" name="password" value="the password" /><input type="submit" value="Register" /></form></html>'));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $client->submitForm('Register', [
            'username' => 'new username',
            'password' => 'new password',
        ], 'PUT', [
            'HTTP_USER_AGENT' => 'Symfony User Agent',
        ]);

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->submitForm() submit forms');
        $this->assertSame('PUT', $client->getRequest()->getMethod(), '->submitForm() allows to change the method');
        $this->assertSame('new username', $client->getRequest()->getParameters()['username'], '->submitForm() allows to override the form values');
        $this->assertSame('new password', $client->getRequest()->getParameters()['password'], '->submitForm() allows to override the form values');
        $this->assertSame('Symfony User Agent', $client->getRequest()->getServer()['HTTP_USER_AGENT'], '->submitForm() allows to change the $_SERVER parameters');
    }

    public function testSubmitFormNotFound()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><form action="/foo"><input type="submit" /></form></html>'));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->expectExceptionObject(
            new InvalidArgumentException('There is no button with "Register" as its content, id, value or name.')
        );

        $client->submitForm('Register', [
            'username' => 'username',
            'password' => 'password',
        ], 'POST');
    }

    public function testSubmitPreserveAuth()
    {
        $client = $this->getBrowser(['PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'bar']);
        $client->setNextResponse(new Response('<html><form action="/foo"><input type="submit" /></form></html>'));
        $crawler = $client->request('GET', 'http://www.example.com/foo/foobar');

        $server = $client->getRequest()->getServer();
        $this->assertArrayHasKey('PHP_AUTH_USER', $server);
        $this->assertSame('foo', $server['PHP_AUTH_USER']);
        $this->assertArrayHasKey('PHP_AUTH_PW', $server);
        $this->assertSame('bar', $server['PHP_AUTH_PW']);

        $client->submit($crawler->filter('input')->form());

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->submit() submit forms');

        $server = $client->getRequest()->getServer();
        $this->assertArrayHasKey('PHP_AUTH_USER', $server);
        $this->assertSame('foo', $server['PHP_AUTH_USER']);
        $this->assertArrayHasKey('PHP_AUTH_PW', $server);
        $this->assertSame('bar', $server['PHP_AUTH_PW']);
    }

    public function testSubmitPassthrewHeaders()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('<html><form action="/foo"><input type="submit" /></form></html>'));
        $crawler = $client->request('GET', 'http://www.example.com/foo/foobar');
        $headers = ['Accept-Language' => 'de'];

        $client->submit($crawler->filter('input')->form(), [], $headers);

        $server = $client->getRequest()->getServer();
        $this->assertArrayHasKey('Accept-Language', $server);
        $this->assertSame('de', $server['Accept-Language']);
    }

    public function testFollowRedirect()
    {
        $client = $this->getBrowser();
        $client->followRedirects(false);
        $client->request('GET', 'http://www.example.com/foo/foobar');

        try {
            $client->followRedirect();
            $this->fail('->followRedirect() throws a \LogicException if the request was not redirected');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e, '->followRedirect() throws a \LogicException if the request was not redirected');
        }

        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->followRedirect();

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect if any');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() automatically follows redirects if followRedirects is true');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 201, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->assertSame('http://www.example.com/foo/foobar', $client->getRequest()->getUri(), '->followRedirect() does not follow redirect if HTTP Code is not 30x');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 201, ['Location' => 'http://www.example.com/redirected']));
        $client->followRedirects(false);
        $client->request('GET', 'http://www.example.com/foo/foobar');

        try {
            $client->followRedirect();
            $this->fail('->followRedirect() throws a \LogicException if the request did not respond with 30x HTTP Code');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e, '->followRedirect() throws a \LogicException if the request did not respond with 30x HTTP Code');
        }
    }

    public function testFollowRelativeRedirect()
    {
        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, ['Location' => '/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect if any');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, ['Location' => '/redirected:1234']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame('http://www.example.com/redirected:1234', $client->getRequest()->getUri(), '->followRedirect() follows relative urls');
    }

    public function testFollowRedirectWithMaxRedirects()
    {
        $client = $this->getBrowser();
        $client->setMaxRedirects(1);
        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect if any');

        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected2']));
        try {
            $client->followRedirect();
            $this->fail('->followRedirect() throws a \LogicException if the request was redirected and limit of redirections was reached');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e, '->followRedirect() throws a \LogicException if the request was redirected and limit of redirections was reached');
        }

        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect if any');

        $client->setNextResponse(new Response('', 302, ['Location' => '/redirected']));
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows relative URLs');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, ['Location' => '//www.example.org/']));
        $client->request('GET', 'https://www.example.com/');

        $this->assertSame('https://www.example.org/', $client->getRequest()->getUri(), '->followRedirect() follows protocol-relative URLs');

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, ['Location' => 'http://www.example.com/redirected']));
        $client->request('POST', 'http://www.example.com/foo/foobar', ['name' => 'bar']);

        $this->assertSame('GET', $client->getRequest()->getMethod(), '->followRedirect() uses a GET for 302');
        $this->assertSame([], $client->getRequest()->getParameters(), '->followRedirect() does not submit parameters when changing the method');
    }

    public function testFollowRedirectWithCookies()
    {
        $client = $this->getBrowser();
        $client->followRedirects(false);
        $client->setNextResponse(new Response('', 302, [
            'Location' => 'http://www.example.com/redirected',
            'Set-Cookie' => 'foo=bar',
        ]));
        $client->request('GET', 'http://www.example.com/');
        $this->assertSame([], $client->getRequest()->getCookies());
        $client->followRedirect();
        $this->assertSame(['foo' => 'bar'], $client->getRequest()->getCookies());
    }

    public function testFollowRedirectWithHeaders()
    {
        $headers = [
            'HTTP_HOST' => 'www.example.com',
            'HTTP_USER_AGENT' => 'Symfony BrowserKit',
            'CONTENT_TYPE' => 'application/vnd.custom+xml',
            'HTTPS' => false,
        ];

        $client = $this->getBrowser();
        $client->followRedirects(false);
        $client->setNextResponse(new Response('', 302, [
            'Location' => 'http://www.example.com/redirected',
        ]));
        $client->request('GET', 'http://www.example.com/', [], [], [
            'CONTENT_TYPE' => 'application/vnd.custom+xml',
        ]);

        $this->assertEquals($headers, $client->getRequest()->getServer());

        $client->followRedirect();

        $headers['HTTP_REFERER'] = 'http://www.example.com/';

        $this->assertEquals($headers, $client->getRequest()->getServer());
    }

    public function testFollowRedirectWithPort()
    {
        $headers = [
            'HTTP_HOST' => 'www.example.com:8080',
            'HTTP_USER_AGENT' => 'Symfony BrowserKit',
            'HTTPS' => false,
            'HTTP_REFERER' => 'http://www.example.com:8080/',
        ];

        $client = $this->getBrowser();
        $client->setNextResponse(new Response('', 302, [
            'Location' => 'http://www.example.com:8080/redirected',
        ]));
        $client->request('GET', 'http://www.example.com:8080/');

        $this->assertEquals($headers, $client->getRequest()->getServer());
    }

    public function testIsFollowingRedirects()
    {
        $client = $this->getBrowser();
        $this->assertTrue($client->isFollowingRedirects(), '->getFollowRedirects() returns default value');
        $client->followRedirects(false);
        $this->assertFalse($client->isFollowingRedirects(), '->getFollowRedirects() returns assigned value');
    }

    public function testGetMaxRedirects()
    {
        $client = $this->getBrowser();
        $this->assertSame(-1, $client->getMaxRedirects(), '->getMaxRedirects() returns default value');
        $client->setMaxRedirects(3);
        $this->assertSame(3, $client->getMaxRedirects(), '->getMaxRedirects() returns assigned value');
    }

    public function testFollowRedirectWithPostMethod()
    {
        $parameters = ['foo' => 'bar'];
        $files = ['myfile.foo' => 'baz'];
        $server = ['X_TEST_FOO' => 'bazbar'];
        $content = 'foobarbaz';

        $client = $this->getBrowser();

        $client->setNextResponse(new Response('', 307, ['Location' => 'http://www.example.com/redirected']));
        $client->request('POST', 'http://www.example.com/foo/foobar', $parameters, $files, $server, $content);

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect with POST method');
        $this->assertArrayHasKey('foo', $client->getRequest()->getParameters(), '->followRedirect() keeps parameters with POST method');
        $this->assertArrayHasKey('myfile.foo', $client->getRequest()->getFiles(), '->followRedirect() keeps files with POST method');
        $this->assertArrayHasKey('X_TEST_FOO', $client->getRequest()->getServer(), '->followRedirect() keeps $_SERVER with POST method');
        $this->assertSame($content, $client->getRequest()->getContent(), '->followRedirect() keeps content with POST method');
        $this->assertSame('POST', $client->getRequest()->getMethod(), '->followRedirect() keeps request method');
    }

    public function testFollowRedirectDropPostMethod()
    {
        $parameters = ['foo' => 'bar'];
        $files = ['myfile.foo' => 'baz'];
        $server = ['X_TEST_FOO' => 'bazbar'];
        $content = 'foobarbaz';

        $client = $this->getBrowser();

        foreach ([301, 302, 303] as $code) {
            $client->setNextResponse(new Response('', $code, ['Location' => 'http://www.example.com/redirected']));
            $client->request('POST', 'http://www.example.com/foo/foobar', $parameters, $files, $server, $content);

            $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->followRedirect() follows a redirect with POST method on response code: '.$code.'.');
            $this->assertEmpty($client->getRequest()->getParameters(), '->followRedirect() drops parameters with POST method on response code: '.$code.'.');
            $this->assertEmpty($client->getRequest()->getFiles(), '->followRedirect() drops files with POST method on response code: '.$code.'.');
            $this->assertArrayHasKey('X_TEST_FOO', $client->getRequest()->getServer(), '->followRedirect() keeps $_SERVER with POST method on response code: '.$code.'.');
            $this->assertEmpty($client->getRequest()->getContent(), '->followRedirect() drops content with POST method on response code: '.$code.'.');
            $this->assertSame('GET', $client->getRequest()->getMethod(), '->followRedirect() drops request method to GET on response code: '.$code.'.');
        }
    }

    /**
     * @dataProvider getTestsForMetaRefresh
     */
    public function testFollowMetaRefresh(string $content, string $expectedEndingUrl, bool $followMetaRefresh = true)
    {
        $client = $this->getBrowser();
        $client->followMetaRefresh($followMetaRefresh);
        $client->setNextResponse(new Response($content));
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $this->assertSame($expectedEndingUrl, $client->getRequest()->getUri());
    }

    public static function getTestsForMetaRefresh()
    {
        return [
            ['<html><head><meta http-equiv="Refresh" content="4" /><meta http-equiv="refresh" content="0; URL=http://www.example.com/redirected"/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content="0;URL=http://www.example.com/redirected"/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content="0;URL=\'http://www.example.com/redirected\'"/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content=\'0;URL="http://www.example.com/redirected"\'/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content="0; URL = http://www.example.com/redirected"/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content="0;URL= http://www.example.com/redirected  "/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><meta http-equiv="refresh" content="0;url=http://www.example.com/redirected  "/></head></html>', 'http://www.example.com/redirected'],
            ['<html><head><noscript><meta http-equiv="refresh" content="0;URL=http://www.example.com/redirected"/></noscript></head></head></html>', 'http://www.example.com/redirected'],
            // Non-zero timeout should not result in a redirect.
            ['<html><head><meta http-equiv="refresh" content="4; URL=http://www.example.com/redirected"/></head></html>', 'http://www.example.com/foo/foobar'],
            ['<html><body></body></html>', 'http://www.example.com/foo/foobar'],
            // Invalid meta tag placement should not result in a redirect.
            ['<html><body><meta http-equiv="refresh" content="0;url=http://www.example.com/redirected"/></body></html>', 'http://www.example.com/foo/foobar'],
            // Valid meta refresh should not be followed if disabled.
            ['<html><head><meta http-equiv="refresh" content="0;URL=http://www.example.com/redirected"/></head></html>', 'http://www.example.com/foo/foobar', false],
        ];
    }

    public function testBack()
    {
        $client = $this->getBrowser();

        $parameters = ['foo' => 'bar'];
        $files = ['myfile.foo' => 'baz'];
        $server = ['X_TEST_FOO' => 'bazbar'];
        $content = 'foobarbaz';

        $client->request('GET', 'http://www.example.com/foo/foobar', $parameters, $files, $server, $content);
        $client->request('GET', 'http://www.example.com/foo');
        $client->back();

        $this->assertSame('http://www.example.com/foo/foobar', $client->getRequest()->getUri(), '->back() goes back in the history');
        $this->assertArrayHasKey('foo', $client->getRequest()->getParameters(), '->back() keeps parameters');
        $this->assertArrayHasKey('myfile.foo', $client->getRequest()->getFiles(), '->back() keeps files');
        $this->assertArrayHasKey('X_TEST_FOO', $client->getRequest()->getServer(), '->back() keeps $_SERVER');
        $this->assertSame($content, $client->getRequest()->getContent(), '->back() keeps content');
    }

    public function testForward()
    {
        $client = $this->getBrowser();

        $parameters = ['foo' => 'bar'];
        $files = ['myfile.foo' => 'baz'];
        $server = ['X_TEST_FOO' => 'bazbar'];
        $content = 'foobarbaz';

        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->request('GET', 'http://www.example.com/foo', $parameters, $files, $server, $content);
        $client->back();
        $client->forward();

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->forward() goes forward in the history');
        $this->assertArrayHasKey('foo', $client->getRequest()->getParameters(), '->forward() keeps parameters');
        $this->assertArrayHasKey('myfile.foo', $client->getRequest()->getFiles(), '->forward() keeps files');
        $this->assertArrayHasKey('X_TEST_FOO', $client->getRequest()->getServer(), '->forward() keeps $_SERVER');
        $this->assertSame($content, $client->getRequest()->getContent(), '->forward() keeps content');
    }

    public function testBackAndFrowardWithRedirects()
    {
        $client = $this->getBrowser();

        $client->request('GET', 'http://www.example.com/foo');
        $client->setNextResponse(new Response('', 301, ['Location' => 'http://www.example.com/redirected']));
        $client->request('GET', 'http://www.example.com/bar');

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), 'client followed redirect');

        $client->back();

        $this->assertSame('http://www.example.com/foo', $client->getRequest()->getUri(), '->back() goes back in the history skipping redirects');

        $client->forward();

        $this->assertSame('http://www.example.com/redirected', $client->getRequest()->getUri(), '->forward() goes forward in the history skipping redirects');
    }

    public function testReload()
    {
        $client = $this->getBrowser();

        $parameters = ['foo' => 'bar'];
        $files = ['myfile.foo' => 'baz'];
        $server = ['X_TEST_FOO' => 'bazbar'];
        $content = 'foobarbaz';

        $client->request('GET', 'http://www.example.com/foo/foobar', $parameters, $files, $server, $content);
        $client->reload();

        $this->assertSame('http://www.example.com/foo/foobar', $client->getRequest()->getUri(), '->reload() reloads the current page');
        $this->assertArrayHasKey('foo', $client->getRequest()->getParameters(), '->reload() keeps parameters');
        $this->assertArrayHasKey('myfile.foo', $client->getRequest()->getFiles(), '->reload() keeps files');
        $this->assertArrayHasKey('X_TEST_FOO', $client->getRequest()->getServer(), '->reload() keeps $_SERVER');
        $this->assertSame($content, $client->getRequest()->getContent(), '->reload() keeps content');
    }

    public function testRestart()
    {
        $client = $this->getBrowser();
        $client->request('GET', 'http://www.example.com/foo/foobar');
        $client->restart();

        $this->assertTrue($client->getHistory()->isEmpty(), '->restart() clears the history');
        $this->assertSame([], $client->getCookieJar()->all(), '->restart() clears the cookies');
    }

    /**
     * @runInSeparateProcess
     */
    public function testInsulatedRequests()
    {
        $client = $this->getBrowser();
        $client->insulate();
        $client->setNextScript("new Symfony\Component\BrowserKit\Response('foobar')");
        $client->request('GET', 'http://www.example.com/foo/foobar');

        $this->assertSame('foobar', $client->getResponse()->getContent(), '->insulate() process the request in a forked process');

        $client->setNextScript("new Symfony\Component\BrowserKit\Response('foobar)");

        try {
            $client->request('GET', 'http://www.example.com/foo/foobar');
            $this->fail('->request() throws a \RuntimeException if the script has an error');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, '->request() throws a \RuntimeException if the script has an error');
        }
    }

    public function testGetServerParameter()
    {
        $client = $this->getBrowser();
        $this->assertSame('', $client->getServerParameter('HTTP_HOST'));
        $this->assertSame('Symfony BrowserKit', $client->getServerParameter('HTTP_USER_AGENT'));
        $this->assertSame('testvalue', $client->getServerParameter('testkey', 'testvalue'));
    }

    public function testSetServerParameter()
    {
        $client = $this->getBrowser();

        $this->assertSame('', $client->getServerParameter('HTTP_HOST'));
        $this->assertSame('Symfony BrowserKit', $client->getServerParameter('HTTP_USER_AGENT'));

        $client->setServerParameter('HTTP_HOST', 'testhost');
        $this->assertSame('testhost', $client->getServerParameter('HTTP_HOST'));

        $client->setServerParameter('HTTP_USER_AGENT', 'testua');
        $this->assertSame('testua', $client->getServerParameter('HTTP_USER_AGENT'));
    }

    public function testSetServerParameterInRequest()
    {
        $client = $this->getBrowser();

        $this->assertSame('', $client->getServerParameter('HTTP_HOST'));
        $this->assertSame('Symfony BrowserKit', $client->getServerParameter('HTTP_USER_AGENT'));

        $client->request('GET', 'https://www.example.com/https/www.example.com', [], [], [
            'HTTP_HOST' => 'testhost',
            'HTTP_USER_AGENT' => 'testua',
            'HTTPS' => false,
            'NEW_SERVER_KEY' => 'new-server-key-value',
        ]);

        $this->assertSame('', $client->getServerParameter('HTTP_HOST'));
        $this->assertSame('Symfony BrowserKit', $client->getServerParameter('HTTP_USER_AGENT'));

        $this->assertSame('https://www.example.com/https/www.example.com', $client->getRequest()->getUri());

        $server = $client->getRequest()->getServer();

        $this->assertArrayHasKey('HTTP_USER_AGENT', $server);
        $this->assertSame('testua', $server['HTTP_USER_AGENT']);

        $this->assertArrayHasKey('HTTP_HOST', $server);
        $this->assertSame('testhost', $server['HTTP_HOST']);

        $this->assertArrayHasKey('NEW_SERVER_KEY', $server);
        $this->assertSame('new-server-key-value', $server['NEW_SERVER_KEY']);

        $this->assertArrayHasKey('HTTPS', $server);
        $this->assertTrue($server['HTTPS']);
    }

    public function testRequestWithRelativeUri()
    {
        $client = $this->getBrowser();

        $client->request('GET', '/', [], [], [
            'HTTP_HOST' => 'testhost',
            'HTTPS' => true,
        ]);
        $this->assertSame('https://testhost/', $client->getRequest()->getUri());

        $client->request('GET', 'https://www.example.com/', [], [], [
            'HTTP_HOST' => 'testhost',
            'HTTPS' => false,
        ]);
        $this->assertSame('https://www.example.com/', $client->getRequest()->getUri());
    }

    public function testInternalRequest()
    {
        $client = $this->getBrowser();

        $client->request('GET', 'https://www.example.com/https/www.example.com', [], [], [
            'HTTP_HOST' => 'testhost',
            'HTTP_USER_AGENT' => 'testua',
            'HTTPS' => false,
            'NEW_SERVER_KEY' => 'new-server-key-value',
        ]);

        $this->assertInstanceOf(Request::class, $client->getInternalRequest());
    }

    public function testInternalRequestNull()
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('The "request()" method must be called before "Symfony\\Component\\BrowserKit\\AbstractBrowser::getInternalRequest()".');

        $client = $this->getBrowser();
        $this->assertNull($client->getInternalRequest());
    }
}
