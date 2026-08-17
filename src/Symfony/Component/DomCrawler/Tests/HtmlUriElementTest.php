<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\HtmlImage;
use Symfony\Component\DomCrawler\HtmlLink;
use Symfony\Component\DomCrawler\Image;
use Symfony\Component\DomCrawler\Link;

class HtmlUriElementTest extends TestCase
{
    private function nativeElement(string $html, string $selector): \Dom\Element
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><body>'.$html.'</body>', 0)->querySelector($selector);
    }

    private function classicElement(string $html, string $selector): \DOMElement
    {
        $dom = new \DOMDocument();
        $dom->loadHTML('<!doctype html><body>'.$html.'</body>', \LIBXML_NOERROR);

        return $dom->getElementsByTagName($selector)->item(0);
    }

    public function testGetUriAndMethod()
    {
        $link = new HtmlLink($this->nativeElement('<a href="/foo">f</a>', 'a'), 'http://localhost/bar/');

        $this->assertSame('http://localhost/foo', $link->getUri());
        $this->assertSame('GET', $link->getMethod());
    }

    public function testMethodIsUppercased()
    {
        $link = new HtmlLink($this->nativeElement('<a href="/foo">f</a>', 'a'), 'http://localhost/', 'post');

        $this->assertSame('POST', $link->getMethod());
    }

    public function testGetNodeReturnsTheNativeElement()
    {
        $node = $this->nativeElement('<a href="/foo">f</a>', 'a');

        $this->assertSame($node, (new HtmlLink($node, 'http://localhost/'))->getNode());
    }

    #[DataProvider('provideLinkTags')]
    public function testAcceptsEveryLinkTag(string $html, string $selector)
    {
        $link = new HtmlLink($this->nativeElement($html, $selector), 'http://localhost/');

        $this->assertSame('http://localhost/foo', $link->getUri());
    }

    public static function provideLinkTags(): iterable
    {
        yield 'a' => ['<a href="/foo">f</a>', 'a'];
        yield 'area' => ['<map><area href="/foo"></map>', 'area'];
        yield 'link' => ['<link href="/foo">', 'link'];
    }

    public function testRejectsANonLinkTag()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to navigate from a "div" tag.');

        new HtmlLink($this->nativeElement('<div></div>', 'div'), 'http://localhost/');
    }

    public function testRejectsANonImageTag()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to visualize a "div" tag.');

        new HtmlImage($this->nativeElement('<div></div>', 'div'), 'http://localhost/');
    }

    public function testRelativeUriWithoutAnAbsoluteBaseIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Symfony\Component\DomCrawler\AbstractHtmlUriElement');

        new HtmlLink($this->nativeElement('<a href="/foo">f</a>', 'a'), 'not-absolute');
    }

    public function testImageGetUri()
    {
        $image = new HtmlImage($this->nativeElement('<img src="/pic.png">', 'img'), 'http://localhost/bar/');

        $this->assertSame('http://localhost/pic.png', $image->getUri());
    }

    /**
     * The native and the classic elements must resolve URIs the same way.
     */
    #[DataProvider('provideUriCases')]
    public function testNativeMatchesClassic(string $href, ?string $currentUri)
    {
        $html = \sprintf('<a href="%s">f</a>', $href);

        $native = new HtmlLink($this->nativeElement($html, 'a'), $currentUri);
        $classic = new Link($this->classicElement($html, 'a'), $currentUri);

        $this->assertSame($classic->getUri(), $native->getUri());
        $this->assertSame($classic->getMethod(), $native->getMethod());
    }

    public static function provideUriCases(): iterable
    {
        yield 'absolute path' => ['/foo', 'http://localhost/bar/baz'];
        yield 'relative path' => ['foo', 'http://localhost/bar/baz'];
        yield 'parent path' => ['../foo', 'http://localhost/bar/baz/'];
        yield 'same directory' => ['./foo', 'http://localhost/bar/'];
        yield 'absolute url' => ['http://example.com/foo', 'http://localhost/'];
        yield 'query only' => ['?a=b', 'http://localhost/bar'];
        yield 'fragment only' => ['#frag', 'http://localhost/bar'];
        yield 'empty href' => ['', 'http://localhost/bar'];
    }

    public function testImageNativeMatchesClassic()
    {
        $html = '<img src="../pic.png">';

        $native = new HtmlImage($this->nativeElement($html, 'img'), 'http://localhost/a/b/');
        $classic = new Image($this->classicElement($html, 'img'), 'http://localhost/a/b/');

        $this->assertSame($classic->getUri(), $native->getUri());
    }
}
