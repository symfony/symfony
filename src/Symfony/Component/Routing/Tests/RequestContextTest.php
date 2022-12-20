<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;

class RequestContextTest extends TestCase
{
    public function testConstruct()
    {
        $requestContext = new RequestContext(
            'foo',
            'post',
            'foo.bar',
            'HTTPS',
            8080,
            444,
            '/baz',
            'bar=foobar'
        );

        self::assertEquals('foo', $requestContext->getBaseUrl());
        self::assertEquals('POST', $requestContext->getMethod());
        self::assertEquals('foo.bar', $requestContext->getHost());
        self::assertEquals('https', $requestContext->getScheme());
        self::assertSame(8080, $requestContext->getHttpPort());
        self::assertSame(444, $requestContext->getHttpsPort());
        self::assertEquals('/baz', $requestContext->getPathInfo());
        self::assertEquals('bar=foobar', $requestContext->getQueryString());
    }

    public function testFromUriWithBaseUrl()
    {
        $requestContext = RequestContext::fromUri('https://test.com:444/index.php');

        self::assertSame('GET', $requestContext->getMethod());
        self::assertSame('https', $requestContext->getScheme());
        self::assertSame('test.com', $requestContext->getHost());
        self::assertSame('/index.php', $requestContext->getBaseUrl());
        self::assertSame('/', $requestContext->getPathInfo());
        self::assertSame(80, $requestContext->getHttpPort());
        self::assertSame(444, $requestContext->getHttpsPort());
    }

    public function testFromUriWithTrailingSlash()
    {
        $requestContext = RequestContext::fromUri('http://test.com:8080/');

        self::assertSame('http', $requestContext->getScheme());
        self::assertSame('test.com', $requestContext->getHost());
        self::assertSame(8080, $requestContext->getHttpPort());
        self::assertSame(443, $requestContext->getHttpsPort());
        self::assertSame('', $requestContext->getBaseUrl());
        self::assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromUriWithoutTrailingSlash()
    {
        $requestContext = RequestContext::fromUri('https://test.com');

        self::assertSame('https', $requestContext->getScheme());
        self::assertSame('test.com', $requestContext->getHost());
        self::assertSame('', $requestContext->getBaseUrl());
        self::assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromUriBeingEmpty()
    {
        $requestContext = RequestContext::fromUri('');

        self::assertSame('http', $requestContext->getScheme());
        self::assertSame('localhost', $requestContext->getHost());
        self::assertSame('', $requestContext->getBaseUrl());
        self::assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromRequest()
    {
        $request = Request::create('https://test.com:444/foo?bar=baz');
        $requestContext = new RequestContext();
        $requestContext->setHttpPort(123);
        $requestContext->fromRequest($request);

        self::assertEquals('', $requestContext->getBaseUrl());
        self::assertEquals('GET', $requestContext->getMethod());
        self::assertEquals('test.com', $requestContext->getHost());
        self::assertEquals('https', $requestContext->getScheme());
        self::assertEquals('/foo', $requestContext->getPathInfo());
        self::assertEquals('bar=baz', $requestContext->getQueryString());
        self::assertSame(123, $requestContext->getHttpPort());
        self::assertSame(444, $requestContext->getHttpsPort());

        $request = Request::create('http://test.com:8080/foo?bar=baz');
        $requestContext = new RequestContext();
        $requestContext->setHttpsPort(567);
        $requestContext->fromRequest($request);

        self::assertSame(8080, $requestContext->getHttpPort());
        self::assertSame(567, $requestContext->getHttpsPort());
    }

    public function testGetParameters()
    {
        $requestContext = new RequestContext();
        self::assertEquals([], $requestContext->getParameters());

        $requestContext->setParameters(['foo' => 'bar']);
        self::assertEquals(['foo' => 'bar'], $requestContext->getParameters());
    }

    public function testHasParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameters(['foo' => 'bar']);

        self::assertTrue($requestContext->hasParameter('foo'));
        self::assertFalse($requestContext->hasParameter('baz'));
    }

    public function testGetParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameters(['foo' => 'bar']);

        self::assertEquals('bar', $requestContext->getParameter('foo'));
        self::assertNull($requestContext->getParameter('baz'));
    }

    public function testSetParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameter('foo', 'bar');

        self::assertEquals('bar', $requestContext->getParameter('foo'));
    }

    public function testMethod()
    {
        $requestContext = new RequestContext();
        $requestContext->setMethod('post');

        self::assertSame('POST', $requestContext->getMethod());
    }

    public function testScheme()
    {
        $requestContext = new RequestContext();
        $requestContext->setScheme('HTTPS');

        self::assertSame('https', $requestContext->getScheme());
    }

    public function testHost()
    {
        $requestContext = new RequestContext();
        $requestContext->setHost('eXampLe.com');

        self::assertSame('example.com', $requestContext->getHost());
    }

    public function testQueryString()
    {
        $requestContext = new RequestContext();
        $requestContext->setQueryString(null);

        self::assertSame('', $requestContext->getQueryString());
    }

    public function testPort()
    {
        $requestContext = new RequestContext();
        $requestContext->setHttpPort('123');
        $requestContext->setHttpsPort('456');

        self::assertSame(123, $requestContext->getHttpPort());
        self::assertSame(456, $requestContext->getHttpsPort());
    }

    public function testFluentInterface()
    {
        $requestContext = new RequestContext();

        self::assertSame($requestContext, $requestContext->setBaseUrl('/app.php'));
        self::assertSame($requestContext, $requestContext->setPathInfo('/index'));
        self::assertSame($requestContext, $requestContext->setMethod('POST'));
        self::assertSame($requestContext, $requestContext->setScheme('https'));
        self::assertSame($requestContext, $requestContext->setHost('example.com'));
        self::assertSame($requestContext, $requestContext->setQueryString('foo=bar'));
        self::assertSame($requestContext, $requestContext->setHttpPort(80));
        self::assertSame($requestContext, $requestContext->setHttpsPort(443));
        self::assertSame($requestContext, $requestContext->setParameters([]));
        self::assertSame($requestContext, $requestContext->setParameter('foo', 'bar'));
    }
}
