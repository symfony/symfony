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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class HttpUtilsTest extends TestCase
{
    public function testCreateRedirectResponseWithPath(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), '/foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foobar'));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testCreateRedirectResponseWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator());
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/');

        $this->assertTrue($response->isRedirect('http://symfony.com/'));
    }

    public function testCreateRedirectResponseWithDomainRegexp(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://symfony\.com$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://symfony.com/blog');

        $this->assertTrue($response->isRedirect('http://symfony.com/blog'));
    }

    public function testCreateRedirectResponseWithRequestsDomain(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), 'http://localhost/blog');

        $this->assertTrue($response->isRedirect('http://localhost/blog'));
    }

    #[DataProvider('validRequestDomainUrls')]
    public function testCreateRedirectResponse(?string $domainRegexp, string $path, string $expectedRedirectUri): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, $domainRegexp);
        $response = $utils->createRedirectResponse($this->getRequest(), $path);

        $this->assertTrue($response->isRedirect($expectedRedirectUri));
        $this->assertEquals(302, $response->getStatusCode());
    }

    public static function validRequestDomainUrls()
    {
        return [
            '/foobar' => [
                null,
                '/foobar',
                'http://localhost/foobar',
            ],
            'http://symfony.com/ without domain regex' => [
                null,
                'http://symfony.com/',
                'http://symfony.com/',
            ],
            'http://localhost/blog with #^https?://symfony\.com$#i' => [
                '#^https?://symfony\.com$#i',
                'http://symfony.com/blog',
                'http://symfony.com/blog',
            ],
            'http://localhost/blog with #^https?://%s$#i' => [
                '#^https?://%s$#i',
                'http://localhost/blog',
                'http://localhost/blog',
            ],
            'custom scheme' => [
                null,
                'android-app://com.google.android.gm/',
                'android-app://com.google.android.gm/',
            ],
            'custom scheme with all URL components' => [
                null,
                'android-app://foo:bar@www.example.com:8080/software/index.html?lite=true#section1',
                'android-app://foo:bar@www.example.com:8080/software/index.html?lite=true#section1',
            ],
        ];
    }

    #[DataProvider('badRequestDomainUrls')]
    public function testCreateRedirectResponseWithBadRequestsDomain($url): void
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
            ['http:///foo'],
        ];
    }

    public function testCreateRedirectResponseWithProtocolRelativeTarget(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator(), null, '#^https?://%s$#i');
        $response = $utils->createRedirectResponse($this->getRequest(), '//evil.com/do-bad-things');

        $this->assertTrue($response->isRedirect('http://localhost//evil.com/do-bad-things'), 'Protocol-relative redirection should not be supported for security reasons');
    }

    public function testCreateRedirectResponseWithRouteName(): void
    {
        $utils = new HttpUtils($urlGenerator = $this->createStub(UrlGeneratorInterface::class));

        $urlGenerator
            ->method('generate')
            ->with('foobar', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/foo/bar')
        ;
        $urlGenerator
            ->method('getContext')
            ->willReturn(new RequestContext())
        ;

        $response = $utils->createRedirectResponse($this->getRequest(), 'foobar');

        $this->assertTrue($response->isRedirect('http://localhost/foo/bar'));
    }

    public function testCreateRequestWithPath(): void
    {
        $request = $this->getRequest();
        $request->server->set('Foo', 'bar');

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertEquals('GET', $subRequest->getMethod());
        $this->assertEquals('/foobar', $subRequest->getPathInfo());
        $this->assertEquals('bar', $subRequest->server->get('Foo'));
    }

    public function testCreateRequestWithRouteName(): void
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
            ->willReturn(new RequestContext())
        ;

        $subRequest = $utils->createRequest($this->getRequest(), 'foobar');

        $this->assertEquals('/foo/bar', $subRequest->getPathInfo());
    }

    public function testCreateRequestWithAbsoluteUrl(): void
    {
        $utils = new HttpUtils($this->createStub(UrlGeneratorInterface::class));
        $subRequest = $utils->createRequest($this->getRequest(), 'http://symfony.com/');

        $this->assertEquals('/', $subRequest->getPathInfo());
    }

    public function testCreateRequestPassesSessionToTheNewRequest(): void
    {
        $request = $this->getRequest();
        $request->setSession($session = new Session(new MockArraySessionStorage()));

        $utils = new HttpUtils($this->getUrlGenerator());
        $subRequest = $utils->createRequest($request, '/foobar');

        $this->assertSame($session, $subRequest->getSession());
    }

    #[DataProvider('provideSecurityRequestAttributes')]
    public function testCreateRequestPassesSecurityRequestAttributesToTheNewRequest($attribute): void
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

    public function testCreateRequestFromPathHandlesTrustedHeaders(): void
    {
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_PREFIX);

        $this->assertSame(
            'http://localhost/foo/',
            (new HttpUtils())->createRequest(Request::create('/', server: ['HTTP_X_FORWARDED_PREFIX' => '/foo']), '/')->getUri(),
        );
    }

    public function testCreateRequestFromRouteHandlesTrustedHeaders(): void
    {
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_PREFIX);

        $request = Request::create('/', server: ['HTTP_X_FORWARDED_PREFIX' => '/foo']);

        $urlGenerator = new UrlGenerator(
            $routeCollection = new RouteCollection(),
            (new RequestContext())->fromRequest($request),
        );
        $routeCollection->add('root', new Route('/'));

        $this->assertSame(
            'http://localhost/foo/',
            (new HttpUtils($urlGenerator))->createRequest($request, 'root')->getUri(),
        );
    }

    public function testCheckRequestPath(): void
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

    public function testCheckRequestPathWithUrlMatcherAndResourceNotFound(): void
    {
        $urlMatcher = $this->createStub(UrlMatcherInterface::class);
        $urlMatcher
            ->method('match')
            ->with('/')
            ->willThrowException(new ResourceNotFoundException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndMethodNotAllowed(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->createStub(RequestMatcherInterface::class);
        $urlMatcher
            ->method('matchRequest')
            ->with($request)
            ->willThrowException(new MethodNotAllowedException([]))
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByUrl(): void
    {
        $urlMatcher = $this->createStub(UrlMatcherInterface::class);
        $urlMatcher
            ->method('match')
            ->with('/foo/bar')
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($this->getRequest('/foo/bar'), 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherAndResourceFoundByRequest(): void
    {
        $request = $this->getRequest();
        $urlMatcher = $this->createStub(RequestMatcherInterface::class);
        $urlMatcher
            ->method('matchRequest')
            ->with($request)
            ->willReturn(['_route' => 'foobar'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckRequestPathWithUrlMatcherLoadingException(): void
    {
        $urlMatcher = $this->createStub(UrlMatcherInterface::class);
        $urlMatcher
            ->method('match')
            ->willThrowException(new \RuntimeException())
        ;

        $utils = new HttpUtils(null, $urlMatcher);

        $this->expectException(\RuntimeException::class);

        $utils->checkRequestPath($this->getRequest(), 'foobar');
    }

    public function testCheckRequestPathWithRequestAlreadyMatchedBefore(): void
    {
        $urlMatcher = $this->createMock(RequestMatcherInterface::class);
        $urlMatcher
            ->expects($this->never())
            ->method('matchRequest')
        ;

        $request = $this->getRequest();
        $request->attributes->set('_route', 'route_name');

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertTrue($utils->checkRequestPath($request, 'route_name'));
        $this->assertFalse($utils->checkRequestPath($request, 'foobar'));
    }

    public function testCheckPathWithoutRouteParam(): void
    {
        $urlMatcher = $this->createStub(UrlMatcherInterface::class);
        $urlMatcher
            ->method('match')
            ->willReturn(['_controller' => 'PathController'])
        ;

        $utils = new HttpUtils(null, $urlMatcher);
        $this->assertFalse($utils->checkRequestPath($this->getRequest(), 'path/index.html'));
    }

    public function testGenerateUriRemovesQueryString(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value'));
        $this->assertEquals('/foo/bar', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testGenerateUriPreservesFragment(): void
    {
        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar?param=value#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));

        $utils = new HttpUtils($this->getUrlGenerator('/foo/bar#fragment'));
        $this->assertEquals('/foo/bar#fragment', $utils->generateUri(new Request(), 'route_name'));
    }

    public function testUrlGeneratorIsRequiredToGenerateUrl(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('You must provide a UrlGeneratorInterface instance to be able to use routes.');
        (new HttpUtils())->generateUri(new Request(), 'route_name');
    }

    private function getUrlGenerator($generatedUrl = '/foo/bar')
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator
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
