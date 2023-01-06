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

        $this->assertEquals('foo', $requestContext->getBaseUrl());
        $this->assertEquals('POST', $requestContext->getMethod());
        $this->assertEquals('foo.bar', $requestContext->getHost());
        $this->assertEquals('https', $requestContext->getScheme());
        $this->assertSame(8080, $requestContext->getHttpPort());
        $this->assertSame(444, $requestContext->getHttpsPort());
        $this->assertEquals('/baz', $requestContext->getPathInfo());
        $this->assertEquals('bar=foobar', $requestContext->getQueryString());
    }

    public function testFromUriWithBaseUrl()
    {
        $requestContext = RequestContext::fromUri('https://test.com:444/index.php');

        $this->assertSame('GET', $requestContext->getMethod());
        $this->assertSame('https', $requestContext->getScheme());
        $this->assertSame('test.com', $requestContext->getHost());
        $this->assertSame('/index.php', $requestContext->getBaseUrl());
        $this->assertSame('/', $requestContext->getPathInfo());
        $this->assertSame(80, $requestContext->getHttpPort());
        $this->assertSame(444, $requestContext->getHttpsPort());
    }

    public function testFromUriWithTrailingSlash()
    {
        $requestContext = RequestContext::fromUri('http://test.com:8080/');

        $this->assertSame('http', $requestContext->getScheme());
        $this->assertSame('test.com', $requestContext->getHost());
        $this->assertSame(8080, $requestContext->getHttpPort());
        $this->assertSame(443, $requestContext->getHttpsPort());
        $this->assertSame('', $requestContext->getBaseUrl());
        $this->assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromUriWithoutTrailingSlash()
    {
        $requestContext = RequestContext::fromUri('https://test.com');

        $this->assertSame('https', $requestContext->getScheme());
        $this->assertSame('test.com', $requestContext->getHost());
        $this->assertSame('', $requestContext->getBaseUrl());
        $this->assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromUriBeingEmpty()
    {
        $requestContext = RequestContext::fromUri('');

        $this->assertSame('http', $requestContext->getScheme());
        $this->assertSame('localhost', $requestContext->getHost());
        $this->assertSame('', $requestContext->getBaseUrl());
        $this->assertSame('/', $requestContext->getPathInfo());
    }

    public function testFromRequest()
    {
        $request = Request::create('https://test.com:444/foo?bar=baz');
        $requestContext = new RequestContext();
        $requestContext->setHttpPort(123);
        $requestContext->fromRequest($request);

        $this->assertEquals('', $requestContext->getBaseUrl());
        $this->assertEquals('GET', $requestContext->getMethod());
        $this->assertEquals('test.com', $requestContext->getHost());
        $this->assertEquals('https', $requestContext->getScheme());
        $this->assertEquals('/foo', $requestContext->getPathInfo());
        $this->assertEquals('bar=baz', $requestContext->getQueryString());
        $this->assertSame(123, $requestContext->getHttpPort());
        $this->assertSame(444, $requestContext->getHttpsPort());

        $request = Request::create('http://test.com:8080/foo?bar=baz');
        $requestContext = new RequestContext();
        $requestContext->setHttpsPort(567);
        $requestContext->fromRequest($request);

        $this->assertSame(8080, $requestContext->getHttpPort());
        $this->assertSame(567, $requestContext->getHttpsPort());
    }

    public function testGetParameters()
    {
        $requestContext = new RequestContext();
        $this->assertEquals([], $requestContext->getParameters());

        $requestContext->setParameters(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $requestContext->getParameters());
    }

    public function testHasParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameters(['foo' => 'bar']);

        $this->assertTrue($requestContext->hasParameter('foo'));
        $this->assertFalse($requestContext->hasParameter('baz'));
    }

    public function testGetParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameters(['foo' => 'bar']);

        $this->assertEquals('bar', $requestContext->getParameter('foo'));
        $this->assertNull($requestContext->getParameter('baz'));
    }

    public function testSetParameter()
    {
        $requestContext = new RequestContext();
        $requestContext->setParameter('foo', 'bar');

        $this->assertEquals('bar', $requestContext->getParameter('foo'));
    }

    public function testMethod()
    {
        $requestContext = new RequestContext();
        $requestContext->setMethod('post');

        $this->assertSame('POST', $requestContext->getMethod());
    }

    public function testScheme()
    {
        $requestContext = new RequestContext();
        $requestContext->setScheme('HTTPS');

        $this->assertSame('https', $requestContext->getScheme());
    }

    public function testHost()
    {
        $requestContext = new RequestContext();
        $requestContext->setHost('eXampLe.com');

        $this->assertSame('example.com', $requestContext->getHost());
    }

    public function testQueryString()
    {
        $requestContext = new RequestContext();
        $requestContext->setQueryString(null);

        $this->assertSame('', $requestContext->getQueryString());
    }

    public function testPort()
    {
        $requestContext = new RequestContext();
        $requestContext->setHttpPort('123');
        $requestContext->setHttpsPort('456');

        $this->assertSame(123, $requestContext->getHttpPort());
        $this->assertSame(456, $requestContext->getHttpsPort());
    }

    public function testFluentInterface()
    {
        $requestContext = new RequestContext();

        $this->assertSame($requestContext, $requestContext->setBaseUrl('/app.php'));
        $this->assertSame($requestContext, $requestContext->setPathInfo('/index'));
        $this->assertSame($requestContext, $requestContext->setMethod('POST'));
        $this->assertSame($requestContext, $requestContext->setScheme('https'));
        $this->assertSame($requestContext, $requestContext->setHost('example.com'));
        $this->assertSame($requestContext, $requestContext->setQueryString('foo=bar'));
        $this->assertSame($requestContext, $requestContext->setHttpPort(80));
        $this->assertSame($requestContext, $requestContext->setHttpsPort(443));
        $this->assertSame($requestContext, $requestContext->setParameters([]));
        $this->assertSame($requestContext, $requestContext->setParameter('foo', 'bar'));
    }
}
