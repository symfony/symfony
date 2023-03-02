<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class HttpUtilsTest extends TestCase
{
    public function testCreateRedirectResponseWithPath()
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testCreateRedirectResponseWithAbsoluteUrl()
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');

        $this->assertTrue($response->isRedirect('http://symfony.com/'));
    }

    public function testCreateRedirectResponseWithDomainRegexp()
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://symfony\.com$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/blog');

        $this->assertTrue($response->isRedirect('http://symfony.com/blog'));
    }

    public function testCreateRedirectResponseWithRequestsDomain()
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://localhost/blog');

        $this->assertTrue($response->isRedirect('http://localhost/blog'));
    }

    /**
     * @dataProvider badRequestDomainUrls
     */
    public function testCreateRedirectResponseWithBadRequestsDomain($url)
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), $url);

        $this->assertTrue($response->isRedirect('http://localhost/'));
    }

    public static function badRequestDomainUrls()
    {
        return [
            ['http://pirate.net/foo'],
            ['http:\\\\pirate.net/foo'],
            ['http:/\\pirate.net/foo'],
            ['http:\\/pirate.net/foo'],
            ['http://////pirate.net/foo'],
        ];
    }

    public function testCreateRedirectResponseWithProtocolRelativeTarget()
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), '//evil.com/do-bad-things');

        $this->assertTrue($response->isRedirect('http://localhost//evil.com/do-bad-things'), 'Protocol-relative redirection should not be supported for security reasons');
    }

    public function testCreateRedirectResponseWithRouteName()
    {
        $utils = new HttpUtils($urlGenerator = $this->createMock(UrlGeneratorInterface::class));

        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->with('foobar', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/foo/bar')
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($this->createMock(RequestContext::class))
        ;

        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequestWithPath()
    {
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));
    }

    public function testCreateRequestWithRouteName()
    {
        $utils = new HttpUtils($urlGenerator = $this->createMock(UrlGeneratorInterface::class));

        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->willReturn('/foo/bar')
        ;
        $urlGenerator
            ->expects($this->any())
            ->method('getContext')
            ->willReturn($this->createMock(RequestContext::class))
        ;

        $subRequest = $utils->createRequest($this->getRequest(), 'foobar');

        $this->assertEquals('/foo/bar', $subRequest->getPathInfo());
    }

    public function testCreateRequestWithAbsoluteUrl()
    {
        $utils = new HttpUtils($this->createMock(UrlGeneratorInterface::class));
        $subRequest = $utils->createRequest($this->getRequest(), 'http://symfony.com/');

        $this->assertEquals('/', $subRequest->getPathInfo());
    }

    public function testCreateRequestPassesSessionToTheNewRequest()
    {
        $request = $this->getRequest();
        $request->setSession($session = $this->createMock(SessionInterface::class));

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame($session, $subRequest->getSession());
    }

    /**
     * @dataProvider provideSecurityRequestAttributes
     */
    public function testCreateRequestPassesSecurityRequestAttributesToTheNewRequest($attribute)
    {
        $request = $this->getRequest();
        $request->attributes->set($attribute, 'foo');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame('foo', $subRequest->attributes->get($attribute));
    }

    public static function provideSecurityRequestAttributes()
    {
        return [
            [SecurityRequestAttributes::AUTHENTICATION_ERROR],
            [SecurityRequestAttributes::ACCESS_DENIED_ERROR],
            [SecurityRequestAttributes::LAST_USERNAME],
        ];
    }

    public function testCheckRequestPath()
    {
        $utils = new HttpUtils($this->getUrlGenerator());

        $this->assertTrue($utils->checkRequestPath($this->getRequest(), '/'));
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), '/foo'));
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo%20bar'), '/foo bar'));
        // Plus must not decoded to space
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo+bar'), '/foo+bar'));
        // Checking unicode
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/'.urlencode('вход')), '/вход'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceNotFound()
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/')
            ->willThrowException(new ResourceNotFoundException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndMethodNotAllowed()
    {
        $request = $this->getRequest();
        $urlMatcher = $this->createMock(RequestMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->willThrowException(new MethodNotAllowedException([]))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByUrl()
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->with('/foo/bar')
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByRequest()
    {
        $request = $this->getRequest();
        $urlMatcher = $this->createMock(RequestMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('matchRequest')
            ->with($request)
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherLoadingException()
    {
        $this->expectException(\RuntimeException::class);
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->willThrowException(new \RuntimeException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    public function testCheckPathWithoutRouteParam()
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher
            ->expects($this->any())
            ->method('match')
            ->willReturn(['_controller' => 'PathController'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'path/index.html'));
    }

    public function testGenerateUriRemovesQueryString()
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testGenerateUriPreservesFragment()
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testUrlGeneratorIsRequiredToGenerateUrl()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must provide a UrlGeneratorInterface instance to be able to use routes.');
        $utils = new HttpUtils();
        $utils->generateUri(new Request(), 'route_name');
    }

    private function getUrlGenerator($generatedUrl = '/foo/bar')
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->any())
            ->method('generate')
            ->willReturn($generatedUrl)
        ;

        return $urlGenerator;
    }

    private function getRequest($path = '/')
    {
        return Request::create($path, 'get');
    }
}
