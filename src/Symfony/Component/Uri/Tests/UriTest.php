<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uri\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uri\Exception\InvalidUriException;
use Symfony\Component\Uri\Exception\UnresolvableUriException;
use Symfony\Component\Uri\Uri;

/**
 * @covers \Symfony\Component\Uri\Uri
 */
class UriTest extends TestCase
{
    /**
     * @dataProvider provideValidUris
     */
    public function testParseValidUriAreReconstructed(string $uri)
    {
        $parsedUri = Uri::parse($uri);

        $this->assertSame($uri, (string) $parsedUri);
    }

    public function testReconstructUriWithZeros()
    {
        $uri = Uri::parse('https://0:0@example.com');

        $this->assertSame('https://0:0@example.com', (string) $uri);
    }

    public function testUriWithoutDoubleSlash()
    {
        $uri = Uri::parse('mailto:user@example.com');

        $this->assertSame('mailto', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertNull($uri->host);
        $this->assertNull($uri->port);
        $this->assertSame('user@example.com', $uri->path);
        $this->assertNull($uri->query);
        $this->assertNull($uri->fragment);

        $this->assertSame('mailto:user@example.com', (string) $uri);
    }

    public function testUriWithOneSlashThrowsException()
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The URI "https:/example.com" is invalid.');

        Uri::parse('https:/example.com');
    }

    public static function provideValidUris(): iterable
    {
        yield ['https://example.com'];
        yield ['https://example.com/'];
        yield ['https://example.com/foo/bar'];
        yield ['https://example.com?foo=bar'];
        yield ['https://example.com#foo'];
        yield ['https://example.com?foo=bar#baz'];
        yield ['https://example.com:8080'];
        yield ['https://example.com:8080/'];
        yield ['https://example.com:8080/foo/bar'];
        yield ['https://example.com:8080?foo=bar'];
        yield ['https://example.com:8080#foo'];
        yield ['https://example.com:8080?foo=bar#baz'];
        yield ['https://user:pass@example.com'];
        yield ['https://user@example.com'];
    }

    public function testBasicUri()
    {
        $uri = Uri::parse('https://example.com');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertNull($uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testUriWithPath()
    {
        $uri = Uri::parse('https://example.com/foo/bar');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertSame('/foo/bar', $uri->path);
        $this->assertNull($uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testUriWithQueryString()
    {
        $uri = Uri::parse('https://example.com?foo=bar');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertSame('foo=bar', (string) $uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testUriWithFragment()
    {
        $uri = Uri::parse('https://example.com#foo');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertNull($uri->query);
        $this->assertSame('foo', $uri->fragment);
    }

    public function testUriWithUserAndPassword()
    {
        $uri = Uri::parse('https://user:pass@example.com');

        $this->assertSame('https', $uri->scheme);
        $this->assertSame('user', $uri->user);
        $this->assertSame('pass', $uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertNull($uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testUriWithColonInUsernameAndAtInPasswordIsDecoded()
    {
        $uri = Uri::parse('https://user%3A:p%40ss@example.com');

        $this->assertSame('https', $uri->scheme);
        $this->assertSame('user:', $uri->user);
        $this->assertSame('p@ss', $uri->password);
        $this->assertSame('example.com', $uri->host);

        // ensure stringed version re-encodes the user and password
        $this->assertSame('https://user%3A:p%40ss@example.com', (string) $uri);
    }

    public function testEncodedCharactersInQueryParameterAreNotDecoded()
    {
        $uri = Uri::parse('https://example.com?foo=bar%3D');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertSame('example.com', $uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertSame('foo=bar%3D', (string) $uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testMissingSchemeThrowsException()
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The URI "//example.com" is invalid.');

        Uri::parse('//example.com');
    }

    public function testOnlySchemeIsRequired()
    {
        $uri = Uri::parse('https:');

        $this->assertSame('https', $uri->scheme);
        $this->assertNull($uri->user);
        $this->assertNull($uri->password);
        $this->assertNull($uri->host);
        $this->assertNull($uri->port);
        $this->assertNull($uri->path);
        $this->assertNull($uri->query);
        $this->assertNull($uri->fragment);
    }

    public function testEmptyStringThrowsException()
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The URI "" is invalid.');

        Uri::parse('');
    }

    public function testNonUriStringThrowsException()
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('The URI "foo" is invalid.');

        Uri::parse('foo');
    }

    public function testUriWithFragmentTextDirectives()
    {
        $uri = Uri::parse('https://example.com');
        $newUri = $uri->withFragmentTextDirective('start', 'end', 'prefix', 'suffix');

        $this->assertNull($uri->fragmentTextDirective);
        $this->assertNotNull($newUri->fragmentTextDirective);

        $this->assertNotSame($uri, $newUri);

        $this->assertSame('https://example.com#:~:text=prefix-,start,end,-suffix', (string) $newUri);
    }

    public function testUriWithExistingFragmentWithFragmentTextDirectives()
    {
        $uri = Uri::parse('https://example.com#existing');
        $newUri = $uri->withFragmentTextDirective('start', 'end', 'prefix', 'suffix');

        $this->assertNull($uri->fragmentTextDirective);
        $this->assertNotNull($newUri->fragmentTextDirective);

        $this->assertNotSame($uri, $newUri);

        $this->assertSame('https://example.com#existing:~:text=prefix-,start,end,-suffix', (string) $newUri);
    }

    public function testUriWithIdnHostAsAscii()
    {
        $uri = Uri::parse('https://bücher.ch');
        $this->assertSame('bücher.ch', $uri->host);

        $uri = $uri->withIdnHostAsAscii();
        $this->assertSame('xn--bcher-kva.ch', $uri->host);
    }

    public function testUriWithIdnHostAsUnicode()
    {
        $uri = Uri::parse('https://xn--bcher-kva.ch');
        $this->assertSame('xn--bcher-kva.ch', $uri->host);

        $uri = $uri->withIdnHostAsUnicode();
        $this->assertSame('bücher.ch', $uri->host);
    }

    /**
     * @dataProvider provideResolveUri
     */
    public function testResolveUri(string $baseUri, string $relativeUri, string $expectedUri)
    {
        $resolvedUri = Uri::resolve($relativeUri, $baseUri);

        $this->assertSame($expectedUri, $resolvedUri);
    }

    public function testInvalidUriAsBaseToResolve()
    {
        $this->expectException(UnresolvableUriException::class);
        $this->expectExceptionMessage('The URI "mailto:user@example.com" cannot be used as a base URI in a resolution.');

        Uri::resolve('bar', 'mailto:user@example.com');
    }

    public static function provideResolveUri(): iterable
    {
        yield 'Single-level absolute path' => ['https://example.com/foo', '/bar', 'https://example.com/bar'];
        yield 'Multi-level Absolute path' => ['https://example.com', '/foo/bar', 'https://example.com/foo/bar'];
        yield 'Single-level relative path' => ['https://example.com/foo', 'bar', 'https://example.com/bar'];
        yield 'Single-level relative path with trailing slash' => ['https://example.com/foo/', 'bar', 'https://example.com/foo/bar'];
        yield 'Single-level parent' => ['https://example.com/foo/', '../bar', 'https://example.com/bar'];
        yield 'Multi-level parent' => ['https://example.com/foo/', '../../bar', 'https://example.com/bar'];
        yield 'Absolute path with trailing slash' => ['https://example.com/foo/', '/bar', 'https://example.com/bar'];
        yield 'Different domains' => ['https://example.com/foo/', 'https://example.org/bar', 'https://example.org/bar'];
        yield 'Different domains without double-slash' => ['https://example.com/foo/', 'mailto:user@example.com', 'mailto:user@example.com'];
        yield 'Erase query string and fragment of base URI' => ['https://example.com/foo?foo=bar#baz', '/bar', 'https://example.com/bar'];
        yield 'Erase query string and fragment of base URI with trailing slash' => ['https://example.com/foo/?foo=bar#baz', 'bar', 'https://example.com/foo/bar'];
        yield 'Keep query string and fragment of relative URI' => ['https://example.com/foo/', '/bar?foo=bar#baz', 'https://example.com/bar?foo=bar#baz'];
        yield 'Relative URI is empty' => ['https://example.com/foo', '', 'https://example.com/foo'];
    }
}
