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

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group time-sensitive
 */
class ResponseTest extends ResponseTestCase
{
    /**
     * @group legacy
     */
    public function testCreate()
    {
        $response = Response::create('foo', 301, ['Foo' => 'bar']);

        self::assertInstanceOf(Response::class, $response);
        self::assertEquals(301, $response->getStatusCode());
        self::assertEquals('bar', $response->headers->get('foo'));
    }

    public function testToString()
    {
        $response = new Response();
        $response = explode("\r\n", $response);
        self::assertEquals('HTTP/1.0 200 OK', $response[0]);
        self::assertEquals('Cache-Control: no-cache, private', $response[1]);
    }

    public function testClone()
    {
        $response = new Response();
        $responseClone = clone $response;
        self::assertEquals($response, $responseClone);
    }

    public function testSendHeaders()
    {
        $response = new Response();
        $headers = $response->sendHeaders();
        self::assertObjectHasAttribute('headers', $headers);
        self::assertObjectHasAttribute('content', $headers);
        self::assertObjectHasAttribute('version', $headers);
        self::assertObjectHasAttribute('statusCode', $headers);
        self::assertObjectHasAttribute('statusText', $headers);
        self::assertObjectHasAttribute('charset', $headers);
    }

    public function testSend()
    {
        $response = new Response();
        $responseSend = $response->send();
        self::assertObjectHasAttribute('headers', $responseSend);
        self::assertObjectHasAttribute('content', $responseSend);
        self::assertObjectHasAttribute('version', $responseSend);
        self::assertObjectHasAttribute('statusCode', $responseSend);
        self::assertObjectHasAttribute('statusText', $responseSend);
        self::assertObjectHasAttribute('charset', $responseSend);
    }

    public function testGetCharset()
    {
        $response = new Response();
        $charsetOrigin = 'UTF-8';
        $response->setCharset($charsetOrigin);
        $charset = $response->getCharset();
        self::assertEquals($charsetOrigin, $charset);
    }

    public function testIsCacheable()
    {
        $response = new Response();
        self::assertFalse($response->isCacheable());
    }

    public function testIsCacheableWithErrorCode()
    {
        $response = new Response('', 500);
        self::assertFalse($response->isCacheable());
    }

    public function testIsCacheableWithNoStoreDirective()
    {
        $response = new Response();
        $response->headers->set('cache-control', 'private');
        self::assertFalse($response->isCacheable());
    }

    public function testIsCacheableWithSetTtl()
    {
        $response = new Response();
        $response->setTtl(10);
        self::assertTrue($response->isCacheable());
    }

    public function testMustRevalidate()
    {
        $response = new Response();
        self::assertFalse($response->mustRevalidate());
    }

    public function testMustRevalidateWithMustRevalidateCacheControlHeader()
    {
        $response = new Response();
        $response->headers->set('cache-control', 'must-revalidate');

        self::assertTrue($response->mustRevalidate());
    }

    public function testMustRevalidateWithProxyRevalidateCacheControlHeader()
    {
        $response = new Response();
        $response->headers->set('cache-control', 'proxy-revalidate');

        self::assertTrue($response->mustRevalidate());
    }

    public function testSetNotModified()
    {
        $response = new Response('foo');
        $modified = $response->setNotModified();
        self::assertObjectHasAttribute('headers', $modified);
        self::assertObjectHasAttribute('content', $modified);
        self::assertObjectHasAttribute('version', $modified);
        self::assertObjectHasAttribute('statusCode', $modified);
        self::assertObjectHasAttribute('statusText', $modified);
        self::assertObjectHasAttribute('charset', $modified);
        self::assertEquals(304, $modified->getStatusCode());

        ob_start();
        $modified->sendContent();
        $string = ob_get_clean();
        self::assertEmpty($string);
    }

    public function testIsSuccessful()
    {
        $response = new Response();
        self::assertTrue($response->isSuccessful());
    }

    public function testIsNotModified()
    {
        $response = new Response();
        $modified = $response->isNotModified(new Request());
        self::assertFalse($modified);
    }

    public function testIsNotModifiedNotSafe()
    {
        $request = Request::create('/homepage', 'POST');

        $response = new Response();
        self::assertFalse($response->isNotModified($request));
    }

    public function testIsNotModifiedLastModified()
    {
        $before = 'Sun, 25 Aug 2013 18:32:31 GMT';
        $modified = 'Sun, 25 Aug 2013 18:33:31 GMT';
        $after = 'Sun, 25 Aug 2013 19:33:31 GMT';

        $request = new Request();
        $request->headers->set('If-Modified-Since', $modified);

        $response = new Response();

        $response->headers->set('Last-Modified', $modified);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('Last-Modified', $before);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('Last-Modified', $after);
        self::assertFalse($response->isNotModified($request));

        $response->headers->set('Last-Modified', '');
        self::assertFalse($response->isNotModified($request));
    }

    public function testIsNotModifiedEtag()
    {
        $etagOne = 'randomly_generated_etag';
        $etagTwo = 'randomly_generated_etag_2';

        $request = new Request();
        $request->headers->set('If-None-Match', sprintf('%s, %s, %s', $etagOne, $etagTwo, 'etagThree'));

        $response = new Response();

        $response->headers->set('ETag', $etagOne);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', $etagTwo);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', '');
        self::assertFalse($response->isNotModified($request));

        // Test wildcard
        $request = new Request();
        $request->headers->set('If-None-Match', '*');

        $response->headers->set('ETag', $etagOne);
        self::assertTrue($response->isNotModified($request));
    }

    public function testIsNotModifiedWeakEtag()
    {
        $etag = 'randomly_generated_etag';
        $weakEtag = 'W/randomly_generated_etag';

        $request = new Request();
        $request->headers->set('If-None-Match', $etag);
        $response = new Response();

        $response->headers->set('ETag', $etag);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', $weakEtag);
        self::assertTrue($response->isNotModified($request));

        $request->headers->set('If-None-Match', $weakEtag);
        $response = new Response();

        $response->headers->set('ETag', $etag);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', $weakEtag);
        self::assertTrue($response->isNotModified($request));
    }

    public function testIsNotModifiedLastModifiedAndEtag()
    {
        $before = 'Sun, 25 Aug 2013 18:32:31 GMT';
        $modified = 'Sun, 25 Aug 2013 18:33:31 GMT';
        $after = 'Sun, 25 Aug 2013 19:33:31 GMT';
        $etag = 'randomly_generated_etag';

        $request = new Request();
        $request->headers->set('If-None-Match', sprintf('%s, %s', $etag, 'etagThree'));
        $request->headers->set('If-Modified-Since', $modified);

        $response = new Response();

        $response->headers->set('ETag', $etag);
        $response->headers->set('Last-Modified', $after);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', 'non-existent-etag');
        $response->headers->set('Last-Modified', $before);
        self::assertFalse($response->isNotModified($request));

        $response->headers->set('ETag', $etag);
        $response->headers->set('Last-Modified', $modified);
        self::assertTrue($response->isNotModified($request));
    }

    public function testIsNotModifiedIfModifiedSinceAndEtagWithoutLastModified()
    {
        $modified = 'Sun, 25 Aug 2013 18:33:31 GMT';
        $etag = 'randomly_generated_etag';

        $request = new Request();
        $request->headers->set('If-None-Match', sprintf('%s, %s', $etag, 'etagThree'));
        $request->headers->set('If-Modified-Since', $modified);

        $response = new Response();

        $response->headers->set('ETag', $etag);
        self::assertTrue($response->isNotModified($request));

        $response->headers->set('ETag', 'non-existent-etag');
        self::assertFalse($response->isNotModified($request));
    }

    public function testIfNoneMatchWithoutETag()
    {
        $request = new Request();
        $request->headers->set('If-None-Match', 'randomly_generated_etag');

        self::assertFalse((new Response())->isNotModified($request));

        // Test wildcard
        $request = new Request();
        $request->headers->set('If-None-Match', '*');

        self::assertFalse((new Response())->isNotModified($request));
    }

    public function testIsValidateable()
    {
        $response = new Response('', 200, ['Last-Modified' => $this->createDateTimeOneHourAgo()->format(\DATE_RFC2822)]);
        self::assertTrue($response->isValidateable(), '->isValidateable() returns true if Last-Modified is present');

        $response = new Response('', 200, ['ETag' => '"12345"']);
        self::assertTrue($response->isValidateable(), '->isValidateable() returns true if ETag is present');

        $response = new Response();
        self::assertFalse($response->isValidateable(), '->isValidateable() returns false when no validator is present');
    }

    public function testGetDate()
    {
        $oneHourAgo = $this->createDateTimeOneHourAgo();
        $response = new Response('', 200, ['Date' => $oneHourAgo->format(\DATE_RFC2822)]);
        $date = $response->getDate();
        self::assertEquals($oneHourAgo->getTimestamp(), $date->getTimestamp(), '->getDate() returns the Date header if present');

        $response = new Response();
        $date = $response->getDate();
        self::assertEquals(time(), $date->getTimestamp(), '->getDate() returns the current Date if no Date header present');

        $response = new Response('', 200, ['Date' => $this->createDateTimeOneHourAgo()->format(\DATE_RFC2822)]);
        $now = $this->createDateTimeNow();
        $response->headers->set('Date', $now->format(\DATE_RFC2822));
        $date = $response->getDate();
        self::assertEquals($now->getTimestamp(), $date->getTimestamp(), '->getDate() returns the date when the header has been modified');

        $response = new Response('', 200);
        $now = $this->createDateTimeNow();
        $response->headers->remove('Date');
        $date = $response->getDate();
        self::assertEquals($now->getTimestamp(), $date->getTimestamp(), '->getDate() returns the current Date when the header has previously been removed');
    }

    public function testGetMaxAge()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 's-maxage=600, max-age=0');
        self::assertEquals(600, $response->getMaxAge(), '->getMaxAge() uses s-maxage cache control directive when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=600');
        self::assertEquals(600, $response->getMaxAge(), '->getMaxAge() falls back to max-age when no s-maxage directive present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(\DATE_RFC2822));
        self::assertEquals(3600, $response->getMaxAge(), '->getMaxAge() falls back to Expires when no max-age or s-maxage directive present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Expires', -1);
        self::assertLessThanOrEqual(time() - 2 * 86400, $response->getExpires()->format('U'));

        $response = new Response();
        self::assertNull($response->getMaxAge(), '->getMaxAge() returns null if no freshness information available');
    }

    public function testSetSharedMaxAge()
    {
        $response = new Response();
        $response->setSharedMaxAge(20);

        $cacheControl = $response->headers->get('Cache-Control');
        self::assertEquals('public, s-maxage=20', $cacheControl);
    }

    public function testIsPrivate()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->setPrivate();
        self::assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        self::assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');

        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=100');
        $response->setPrivate();
        self::assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        self::assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');
        self::assertFalse($response->headers->hasCacheControlDirective('public'), '->isPrivate() removes the public Cache-Control directive');
    }

    public function testExpire()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->expire();
        self::assertEquals(100, $response->headers->get('Age'), '->expire() sets the Age to max-age when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100, s-maxage=500');
        $response->expire();
        self::assertEquals(500, $response->headers->get('Age'), '->expire() sets the Age to s-maxage when both max-age and s-maxage are present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=5, s-maxage=500');
        $response->headers->set('Age', '1000');
        $response->expire();
        self::assertEquals(1000, $response->headers->get('Age'), '->expire() does nothing when the response is already stale/expired');

        $response = new Response();
        $response->expire();
        self::assertFalse($response->headers->has('Age'), '->expire() does nothing when the response does not include freshness information');

        $response = new Response();
        $response->headers->set('Expires', -1);
        $response->expire();
        self::assertNull($response->headers->get('Age'), '->expire() does not set the Age when the response is expired');

        $response = new Response();
        $response->headers->set('Expires', date(\DATE_RFC2822, time() + 600));
        $response->expire();
        self::assertNull($response->headers->get('Expires'), '->expire() removes the Expires header when the response is fresh');
    }

    public function testNullExpireHeader()
    {
        $response = new Response(null, 200, ['Expires' => null]);
        self::assertNull($response->getExpires());
    }

    public function testGetTtl()
    {
        $response = new Response();
        self::assertNull($response->getTtl(), '->getTtl() returns null when no Expires or Cache-Control headers are present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(\DATE_RFC2822));
        self::assertEquals(3600, $response->getTtl(), '->getTtl() uses the Expires header when no max-age is present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourAgo()->format(\DATE_RFC2822));
        self::assertLessThan(0, $response->getTtl(), '->getTtl() returns negative values when Expires is in past');

        $response = new Response();
        $response->headers->set('Expires', $response->getDate()->format(\DATE_RFC2822));
        $response->headers->set('Age', 0);
        self::assertSame(0, $response->getTtl(), '->getTtl() correctly handles zero');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=60');
        self::assertEquals(60, $response->getTtl(), '->getTtl() uses Cache-Control max-age when present');
    }

    public function testSetClientTtl()
    {
        $response = new Response();
        $response->setClientTtl(10);

        self::assertEquals($response->getMaxAge(), $response->getAge() + 10);
    }

    public function testGetSetProtocolVersion()
    {
        $response = new Response();

        self::assertEquals('1.0', $response->getProtocolVersion());

        $response->setProtocolVersion('1.1');

        self::assertEquals('1.1', $response->getProtocolVersion());
    }

    public function testGetVary()
    {
        $response = new Response();
        self::assertEquals([], $response->getVary(), '->getVary() returns an empty array if no Vary header is present');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language');
        self::assertEquals(['Accept-Language'], $response->getVary(), '->getVary() parses a single header name value');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language User-Agent    X-Foo');
        self::assertEquals(['Accept-Language', 'User-Agent', 'X-Foo'], $response->getVary(), '->getVary() parses multiple header name values separated by spaces');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language,User-Agent,    X-Foo');
        self::assertEquals(['Accept-Language', 'User-Agent', 'X-Foo'], $response->getVary(), '->getVary() parses multiple header name values separated by commas');

        $vary = ['Accept-Language', 'User-Agent', 'X-foo'];

        $response = new Response();
        $response->headers->set('Vary', $vary);
        self::assertEquals($vary, $response->getVary(), '->getVary() parses multiple header name values in arrays');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language, User-Agent, X-foo');
        self::assertEquals($vary, $response->getVary(), '->getVary() parses multiple header name values in arrays');
    }

    public function testSetVary()
    {
        $response = new Response();
        $response->setVary('Accept-Language');
        self::assertEquals(['Accept-Language'], $response->getVary());

        $response->setVary('Accept-Language, User-Agent');
        self::assertEquals(['Accept-Language', 'User-Agent'], $response->getVary(), '->setVary() replace the vary header by default');

        $response->setVary('X-Foo', false);
        self::assertEquals(['Accept-Language', 'User-Agent', 'X-Foo'], $response->getVary(), '->setVary() doesn\'t wipe out earlier Vary headers if replace is set to false');
    }

    public function testDefaultContentType()
    {
        $response = new Response('foo');
        $response->prepare(new Request());

        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testContentTypeCharset()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/css');

        // force fixContentType() to be called
        $response->prepare(new Request());

        self::assertEquals('text/css; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testPrepareDoesNothingIfContentTypeIsSet()
    {
        $response = new Response('foo');
        $response->headers->set('Content-Type', 'text/plain');

        $response->prepare(new Request());

        self::assertEquals('text/plain; charset=UTF-8', $response->headers->get('content-type'));
    }

    public function testPrepareDoesNothingIfRequestFormatIsNotDefined()
    {
        $response = new Response('foo');

        $response->prepare(new Request());

        self::assertEquals('text/html; charset=UTF-8', $response->headers->get('content-type'));
    }

    /**
     * Same URL cannot produce different Content-Type based on the value of the Accept header,
     * unless explicitly stated in the response object.
     */
    public function testPrepareDoesNotSetContentTypeBasedOnRequestAcceptHeader()
    {
        $response = new Response('foo');
        $request = Request::create('/');
        $request->headers->set('Accept', 'application/json');
        $response->prepare($request);

        self::assertSame('text/html; charset=UTF-8', $response->headers->get('content-type'));
    }

    public function testPrepareSetContentType()
    {
        $response = new Response('foo');
        $request = Request::create('/');
        $request->setRequestFormat('json');

        $response->prepare($request);

        self::assertEquals('application/json', $response->headers->get('content-type'));
    }

    public function testPrepareRemovesContentForHeadRequests()
    {
        $response = new Response('foo');
        $request = Request::create('/', 'HEAD');

        $length = 12345;
        $response->headers->set('Content-Length', $length);
        $response->prepare($request);

        self::assertEquals('', $response->getContent());
        self::assertEquals($length, $response->headers->get('Content-Length'), 'Content-Length should be as if it was GET; see RFC2616 14.13');
    }

    public function testPrepareRemovesContentForInformationalResponse()
    {
        $response = new Response('foo');
        $request = Request::create('/');

        $response->setContent('content');
        $response->setStatusCode(101);
        $response->prepare($request);
        self::assertEquals('', $response->getContent());
        self::assertFalse($response->headers->has('Content-Type'));

        $response->setContent('content');
        $response->setStatusCode(304);
        $response->prepare($request);
        self::assertEquals('', $response->getContent());
        self::assertFalse($response->headers->has('Content-Type'));
        self::assertFalse($response->headers->has('Content-Length'));
    }

    public function testPrepareRemovesContentLength()
    {
        $response = new Response('foo');
        $request = Request::create('/');

        $response->headers->set('Content-Length', 12345);
        $response->prepare($request);
        self::assertEquals(12345, $response->headers->get('Content-Length'));

        $response->headers->set('Transfer-Encoding', 'chunked');
        $response->prepare($request);
        self::assertFalse($response->headers->has('Content-Length'));
    }

    public function testPrepareSetsPragmaOnHttp10Only()
    {
        $request = Request::create('/', 'GET');
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.0');

        $response = new Response('foo');
        $response->prepare($request);
        self::assertEquals('no-cache', $response->headers->get('pragma'));
        self::assertEquals('-1', $response->headers->get('expires'));

        $response = new Response('foo');
        $response->headers->remove('cache-control');
        $response->prepare($request);
        self::assertFalse($response->headers->has('pragma'));
        self::assertFalse($response->headers->has('expires'));

        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');
        $response = new Response('foo');
        $response->prepare($request);
        self::assertFalse($response->headers->has('pragma'));
        self::assertFalse($response->headers->has('expires'));
    }

    public function testPrepareSetsCookiesSecure()
    {
        $cookie = Cookie::create('foo', 'bar');

        $response = new Response('foo');
        $response->headers->setCookie($cookie);

        $request = Request::create('/', 'GET');
        $response->prepare($request);

        self::assertFalse($cookie->isSecure());

        $request = Request::create('https://localhost/', 'GET');
        $response->prepare($request);

        self::assertTrue($cookie->isSecure());
    }

    public function testSetCache()
    {
        $response = new Response();
        // ['etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public']
        try {
            $response->setCache(['wrong option' => 'value']);
            self::fail('->setCache() throws an InvalidArgumentException if an option is not supported');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->setCache() throws an InvalidArgumentException if an option is not supported');
            self::assertStringContainsString('"wrong option"', $e->getMessage());
        }

        $options = ['etag' => '"whatever"'];
        $response->setCache($options);
        self::assertEquals('"whatever"', $response->getEtag());

        $now = $this->createDateTimeNow();
        $options = ['last_modified' => $now];
        $response->setCache($options);
        self::assertEquals($now->getTimestamp(), $response->getLastModified()->getTimestamp());

        $options = ['max_age' => 100];
        $response->setCache($options);
        self::assertEquals(100, $response->getMaxAge());

        $options = ['s_maxage' => 200];
        $response->setCache($options);
        self::assertEquals(200, $response->getMaxAge());

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));

        $response->setCache(['public' => true]);
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));

        $response->setCache(['public' => false]);
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $response->setCache(['private' => true]);
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $response->setCache(['private' => false]);
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));

        $response->setCache(['immutable' => true]);
        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));

        $response->setCache(['immutable' => false]);
        self::assertFalse($response->headers->hasCacheControlDirective('immutable'));

        $directives = ['proxy_revalidate', 'must_revalidate', 'no_cache', 'no_store', 'no_transform'];
        foreach ($directives as $directive) {
            $response->setCache([$directive => true]);

            self::assertTrue($response->headers->hasCacheControlDirective(str_replace('_', '-', $directive)));
        }

        foreach ($directives as $directive) {
            $response->setCache([$directive => false]);

            self::assertFalse($response->headers->hasCacheControlDirective(str_replace('_', '-', $directive)));
        }

        $response = new DefaultResponse();

        $options = ['etag' => '"whatever"'];
        $response->setCache($options);
        self::assertSame($response->getEtag(), '"whatever"');
    }

    public function testSendContent()
    {
        $response = new Response('test response rendering', 200);

        ob_start();
        $response->sendContent();
        $string = ob_get_clean();
        self::assertStringContainsString('test response rendering', $string);
    }

    public function testSetPublic()
    {
        $response = new Response();
        $response->setPublic();

        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertFalse($response->headers->hasCacheControlDirective('private'));
    }

    public function testSetImmutable()
    {
        $response = new Response();
        $response->setImmutable();

        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));
    }

    public function testIsImmutable()
    {
        $response = new Response();
        $response->setImmutable();

        self::assertTrue($response->isImmutable());
    }

    public function testSetDate()
    {
        $response = new Response();
        $response->setDate(\DateTime::createFromFormat(\DateTime::ATOM, '2013-01-26T09:21:56+0100', new \DateTimeZone('Europe/Berlin')));

        self::assertEquals('2013-01-26T08:21:56+00:00', $response->getDate()->format(\DateTime::ATOM));
    }

    public function testSetDateWithImmutable()
    {
        $response = new Response();
        $response->setDate(\DateTimeImmutable::createFromFormat(\DateTime::ATOM, '2013-01-26T09:21:56+0100', new \DateTimeZone('Europe/Berlin')));

        self::assertEquals('2013-01-26T08:21:56+00:00', $response->getDate()->format(\DateTime::ATOM));
    }

    public function testSetExpires()
    {
        $response = new Response();
        $response->setExpires(null);

        self::assertNull($response->getExpires(), '->setExpires() remove the header when passed null');

        $now = $this->createDateTimeNow();
        $response->setExpires($now);

        self::assertEquals($response->getExpires()->getTimestamp(), $now->getTimestamp());
    }

    public function testSetExpiresWithImmutable()
    {
        $response = new Response();

        $now = $this->createDateTimeImmutableNow();
        $response->setExpires($now);

        self::assertEquals($response->getExpires()->getTimestamp(), $now->getTimestamp());
    }

    public function testSetLastModified()
    {
        $response = new Response();
        $response->setLastModified($this->createDateTimeNow());
        self::assertNotNull($response->getLastModified());

        $response->setLastModified(null);
        self::assertNull($response->getLastModified());
    }

    public function testSetLastModifiedWithImmutable()
    {
        $response = new Response();
        $response->setLastModified($this->createDateTimeImmutableNow());
        self::assertNotNull($response->getLastModified());

        $response->setLastModified(null);
        self::assertNull($response->getLastModified());
    }

    public function testIsInvalid()
    {
        $response = new Response();

        try {
            $response->setStatusCode(99);
            self::fail();
        } catch (\InvalidArgumentException $e) {
            self::assertTrue($response->isInvalid());
        }

        try {
            $response->setStatusCode(650);
            self::fail();
        } catch (\InvalidArgumentException $e) {
            self::assertTrue($response->isInvalid());
        }

        $response = new Response('', 200);
        self::assertFalse($response->isInvalid());
    }

    /**
     * @dataProvider getStatusCodeFixtures
     */
    public function testSetStatusCode($code, $text, $expectedText)
    {
        $response = new Response();

        $response->setStatusCode($code, $text);

        $statusText = new \ReflectionProperty($response, 'statusText');
        $statusText->setAccessible(true);

        self::assertEquals($expectedText, $statusText->getValue($response));
    }

    public function getStatusCodeFixtures()
    {
        return [
            ['200', null, 'OK'],
            ['200', false, ''],
            ['200', 'foo', 'foo'],
            ['199', null, 'unknown status'],
            ['199', false, ''],
            ['199', 'foo', 'foo'],
        ];
    }

    public function testIsInformational()
    {
        $response = new Response('', 100);
        self::assertTrue($response->isInformational());

        $response = new Response('', 200);
        self::assertFalse($response->isInformational());
    }

    public function testIsRedirectRedirection()
    {
        foreach ([301, 302, 303, 307] as $code) {
            $response = new Response('', $code);
            self::assertTrue($response->isRedirection());
            self::assertTrue($response->isRedirect());
        }

        $response = new Response('', 304);
        self::assertTrue($response->isRedirection());
        self::assertFalse($response->isRedirect());

        $response = new Response('', 200);
        self::assertFalse($response->isRedirection());
        self::assertFalse($response->isRedirect());

        $response = new Response('', 404);
        self::assertFalse($response->isRedirection());
        self::assertFalse($response->isRedirect());

        $response = new Response('', 301, ['Location' => '/good-uri']);
        self::assertFalse($response->isRedirect('/bad-uri'));
        self::assertTrue($response->isRedirect('/good-uri'));
    }

    public function testIsNotFound()
    {
        $response = new Response('', 404);
        self::assertTrue($response->isNotFound());

        $response = new Response('', 200);
        self::assertFalse($response->isNotFound());
    }

    public function testIsEmpty()
    {
        foreach ([204, 304] as $code) {
            $response = new Response('', $code);
            self::assertTrue($response->isEmpty());
        }

        $response = new Response('', 200);
        self::assertFalse($response->isEmpty());
    }

    public function testIsForbidden()
    {
        $response = new Response('', 403);
        self::assertTrue($response->isForbidden());

        $response = new Response('', 200);
        self::assertFalse($response->isForbidden());
    }

    public function testIsOk()
    {
        $response = new Response('', 200);
        self::assertTrue($response->isOk());

        $response = new Response('', 404);
        self::assertFalse($response->isOk());
    }

    public function testIsServerOrClientError()
    {
        $response = new Response('', 404);
        self::assertTrue($response->isClientError());
        self::assertFalse($response->isServerError());

        $response = new Response('', 500);
        self::assertFalse($response->isClientError());
        self::assertTrue($response->isServerError());
    }

    public function testHasVary()
    {
        $response = new Response();
        self::assertFalse($response->hasVary());

        $response->setVary('User-Agent');
        self::assertTrue($response->hasVary());
    }

    public function testSetEtag()
    {
        $response = new Response('', 200, ['ETag' => '"12345"']);
        $response->setEtag();

        self::assertNull($response->headers->get('Etag'), '->setEtag() removes Etags when call with null');
    }

    /**
     * @dataProvider validContentProvider
     */
    public function testSetContent($content)
    {
        $response = new Response();
        $response->setContent($content);
        self::assertEquals((string) $content, $response->getContent());
    }

    public function testSettersAreChainable()
    {
        $response = new Response();

        $setters = [
            'setProtocolVersion' => '1.0',
            'setCharset' => 'UTF-8',
            'setPublic' => null,
            'setPrivate' => null,
            'setDate' => $this->createDateTimeNow(),
            'expire' => null,
            'setMaxAge' => 1,
            'setSharedMaxAge' => 1,
            'setTtl' => 1,
            'setClientTtl' => 1,
        ];

        foreach ($setters as $setter => $arg) {
            self::assertEquals($response, $response->{$setter}($arg));
        }
    }

    public function testNoDeprecationsAreTriggered()
    {
        new DefaultResponse();
        self::createMock(Response::class);

        // we just need to ensure that subclasses of Response can be created without any deprecations
        // being triggered if the subclass does not override any final methods
        self::addToAssertionCount(1);
    }

    public function validContentProvider()
    {
        return [
            'obj' => [new StringableObject()],
            'string' => ['Foo'],
            'int' => [2],
        ];
    }

    protected function createDateTimeOneHourAgo()
    {
        return $this->createDateTimeNow()->sub(new \DateInterval('PT1H'));
    }

    protected function createDateTimeOneHourLater()
    {
        return $this->createDateTimeNow()->add(new \DateInterval('PT1H'));
    }

    protected function createDateTimeNow()
    {
        $date = new \DateTime();

        return $date->setTimestamp(time());
    }

    protected function createDateTimeImmutableNow()
    {
        $date = new \DateTimeImmutable();

        return $date->setTimestamp(time());
    }

    protected function provideResponse()
    {
        return new Response();
    }

    /**
     * @see http://github.com/zendframework/zend-diactoros for the canonical source repository
     *
     * @author Fábio Pacheco
     * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
     * @license https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
     */
    public function ianaCodesReasonPhrasesProvider()
    {
        // XML taken from https://www.iana.org/assignments/http-status-codes/http-status-codes.xml
        // (might not be up-to-date for older Symfony versions)
        $ianaHttpStatusCodes = new \DOMDocument();
        $ianaHttpStatusCodes->load(__DIR__.'/Fixtures/xml/http-status-codes.xml');
        if (!$ianaHttpStatusCodes->relaxNGValidate(__DIR__.'/schema/http-status-codes.rng')) {
            self::fail('Invalid IANA\'s HTTP status code list.');
        }

        $ianaCodesReasonPhrases = [];

        $xpath = new \DOMXPath($ianaHttpStatusCodes);
        $xpath->registerNamespace('ns', 'http://www.iana.org/assignments');

        $records = $xpath->query('//ns:record');
        foreach ($records as $record) {
            $value = $xpath->query('.//ns:value', $record)->item(0)->nodeValue;
            $description = $xpath->query('.//ns:description', $record)->item(0)->nodeValue;

            if (\in_array($description, ['Unassigned', '(Unused)'], true)) {
                continue;
            }

            if (preg_match('/^([0-9]+)\s*\-\s*([0-9]+)$/', $value, $matches)) {
                for ($value = $matches[1]; $value <= $matches[2]; ++$value) {
                    $ianaCodesReasonPhrases[] = [$value, $description];
                }
            } else {
                $ianaCodesReasonPhrases[] = [$value, $description];
            }
        }

        return $ianaCodesReasonPhrases;
    }

    /**
     * @dataProvider ianaCodesReasonPhrasesProvider
     */
    public function testReasonPhraseDefaultsAgainstIana($code, $reasonPhrase)
    {
        self::assertEquals($reasonPhrase, Response::$statusTexts[$code]);
    }

    public function testSetContentSafe()
    {
        $response = new Response();

        self::assertFalse($response->headers->has('Preference-Applied'));
        self::assertFalse($response->headers->has('Vary'));

        $response->setContentSafe();

        self::assertSame('safe', $response->headers->get('Preference-Applied'));
        self::assertSame('Prefer', $response->headers->get('Vary'));

        $response->setContentSafe(false);

        self::assertFalse($response->headers->has('Preference-Applied'));
        self::assertSame('Prefer', $response->headers->get('Vary'));
    }
}

class StringableObject
{
    public function __toString(): string
    {
        return 'Foo';
    }
}

class DefaultResponse extends Response
{
}
