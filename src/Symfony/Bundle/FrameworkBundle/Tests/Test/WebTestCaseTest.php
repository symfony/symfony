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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Tests\Test\Fixtures\Kernel;
use Symfony\Component\HttpFoundation\Request;

class WebTestCaseTest extends WebTestCase
{
    public function testAssertResponseIsSuccessful()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseIsSuccessful();

        $client->request('GET', '/404');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response is successful.\nHTTP/1.1 404 Not Found");

        $this->assertResponseIsSuccessful();
    }

    public function testAssertResponseStatusCodeSame()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseStatusCodeSame(200);

        $client->request('GET', '/404');

        $this->assertResponseStatusCodeSame(404);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response status code is 200.\nHTTP/1.1 404 Not Found");

        $this->assertResponseStatusCodeSame(200);
    }

    public function testAssertResponseRedirects()
    {
        $client = static::createClient();
        $client->request('GET', '/301');

        $this->assertResponseRedirects();

        $client->request('GET', '/200');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response is redirected.\nHTTP/1.1 200 OK");

        $this->assertResponseRedirects();
    }

    public function testAssertResponseRedirectsWithLocation()
    {
        $client = static::createClient();
        $client->request('GET', '/301');

        $this->assertResponseRedirects('https://example.com/');

        $client->request('GET', '/200');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('is redirected and has header "Location" with value "https://example.com/".');

        $this->assertResponseRedirects('https://example.com/');
    }

    public function testAssertResponseRedirectsWithStatusCode()
    {
        $client = static::createClient();
        $client->request('GET', '/302');

        $this->assertResponseRedirects(null, 302);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('is redirected and status code is 301.');

        $this->assertResponseRedirects(null, 301);
    }

    public function testAssertResponseRedirectsWithLocationAndStatusCode()
    {
        $client = static::createClient();
        $client->request('GET', '/302');

        $this->assertResponseRedirects('https://example.com/', 302);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('#(:?\( )?is redirected and has header "Location" with value "https://example\.com/" (:?\) )?and status code is 301\.#');

        $this->assertResponseRedirects('https://example.com/', 301);
    }

    public function testAssertResponseFormat()
    {
        $client = static::createClient();

        $request = new Request();
        $request->setFormat('custom', ['application/vnd.myformat']);

        $client->request('GET', '/custom-format');
        $this->assertResponseFormatSame('custom');

        $client->request('GET', '/jsonld-format');
        $this->assertResponseFormatSame('jsonld');

        $client->request('GET', '/no-format');
        $this->assertResponseFormatSame(null);

        $client->request('GET', '/no-format');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage("Failed asserting that the Response format is jsonld.\nHTTP/1.1 200 OK");

        $this->assertResponseFormatSame('jsonld');
    }

    public function testAssertResponseHasHeader()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseHasHeader('Date');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "X-Date".');

        $this->assertResponseHasHeader('X-Date');
    }

    public function testAssertResponseNotHasHeader()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseNotHasHeader('X-Date');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have header "Date".');

        $this->assertResponseNotHasHeader('Date');
    }

    public function testAssertResponseHeaderSame()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseHeaderSame('Cache-Control', 'no-cache, private');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has header "Cache-Control" with value "public".');

        $this->assertResponseHeaderSame('Cache-Control', 'public');
    }

    public function testAssertResponseHeaderNotSame()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseHeaderNotSame('Cache-Control', 'public');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have header "Cache-Control" with value "no-cache, private".');

        $this->assertResponseHeaderNotSame('Cache-Control', 'no-cache, private');
    }

    public function testAssertResponseHasCookie()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseHasCookie('foo');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response has cookie "bar".');

        $this->assertResponseHasCookie('bar');
    }

    public function testAssertResponseNotHasCookie()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseNotHasCookie('bar');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Response does not have cookie "foo".');

        $this->assertResponseNotHasCookie('foo');
    }

    public function testAssertResponseCookieValueSame()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertResponseCookieValueSame('foo', 'bar');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('has cookie "bar" and has cookie "bar" with value "bar".');

        $this->assertResponseCookieValueSame('bar', 'bar');
    }

    public function testAssertBrowserHasCookie()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertBrowserHasCookie('foo', '/path');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Browser has cookie "bar".');

        $this->assertBrowserHasCookie('bar');
    }

    public function testAssertBrowserNotHasCookie()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertBrowserNotHasCookie('bar');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Browser does not have cookie "foo" with path "/path".');

        $this->assertBrowserNotHasCookie('foo', '/path');
    }

    public function testAssertBrowserCookieValueSame()
    {
        $client = static::createClient();
        $client->request('GET', '/200');

        $this->assertBrowserCookieValueSame('foo', 'bar', false, '/path');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('has cookie "foo" with path "/path" and has cookie "foo" with path "/path" with value "babar".');

        $this->assertBrowserCookieValueSame('foo', 'babar', false, '/path');
    }

    public function testAssertSelectorExists()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><h1>')));

        $this->assertSelectorExists('body > h1');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "body > h1".');

        $this->assertSelectorExists('body > h1');
    }

    public function testAssertSelectorNotExists()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->assertSelectorNotExists('body > h1');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><h1>')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not match selector "body > h1".');

        $this->assertSelectorNotExists('body > h1');
    }

    public function testAssertSelectorTextNotContains()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><h1>Foo')));

        $this->assertSelectorTextNotContains('body > h1', 'Bar');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><h1>Foo')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "body > h1" and the text "Foo" of the node matching selector "body > h1" does not contain "Foo".');

        $this->assertSelectorTextNotContains('body > h1', 'Foo');
    }

    public function testAssertPageTitleSame()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->assertPageTitleSame('Foo');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "title" and has a node matching selector "title" with content "Bar".');

        $this->assertPageTitleSame('Bar');
    }

    public function testAssertPageTitleContains()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foobar')));

        $this->assertPageTitleContains('Foo');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "title" and the text "Foo" of the node matching selector "title" contains "Bar".');

        $this->assertPageTitleContains('Bar');
    }

    public function testAssertInputValueSame()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="text" name="username" value="Fabien">')));

        $this->assertInputValueSame('username', 'Fabien');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><head><title>Foo')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="password"]" and has a node matching selector "input[name="password"]" with attribute "value" of value "pa$$".');

        $this->assertInputValueSame('password', 'pa$$');
    }

    public function testAssertInputValueNotSame()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><input type="text" name="username" value="Helene">')));

        $this->assertInputValueNotSame('username', 'Fabien');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="text" name="password" value="pa$$">')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="password"]" and does not have a node matching selector "input[name="password"]" with attribute "value" of value "pa$$".');

        $this->assertInputValueNotSame('password', 'pa$$');
    }

    public function testAssertCheckboxChecked()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="checkbox" name="rememberMe" checked>')));

        $this->assertCheckboxChecked('rememberMe');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<!DOCTYPE html><body><form><input type="checkbox" name="rememberMe" checked>')));

        $this->assertCheckboxChecked('rememberMe');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="checkbox" name="rememberMe">')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('matches selector "input[name="rememberMe"]:checked".');

        $this->assertCheckboxChecked('rememberMe');
    }

    public function testAssertCheckboxNotChecked()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="checkbox" name="rememberMe">')));

        $this->assertCheckboxNotChecked('rememberMe');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<!DOCTYPE html><body><form><input type="checkbox" name="rememberMe">')));

        $this->assertCheckboxNotChecked('rememberMe');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form><input type="checkbox" name="rememberMe" checked>')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('does not match selector "input[name="rememberMe"]:checked".');

        $this->assertCheckboxNotChecked('rememberMe');
    }

    public function testAssertFormValue()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form id="form"><input type="text" name="username" value="Fabien">')));

        $this->assertFormValue('#form', 'username', 'Fabien');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form id="form"><input type="text" name="username" value="Fabien">')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that two strings are identical.');

        $this->assertFormValue('#form', 'username', 'Jane');
    }

    public function testAssertNoFormValue()
    {
        $client = static::createClient();
        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form id="form"><input type="checkbox" name="rememberMe">')));

        $this->assertNoFormValue('#form', 'rememberMe');

        $client->request('GET', sprintf('/crawler/%s', urlencode('<html><body><form id="form"><input type="checkbox" name="rememberMe" checked>')));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Field "rememberMe" has a value in form "#form".');

        $this->assertNoFormValue('#form', 'rememberMe');
    }

    public function testAssertRequestAttributeValueSame()
    {
        $client = static::createClient();
        $client->request('GET', '/request-attribute');

        $this->assertRequestAttributeValueSame('foo', 'bar');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Request has attribute "foo" with value "baz".');

        $this->assertRequestAttributeValueSame('foo', 'baz');
    }

    public function testAssertRouteSame()
    {
        $client = static::createClient();
        $client->request('GET', '/homepage/bar');

        $this->assertRouteSame('homepage', ['foo' => 'bar']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that the Request has attribute "_route" with value "articles".');

        $this->assertRouteSame('articles');
    }

    public function testExceptionOnServerError()
    {
        $client = static::createClient();
        $client->request('GET', '/500');

        try {
            $this->assertResponseIsSuccessful();
        } catch (ExpectationFailedException $exception) {
            $this->assertSame('An exception has occurred', $exception->getPrevious()->getMessage());
            $this->assertSame('/srv/test.php', $exception->getPrevious()->getFile());
            $this->assertSame(12, $exception->getPrevious()->getLine());
        }
    }

    protected static function getKernelClass()
    {
        return Kernel::class;
    }
}
