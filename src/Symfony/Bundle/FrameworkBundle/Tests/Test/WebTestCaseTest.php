<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Cookie as HttpFoundationCookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebTestCaseTest extends TestCase
{
    public function testAssertResponseIsSuccessful()
    {
        $this->getResponseTester(new Response())->assertResponseIsSuccessful();
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response is successful.\nHTTP/1.0 404 Not Found");
        $this->getResponseTester(new Response('', 404))->assertResponseIsSuccessful();
    }

    public function testAssertResponseStatusCodeSame()
    {
        $this->getResponseTester(new Response())->assertResponseStatusCodeSame(200);
        $this->getResponseTester(new Response('', 404))->assertResponseStatusCodeSame(404);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response status code is 200.\nHTTP/1.0 404 Not Found");
        $this->getResponseTester(new Response('', 404))->assertResponseStatusCodeSame(200);
    }

    public function testAssertResponseRedirects()
    {
        $this->getResponseTester(new Response('', 301))->assertResponseRedirects();
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response is redirected.\nHTTP/1.0 200 OK");
        $this->getResponseTester(new Response())->assertResponseRedirects();
    }

    public function testAssertResponseRedirectsWithLocation()
    {
        $this->getResponseTester(new Response('', 301, ['Location' => 'https://example.com/']))->assertResponseRedirects('https://example.com/');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('is redirected and has header "Location" with value "https://example.com/".');
        $this->getResponseTester(new Response('', 301))->assertResponseRedirects('https://example.com/');
    }

    public function testAssertResponseRedirectsWithStatusCode()
    {
        $this->getResponseTester(new Response('', 302))->assertResponseRedirects(null, 302);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('is redirected and status code is 301.');
        $this->getResponseTester(new Response('', 302))->assertResponseRedirects(null, 301);
    }

    public function testAssertResponseRedirectsWithLocationAndStatusCode()
    {
        $this->getResponseTester(new Response('', 302, ['Location' => 'https://example.com/']))->assertResponseRedirects('https://example.com/', 302);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('#(:?\( )?is redirected and has header "Location" with value "https://example\.com/" (:?\) )?and status code is 301\.#');
        $this->getResponseTester(new Response('', 302))->assertResponseRedirects('https://example.com/', 301);
    }

    public function testAssertResponseFormat()
    {
        $this->getResponseTester(new Response('', 200, ['Content-Type' => 'application/vnd.myformat']))->assertResponseFormatSame('custom');
        $this->getResponseTester(new Response('', 200, ['Content-Type' => 'application/ld+json']))->assertResponseFormatSame('jsonld');
        $this->getResponseTester(new Response())->assertResponseFormatSame(null);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response format is jsonld.\nHTTP/1.0 200 OK");
        $this->getResponseTester(new Response())->assertResponseFormatSame('jsonld');
    }

    public function testAssertResponseHasHeader()
    {
        $this->getResponseTester(new Response())->assertResponseHasHeader('Date');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "X-Date".');
        $this->getResponseTester(new Response())->assertResponseHasHeader('X-Date');
    }

    public function testAssertResponseNotHasHeader()
    {
        $this->getResponseTester(new Response())->assertResponseNotHasHeader('X-Date');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have header "Date".');
        $this->getResponseTester(new Response())->assertResponseNotHasHeader('Date');
    }

    public function testAssertResponseHeaderSame()
    {
        $this->getResponseTester(new Response())->assertResponseHeaderSame('Cache-Control', 'no-cache, private');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "Cache-Control" with value "public".');
        $this->getResponseTester(new Response())->assertResponseHeaderSame('Cache-Control', 'public');
    }

    public function testAssertResponseHeaderNotSame()
    {
        $this->getResponseTester(new Response())->assertResponseHeaderNotSame('Cache-Control', 'public');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have header "Cache-Control" with value "no-cache, private".');
        $this->getResponseTester(new Response())->assertResponseHeaderNotSame('Cache-Control', 'no-cache, private');
    }

    public function testAssertResponseHasCookie()
    {
        $response = new Response();
        $response->headers->setCookie(HttpFoundationCookie::create('foo', 'bar'));

        $this->getResponseTester($response)->assertResponseHasCookie('foo');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has cookie "bar".');
        $this->getResponseTester($response)->assertResponseHasCookie('bar');
    }

    public function testAssertResponseNotHasCookie()
    {
        $response = new Response();
        $response->headers->setCookie(HttpFoundationCookie::create('foo', 'bar'));

        $this->getResponseTester($response)->assertResponseNotHasCookie('bar');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have cookie "foo".');
        $this->getResponseTester($response)->assertResponseNotHasCookie('foo');
    }

    public function testAssertResponseCookieValueSame()
    {
        $response = new Response();
        $response->headers->setCookie(HttpFoundationCookie::create('foo', 'bar'));

        $this->getResponseTester($response)->assertResponseCookieValueSame('foo', 'bar');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('has cookie "bar" and has cookie "bar" with value "bar".');
        $this->getResponseTester($response)->assertResponseCookieValueSame('bar', 'bar');
    }

    public function testAssertBrowserHasCookie()
    {
        $this->getClientTester()->assertBrowserHasCookie('foo', '/path');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Browser has cookie "bar".');
        $this->getClientTester()->assertBrowserHasCookie('bar');
    }

    public function testAssertBrowserNotHasCookie()
    {
        $this->getClientTester()->assertBrowserNotHasCookie('bar');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Browser does not have cookie "foo" with path "/path".');
        $this->getClientTester()->assertBrowserNotHasCookie('foo', '/path');
    }

    public function testAssertBrowserCookieValueSame()
    {
        $this->getClientTester()->assertBrowserCookieValueSame('foo', 'bar', false, '/path');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('has cookie "foo" with path "/path" and has cookie "foo" with path "/path" with value "babar".');
        $this->getClientTester()->assertBrowserCookieValueSame('foo', 'babar', false, '/path');
    }

    public function testAssertSelectorExists()
    {
        $this->getCrawlerTester(new Crawler('<html><body><h1>'))->assertSelectorExists('body > h1');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "body > h1".');
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertSelectorExists('body > h1');
    }

    public function testAssertSelectorNotExists()
    {
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertSelectorNotExists('body > h1');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not match selector "body > h1".');
        $this->getCrawlerTester(new Crawler('<html><body><h1>'))->assertSelectorNotExists('body > h1');
    }

    public function testAssertSelectorCount()
    {
        $this->getCrawlerTester(new Crawler('<html><body><p>Hello</p></body></html>'))->assertSelectorCount(1, 'p');
        $this->getCrawlerTester(new Crawler('<html><body><p>Hello</p><p>Foo</p></body></html>'))->assertSelectorCount(2, 'p');
        $this->getCrawlerTester(new Crawler('<html><body><h1>This is not a paragraph.</h1></body></html>'))->assertSelectorCount(0, 'p');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Crawler selector "p" was expected to be found 0 time(s) but was found 1 time(s).');
        $this->getCrawlerTester(new Crawler('<html><body><p>Hello</p></body></html>'))->assertSelectorCount(0, 'p');
    }

    public function testAssertSelectorTextNotContains()
    {
        $this->getCrawlerTester(new Crawler('<html><body><h1>Foo'))->assertSelectorTextNotContains('body > h1', 'Bar');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "body > h1" and the text "Foo" of the node matching selector "body > h1" does not contain "Foo".');
        $this->getCrawlerTester(new Crawler('<html><body><h1>Foo'))->assertSelectorTextNotContains('body > h1', 'Foo');
    }

    public function testAssertPageTitleSame()
    {
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertPageTitleSame('Foo');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "title" and has a node matching selector "title" with content "Bar".');
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertPageTitleSame('Bar');
    }

    public function testAssertPageTitleContains()
    {
        $this->getCrawlerTester(new Crawler('<html><head><title>Foobar'))->assertPageTitleContains('Foo');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "title" and the text "Foo" of the node matching selector "title" contains "Bar".');
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertPageTitleContains('Bar');
    }

    public function testAssertInputValueSame()
    {
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="text" name="username" value="Fabien">'))->assertInputValueSame('username', 'Fabien');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="password"]" and has a node matching selector "input[name="password"]" with attribute "value" of value "pa$$".');
        $this->getCrawlerTester(new Crawler('<html><head><title>Foo'))->assertInputValueSame('password', 'pa$$');
    }

    public function testAssertInputValueNotSame()
    {
        $this->getCrawlerTester(new Crawler('<html><body><input type="text" name="username" value="Helene">'))->assertInputValueNotSame('username', 'Fabien');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="password"]" and does not have a node matching selector "input[name="password"]" with attribute "value" of value "pa$$".');
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="text" name="password" value="pa$$">'))->assertInputValueNotSame('password', 'pa$$');
    }

    public function testAssertCheckboxChecked()
    {
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="checkbox" name="rememberMe" checked>'))->assertCheckboxChecked('rememberMe');
        $this->getCrawlerTester(new Crawler('<!DOCTYPE html><body><form><input type="checkbox" name="rememberMe" checked>'))->assertCheckboxChecked('rememberMe');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="rememberMe"]:checked".');
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="checkbox" name="rememberMe">'))->assertCheckboxChecked('rememberMe');
    }

    public function testAssertCheckboxNotChecked()
    {
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="checkbox" name="rememberMe">'))->assertCheckboxNotChecked('rememberMe');
        $this->getCrawlerTester(new Crawler('<!DOCTYPE html><body><form><input type="checkbox" name="rememberMe">'))->assertCheckboxNotChecked('rememberMe');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not match selector "input[name="rememberMe"]:checked".');
        $this->getCrawlerTester(new Crawler('<html><body><form><input type="checkbox" name="rememberMe" checked>'))->assertCheckboxNotChecked('rememberMe');
    }

    public function testAssertFormValue()
    {
        $this->getCrawlerTester(new Crawler('<html><body><form id="form"><input type="text" name="username" value="Fabien">', 'http://localhost'))->assertFormValue('#form', 'username', 'Fabien');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that two strings are identical.');
        $this->getCrawlerTester(new Crawler('<html><body><form id="form"><input type="text" name="username" value="Fabien">', 'http://localhost'))->assertFormValue('#form', 'username', 'Jane');
    }

    public function testAssertNoFormValue()
    {
        $this->getCrawlerTester(new Crawler('<html><body><form id="form"><input type="checkbox" name="rememberMe">', 'http://localhost'))->assertNoFormValue('#form', 'rememberMe');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "rememberMe" has a value in form "#form".');
        $this->getCrawlerTester(new Crawler('<html><body><form id="form"><input type="checkbox" name="rememberMe" checked>', 'http://localhost'))->assertNoFormValue('#form', 'rememberMe');
    }

    public function testAssertRequestAttributeValueSame()
    {
        $this->getRequestTester()->assertRequestAttributeValueSame('foo', 'bar');
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Request has attribute "foo" with value "baz".');
        $this->getRequestTester()->assertRequestAttributeValueSame('foo', 'baz');
    }

    public function testAssertRouteSame()
    {
        $this->getRequestTester()->assertRouteSame('homepage', ['foo' => 'bar']);
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Request has attribute "_route" with value "articles".');
        $this->getRequestTester()->assertRouteSame('articles');
    }

    public function testExceptionOnServerError()
    {
        try {
            $this->getResponseTester(new Response('', 500, ['X-Debug-Exception' => 'An exception has occurred', 'X-Debug-Exception-File' => '%2Fsrv%2Ftest.php:12']))->assertResponseIsSuccessful();
        } catch (ExpectationFailedException $exception) {
            $this->assertSame('An exception has occurred', $exception->getPrevious()->getMessage());
            $this->assertSame('/srv/test.php', $exception->getPrevious()->getFile());
            $this->assertSame(12, $exception->getPrevious()->getLine());
        }
    }

    private function getResponseTester(Response $response): WebTestCase
    {
        $client = $this->createMock(KernelBrowser::class);
        $client->expects($this->any())->method('getResponse')->willReturn($response);

        $request = new Request();
        $request->setFormat('custom', ['application/vnd.myformat']);
        $client->expects($this->any())->method('getRequest')->willReturn($request);

        return $this->getTester($client);
    }

    private function getCrawlerTester(Crawler $crawler): WebTestCase
    {
        $client = $this->createMock(KernelBrowser::class);
        $client->expects($this->any())->method('getCrawler')->willReturn($crawler);

        return $this->getTester($client);
    }

    private function getClientTester(): WebTestCase
    {
        $client = $this->createMock(KernelBrowser::class);
        $jar = new CookieJar();
        $jar->set(new Cookie('foo', 'bar', null, '/path', 'example.com'));
        $client->expects($this->any())->method('getCookieJar')->willReturn($jar);

        return $this->getTester($client);
    }

    private function getRequestTester(): WebTestCase
    {
        $client = $this->createMock(KernelBrowser::class);
        $request = new Request();
        $request->attributes->set('foo', 'bar');
        $request->attributes->set('_route', 'homepage');
        $client->expects($this->any())->method('getRequest')->willReturn($request);

        return $this->getTester($client);
    }

    private function getTester(KernelBrowser $client): WebTestCase
    {
        $tester = new class() extends WebTestCase {
            use WebTestAssertionsTrait {
                getClient as public;
            }
        };

        $tester::getClient($client);

        return $tester;
    }
}
