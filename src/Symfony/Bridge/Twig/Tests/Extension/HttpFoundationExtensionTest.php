<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\HttpFoundationExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class HttpFoundationExtensionTest extends TestCase
{
    #[DataProvider('getGenerateAbsoluteUrlData')]
    public function testGenerateAbsoluteUrl($expected, $path, $pathinfo)
    {
        $stack = new RequestStack();
        $stack->push(Request::create($pathinfo));
        $extension = new HttpFoundationExtension(new UrlHelper($stack));

        $this->assertEquals($expected, $extension->generateAbsoluteUrl($path));
    }

    public static function getGenerateAbsoluteUrlData()
    {
        return [
            ['http://localhost/foo.png', '/foo.png', '/foo/bar.html'],
            ['http://localhost/foo/foo.png', 'foo.png', '/foo/bar.html'],
            ['http://localhost/foo/foo.png', 'foo.png', '/foo/bar'],
            ['http://localhost/foo/bar/foo.png', 'foo.png', '/foo/bar/'],

            ['http://example.com/baz', 'http://example.com/baz', '/'],
            ['https://example.com/baz', 'https://example.com/baz', '/'],
            ['//example.com/baz', '//example.com/baz', '/'],

            ['http://localhost/foo/bar?baz', '?baz', '/foo/bar'],
            ['http://localhost/foo/bar?baz=1', '?baz=1', '/foo/bar?foo=1'],
            ['http://localhost/foo/baz?baz=1', 'baz?baz=1', '/foo/bar?foo=1'],

            ['http://localhost/foo/bar#baz', '#baz', '/foo/bar'],
            ['http://localhost/foo/bar?0#baz', '#baz', '/foo/bar?0'],
            ['http://localhost/foo/bar?baz=1#baz', '?baz=1#baz', '/foo/bar?foo=1'],
            ['http://localhost/foo/baz?baz=1#baz', 'baz?baz=1#baz', '/foo/bar?foo=1'],
        ];
    }

    #[DataProvider('getGenerateAbsoluteUrlRequestContextData')]
    public function testGenerateAbsoluteUrlWithRequestContext($path, $baseUrl, $host, $scheme, $httpPort, $httpsPort, $expected)
    {
        $requestContext = new RequestContext($baseUrl, 'GET', $host, $scheme, $httpPort, $httpsPort, $path);
        $extension = new HttpFoundationExtension(new UrlHelper(new RequestStack(), $requestContext));

        $this->assertEquals($expected, $extension->generateAbsoluteUrl($path));
    }

    #[DataProvider('getGenerateAbsoluteUrlRequestContextData')]
    public function testGenerateAbsoluteUrlWithoutRequestAndRequestContext($path, $baseUrl, $host, $scheme, $httpPort, $httpsPort, $expected)
    {
        $extension = new HttpFoundationExtension(new UrlHelper(new RequestStack()));

        $this->assertEquals($path, $extension->generateAbsoluteUrl($path));
    }

    public static function getGenerateAbsoluteUrlRequestContextData()
    {
        return [
            ['/foo.png', '/foo', 'localhost', 'http', 80, 443, 'http://localhost/foo.png'],
            ['foo.png', '/foo', 'localhost', 'http', 80, 443, 'http://localhost/foo/foo.png'],
            ['foo.png', '/foo/bar/', 'localhost', 'http', 80, 443, 'http://localhost/foo/bar/foo.png'],
            ['/foo.png', '/foo', 'localhost', 'https', 80, 443, 'https://localhost/foo.png'],
            ['foo.png', '/foo', 'localhost', 'https', 80, 443, 'https://localhost/foo/foo.png'],
            ['foo.png', '/foo/bar/', 'localhost', 'https', 80, 443, 'https://localhost/foo/bar/foo.png'],
            ['/foo.png', '/foo', 'localhost', 'http', 443, 80, 'http://localhost:443/foo.png'],
            ['/foo.png', '/foo', 'localhost', 'https', 443, 80, 'https://localhost:80/foo.png'],
        ];
    }

    public function testGenerateAbsoluteUrlWithScriptFileName()
    {
        $request = Request::create('http://localhost/app/web/app_dev.php');
        $request->server->set('SCRIPT_FILENAME', '/var/www/app/web/app_dev.php');

        $stack = new RequestStack();
        $stack->push($request);
        $extension = new HttpFoundationExtension(new UrlHelper($stack));

        $this->assertEquals(
            'http://localhost/app/web/bundles/framework/css/structure.css',
            $extension->generateAbsoluteUrl('/app/web/bundles/framework/css/structure.css')
        );
    }

    #[DataProvider('getGenerateRelativePathData')]
    public function testGenerateRelativePath($expected, $path, $pathinfo)
    {
        $stack = new RequestStack();
        $stack->push(Request::create($pathinfo));
        $extension = new HttpFoundationExtension(new UrlHelper($stack));

        $this->assertEquals($expected, $extension->generateRelativePath($path));
    }

    public static function getGenerateRelativePathData()
    {
        return [
            ['../foo.png', '/foo.png', '/foo/bar.html'],
            ['../baz/foo.png', '/baz/foo.png', '/foo/bar.html'],
            ['baz/foo.png', 'baz/foo.png', '/foo/bar.html'],

            ['http://example.com/baz', 'http://example.com/baz', '/'],
            ['https://example.com/baz', 'https://example.com/baz', '/'],
            ['//example.com/baz', '//example.com/baz', '/'],
        ];
    }

    #[DataProvider('provideSignUrlTemplates')]
    public function testSignUrl(string $template, array $context)
    {
        $signer = new UriSigner('secret');

        $this->assertSame(htmlspecialchars($signer->sign('https://example.com/ticket/42?lang=en', 2000000000)), $this->renderTemplate($template, $context, $signer));
    }

    public static function provideSignUrlTemplates(): iterable
    {
        yield 'timestamp' => ["{{ 'https://example.com/ticket/42?lang=en'|sign_url(2000000000) }}", []];
        yield 'date() function' => ["{{ 'https://example.com/ticket/42?lang=en'|sign_url(date('@2000000000')) }}", []];
        yield 'DateTimeInterface' => ['{{ url|sign_url(expiration) }}', ['url' => 'https://example.com/ticket/42?lang=en', 'expiration' => new \DateTimeImmutable('@2000000000')]];
        yield 'absolute_url() function' => ["{{ absolute_url('/ticket/42?lang=en')|sign_url(2000000000) }}", []];
    }

    public function testSignUrlWithTheDefaultExpiration()
    {
        $signer = new UriSigner('secret', '_hash', '_expiration', null, 3600);

        $url = html_entity_decode($this->renderTemplate("{{ 'https://example.com/ticket/42'|sign_url }}", [], $signer));

        $this->assertStringStartsWith('https://example.com/ticket/42?', $url);
        $this->assertTrue($signer->check($url));
    }

    #[DataProvider('provideNonAbsoluteUrls')]
    public function testSignUrlRequiresAnAbsoluteUrl(string $url)
    {
        $extension = new HttpFoundationExtension(new UrlHelper(new RequestStack()), new UriSigner('secret'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "sign_url" filter requires an absolute URL, "%s" given.', $url));

        $extension->signUrl($url, 2000000000);
    }

    public static function provideNonAbsoluteUrls(): iterable
    {
        yield 'path' => ['/ticket/42'];
        yield 'relative path' => ['ticket/42'];
        yield 'scheme-relative URL' => ['//example.com/ticket/42'];
    }

    public function testSignUrlRequiresAUriSigner()
    {
        $extension = new HttpFoundationExtension(new UrlHelper(new RequestStack()));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('The "sign_url" filter requires an instance of "%s".', UriSigner::class));

        $extension->signUrl('https://example.com/ticket/42', 2000000000);
    }

    private function renderTemplate(string $template, array $context, UriSigner $signer): string
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        $twig = new Environment(new ArrayLoader(['template' => $template]));
        $twig->addExtension(new HttpFoundationExtension(new UrlHelper($stack), $signer));

        return $twig->render('template', $context);
    }
}
