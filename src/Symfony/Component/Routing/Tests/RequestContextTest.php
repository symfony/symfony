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

use PHPUnit\Framework\Attributes\TestWith;
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
            'bar=foobar',
            [
                'foo' => 'bar',
            ]
        );

        $this->assertEquals('foo', $requestContext->getBaseUrl());
        $this->assertEquals('POST', $requestContext->getMethod());
        $this->assertEquals('foo.bar', $requestContext->getHost());
        $this->assertEquals('https', $requestContext->getScheme());
        $this->assertSame(8080, $requestContext->getHttpPort());
        $this->assertSame(444, $requestContext->getHttpsPort());
        $this->assertEquals('/baz', $requestContext->getPathInfo());
        $this->assertEquals('bar=foobar', $requestContext->getQueryString());
        $this->assertSame(['foo' => 'bar'], $requestContext->getParameters());
    }

    public function testConstructParametersBcLayer()
    {
        $requestContext = new class extends RequestContext {
            public function __construct()
            {
                $this->setParameters(['foo' => 'bar']);
                parent::__construct();
            }
        };

        $this->assertSame(['foo' => 'bar'], $requestContext->getParameters());
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

    #[TestWith(['http://foo.com\\bar'])]
    #[TestWith(['\\\\foo.com/bar'])]
    #[TestWith(["a\rb"])]
    #[TestWith(["a\nb"])]
    #[TestWith(["a\tb"])]
    #[TestWith(["\u0000foo"])]
    #[TestWith(["foo\u0000"])]
    #[TestWith([' foo'])]
    #[TestWith(['foo '])]
    #[TestWith([':'])]
    public function testFromBadUri(string $uri)
    {
        $context = RequestContext::fromUri($uri);

        $this->assertSame('http', $context->getScheme());
        $this->assertSame('localhost', $context->getHost());
        $this->assertSame('', $context->getBaseUrl());
        $this->assertSame('/', $context->getPathInfo());
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

    public function testRunWithAppliesValuesAndRestoresThem()
    {
        $context = self::createFullContext();

        $returned = $context->runWith(function () use ($context) {
            $this->assertSame('acme.example.com', $context->getHost());
            $this->assertSame('https', $context->getScheme());
            $this->assertSame('/other.php', $context->getBaseUrl());

            return 'from the callback';
        }, baseUrl: '/other.php', host: 'acme.example.com', scheme: 'https');

        $this->assertSame('from the callback', $returned);
        self::assertIsFullContext($context);
    }

    public function testRunWithLeavesNullArgumentsUnchanged()
    {
        $context = self::createFullContext();

        $context->runWith(function () use ($context) {
            $this->assertSame('acme.example.com', $context->getHost());
            $this->assertSame('http', $context->getScheme());
            $this->assertSame('POST', $context->getMethod());
            $this->assertSame('foo=bar', $context->getQueryString());
            $this->assertSame(['_locale' => 'en'], $context->getParameters());
        }, host: 'acme.example.com');

        self::assertIsFullContext($context);
    }

    public function testRunWithAcceptsEmptyValues()
    {
        $context = self::createFullContext();

        $context->runWith(function () use ($context) {
            $this->assertSame('', $context->getQueryString());
            $this->assertSame([], $context->getParameters());
        }, queryString: '', parameters: []);

        self::assertIsFullContext($context);
    }

    public function testRunWithRestoresOnException()
    {
        $context = self::createFullContext();

        try {
            $context->runWith(static fn () => throw new \RuntimeException('Something went wrong.'), host: 'acme.example.com');
            $this->fail('The exception should have bubbled up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Something went wrong.', $e->getMessage());
        }

        self::assertIsFullContext($context);
    }

    public function testRunWithNests()
    {
        $context = self::createFullContext();

        $context->runWith(function () use ($context) {
            $this->assertSame('a.example.com', $context->getHost());

            $context->runWith(function () use ($context) {
                $this->assertSame('b.example.com', $context->getHost());
            }, host: 'b.example.com');

            $this->assertSame('a.example.com', $context->getHost());
        }, host: 'a.example.com');

        self::assertIsFullContext($context);
    }

    public function testRunWithDoesNotKeepChangesMadeByTheCallback()
    {
        $context = self::createFullContext();

        $context->runWith(static function () use ($context) {
            $context->setParameter('_locale', 'fr');
        }, host: 'acme.example.com');

        self::assertIsFullContext($context);
    }

    private static function createFullContext(): RequestContext
    {
        return new RequestContext('/app.php', 'POST', 'localhost', 'http', 8080, 8443, '/index', 'foo=bar', ['_locale' => 'en']);
    }

    private static function assertIsFullContext(RequestContext $context): void
    {
        self::assertSame('/app.php', $context->getBaseUrl());
        self::assertSame('POST', $context->getMethod());
        self::assertSame('localhost', $context->getHost());
        self::assertSame('http', $context->getScheme());
        self::assertSame(8080, $context->getHttpPort());
        self::assertSame(8443, $context->getHttpsPort());
        self::assertSame('/index', $context->getPathInfo());
        self::assertSame('foo=bar', $context->getQueryString());
        self::assertSame(['_locale' => 'en'], $context->getParameters());
    }
}
