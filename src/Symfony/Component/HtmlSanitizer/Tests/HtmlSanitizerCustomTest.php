<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class HtmlSanitizerCustomTest extends TestCase
{
    public function testSanitizeForHead()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            ' world',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<div style="width: 100px">Hello</div> world')
        );
    }

    /**
     * @dataProvider provideSanitizeForHeadWithSafeElements
     */
    public function testSanitizeForHeadWithSafeElements(string $input, string $expected)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        $this->assertSame($expected, (new HtmlSanitizer($config))->sanitizeFor('head', $input));
    }

    public static function provideSanitizeForHeadWithSafeElements(): iterable
    {
        yield 'stylesheet' => ['<link rel="stylesheet" href="https://evil.example/x.css">', ''];
        yield 'data stylesheet' => ['<link rel="stylesheet" href="data:text/css,body{background:url(https://evil.example/leak)}">', ''];
        yield 'import' => ['<link rel="import" href="https://evil.example/x.html">', ''];
        yield 'referrer policy' => ['<meta name="referrer" content="unsafe-url">', ''];
        yield 'title' => ['<title>Hello</title>', '<title>Hello</title>'];
    }

    public function testSanitizeWithSafeElementsDropsReferrerPolicy()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        $this->assertSame(
            '<a href="https://evil.example/">Hello</a><img src="https://evil.example/pixel" />',
            $this->sanitize($config, '<a href="https://evil.example/" referrerpolicy="unsafe-url">Hello</a><img src="https://evil.example/pixel" referrerpolicy="unsafe-url">')
        );
    }

    public function testSanitizeWithSafeElementsDropsPlaintext()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        $this->assertSame('Hello', $this->sanitize($config, 'Hello<plaintext>the rest of the page'));
    }

    public function testSanitizeWithSafeElementsBlocksHtmlAndBody()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        $this->assertSame('<p>Hello</p>', $this->sanitize($config, '<body inert><p>Hello</p></body>'));
        $this->assertSame('<p>Hello</p>', $this->sanitize($config, '<html dir="rtl"><p>Hello</p></html>'));
        $this->assertSame('<div>Hello</div>', $this->sanitize($config, '<div><body inert>Hello</body></div>'));

        $document = '<html dir="rtl"><head><title>Hello</title></head><body inert><p>world</p></body></html>';
        $this->assertSame('<p>world</p>', $this->sanitize($config, $document));
        $this->assertSame('<p>world</p>', (new HtmlSanitizer($config))->sanitizeFor('html', $document));
    }

    public function testSanitizeWithSafeElementsDropsTheOpenerLinkType()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        $this->assertSame(
            '<a href="https://example.com/" target="_blank">Hello</a>',
            $this->sanitize($config, '<a href="https://example.com/" target="_blank" rel="opener">Hello</a>')
        );

        $this->assertSame(
            '<a href="https://example.com/" target="_blank" rel="nofollow noreferrer">Hello</a>',
            $this->sanitize($config, '<a href="https://example.com/" target="_blank" rel="nofollow OPENER noreferrer">Hello</a>')
        );

        $this->assertSame(
            '<area href="https://example.com/" target="_blank" />',
            $this->sanitize($config, '<area href="https://example.com/" target="_blank" rel="opener">')
        );
    }

    public function testSanitizeWithSafeElementsTurnsNamedTargetsIntoBlank()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
        ;

        foreach (['foo', '_new', ' _blank'] as $target) {
            $this->assertSame(
                '<a href="https://example.com/" target="_blank">Hello</a>',
                $this->sanitize($config, \sprintf('<a href="https://example.com/" target="%s">Hello</a>', $target))
            );
        }

        foreach (['_blank', '_BLANK', '_self', '_parent', '_top'] as $target) {
            $this->assertSame(
                \sprintf('<a href="https://example.com/" target="%s" rel="noopener">Hello</a>', $target),
                $this->sanitize($config, \sprintf('<a href="https://example.com/" target="%s" rel="noopener">Hello</a>', $target))
            );
        }
    }

    public function testSanitizeForTextarea()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '&lt;div style&#61;&#34;width: 100px&#34;&gt;Hello&lt;/div&gt; world',
            (new HtmlSanitizer($config))->sanitizeFor('textarea', '<div style="width: 100px">Hello</div> world')
        );
    }

    public function testSanitizeForTitle()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '&lt;div style&#61;&#34;width: 100px&#34;&gt;Hello&lt;/div&gt; world',
            (new HtmlSanitizer($config))->sanitizeFor('title', '<div style="width: 100px">Hello</div> world')
        );
    }

    public function testSanitizeDeepNestedString()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertNotEmpty($this->sanitize($config, str_repeat('<div>T', 10000)));
    }

    public function testSanitizeNullByte()
    {
        $this->assertSame('Null byte', $this->sanitize(new HtmlSanitizerConfig(), "Null byte\0"));
        $this->assertSame('Null byte', $this->sanitize(new HtmlSanitizerConfig(), 'Null byte&#0;'));
    }

    public function testSanitizeDefaultBody()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '<div>Hello</div> world',
            (new HtmlSanitizer($config))->sanitize('<div style="width: 100px">Hello</div> world')
        );
    }

    public function testAllowElement()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '<div>Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            ' world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testAllowElementWithAttribute()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div', ['style'])
        ;

        $this->assertSame(
            '<div style="width: 100px">Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            ' world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testBlockElement()
    {
        $config = (new HtmlSanitizerConfig())
            ->blockElement('div')
        ;

        $this->assertSame(
            'Hello world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            ' world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testDropElement()
    {
        $config = (new HtmlSanitizerConfig())
            ->blockElement('div')
            ->dropElement('div')
        ;

        $this->assertSame(
            ' world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            ' world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testAllowAttributeOnElement()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('span')
            ->allowAttribute('style', ['div'])
        ;

        $this->assertSame(
            '<div style="width: 100px">Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            '<span>Hello</span> world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testAllowAttributeEverywhere()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('span')
            ->allowAttribute('style', '*')
        ;

        $this->assertSame(
            '<div style="width: 100px">Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            '<span style="width: 100px">Hello</span> world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testDropAttributeOnElement()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('span')
            ->allowAttribute('style', '*')
            ->dropAttribute('style', 'span')
        ;

        $this->assertSame(
            '<div style="width: 100px">Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            '<span>Hello</span> world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testDropAttributeEverywhere()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('span')
            ->allowAttribute('style', '*')
            ->dropAttribute('style', '*')
        ;

        $this->assertSame(
            '<div>Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            '<span>Hello</span> world',
            $this->sanitize($config, '<span style="width: 100px">Hello</span> world')
        );
    }

    public function testForceAttribute()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
            ->allowElement('img', '*')
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('img', 'loading', 'lazy')
        ;

        $this->assertSame(
            '<img title="My image" src="https://example.com/image.png" loading="lazy" />',
            $this->sanitize($config, '<img title="My image" src="https://example.com/image.png" loading="eager" onerror="alert(\'1234\')" />')
        );

        $this->assertSame(
            '<a rel="noopener noreferrer">Hello</a> world',
            $this->sanitize($config, '<a>Hello</a> world')
        );

        $this->assertSame(
            '<a href="https://symfony.com" rel="noopener noreferrer">Hello</a> world',
            $this->sanitize($config, '<a href="https://symfony.com">Hello</a> world')
        );

        $this->assertSame(
            '<div>Hello</div> world',
            $this->sanitize($config, '<div style="width: 100px">Hello</div> world')
        );

        $this->assertSame(
            '<a href="https://symfony.com" rel="noopener noreferrer">Hello</a> world',
            $this->sanitize($config, '<a href="https://symfony.com" rel="noopener">Hello</a> world')
        );
    }

    public function testForceHttps()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->forceHttpsUrls()
        ;

        $this->assertSame(
            '<a href="https://symfony.com">Hello world</a>',
            $this->sanitize($config, '<a href="http://symfony.com">Hello world</a>')
        );

        $this->assertSame(
            '<a href="https://symfony.com">Hello world</a>',
            $this->sanitize($config, '<a href="https://symfony.com">Hello world</a>')
        );

        $this->assertSame(
            '<a>Hello world</a>',
            $this->sanitize($config, '<a href="/index.php">Hello world</a>')
        );
    }

    public function testAllowLinksSchemes()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https'])
        ;

        $this->assertSame(
            '<a href="https://trusted.com">Hello world</a>',
            $this->sanitize($config, '<a href="https://trusted.com">Hello world</a>')
        );

        $this->assertSame(
            '<a>Hello world</a>',
            $this->sanitize($config, '<a href="mailto:galopintitouan@gmail.com">Hello world</a>')
        );
    }

    public function testAllowLinksHosts()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowLinkHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<a href="https://trusted.com">Hello world</a>',
            $this->sanitize($config, '<a href="https://trusted.com">Hello world</a>')
        );

        $this->assertSame(
            '<a>Hello world</a>',
            $this->sanitize($config, '<a href="https://untrusted.com">Hello world</a>')
        );
    }

    public function testAreaUsesLinkPolicy()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('area', ['href'])
            ->allowLinkHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<area href="https://trusted.com" />',
            $this->sanitize($config, '<area href="https://trusted.com">')
        );

        $this->assertSame(
            '<area />',
            $this->sanitize($config, '<area href="https://untrusted.com">')
        );
    }

    public function testAllowLinksRelative()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowRelativeLinks()
        ;

        $this->assertSame(
            '<a href="/index.php">Hello world</a>',
            $this->sanitize($config, '<a href="/index.php">Hello world</a>')
        );

        $this->assertSame(
            '<a href="https://symfony.com">Hello world</a>',
            $this->sanitize($config, '<a href="https://symfony.com">Hello world</a>')
        );
    }

    public function testAllowLinksRelativeDoesNotAllowSchemeRelativeHosts()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowLinkHosts(['trusted.com'])
            ->allowRelativeLinks()
        ;

        foreach (['https:untrusted.com', 'https:untrusted.com/login', 'http:untrusted.com', 'https:/untrusted.com', 'https:///untrusted.com', '///untrusted.com', '//untrusted.com', 'https://://untrusted.com/', 'https://:/untrusted.com/', '//:/untrusted.com/'] as $href) {
            $this->assertSame(
                '<a>Hello world</a>',
                $this->sanitize($config, \sprintf('<a href="%s">Hello world</a>', $href))
            );
        }

        $this->assertSame(
            '<a href="/index.php">Hello world</a>',
            $this->sanitize($config, '<a href="/index.php">Hello world</a>')
        );

        $this->assertSame(
            '<a href="https://trusted.com/index.php">Hello world</a>',
            $this->sanitize($config, '<a href="https://trusted.com/index.php">Hello world</a>')
        );
    }

    public function testAllowMediaSchemes()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src'])
            ->allowMediaSchemes(['https'])
        ;

        $this->assertSame(
            '<img src="https://trusted.com" />',
            $this->sanitize($config, '<img src="https://trusted.com" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img src="http://trusted.com" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img src="/image.png" />')
        );
    }

    public function testAllowMediasHosts()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img src="https://trusted.com" />',
            $this->sanitize($config, '<img src="https://trusted.com" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img src="https://untrusted.com" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img src="/image.png" />')
        );
    }

    public function testAllowMediasRelative()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src'])
            ->allowRelativeMedias()
        ;

        $this->assertSame(
            '<img src="/image.png" />',
            $this->sanitize($config, '<img src="/image.png" />')
        );

        $this->assertSame(
            '<img src="https://trusted.com" />',
            $this->sanitize($config, '<img src="https://trusted.com" />')
        );
    }

    public function testAllowMediasRelativeDoesNotAllowSchemeRelativeHosts()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src'])
            ->allowMediaHosts(['trusted.com'])
            ->allowRelativeMedias()
        ;

        foreach (['https:untrusted.com/image.png', 'https:/untrusted.com/image.png', 'https:///untrusted.com/image.png', '///untrusted.com/image.png', '//untrusted.com/image.png', 'https://://untrusted.com/image.png', '//:/untrusted.com/image.png'] as $src) {
            $this->assertSame(
                '<img />',
                $this->sanitize($config, \sprintf('<img src="%s" />', $src))
            );
        }

        $this->assertSame(
            '<img src="/image.png" />',
            $this->sanitize($config, '<img src="/image.png" />')
        );

        $this->assertSame(
            '<img src="//trusted.com/image.png" />',
            $this->sanitize($config, '<img src="//trusted.com/image.png" />')
        );
    }

    public function testAllowedHostsApplyToHostZero()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowElement('img', ['src'])
            ->allowLinkHosts(['trusted.com'])
            ->allowRelativeLinks()
            ->allowMediaHosts(['trusted.com'])
            ->allowRelativeMedias()
        ;

        $this->assertSame('<a>Hello world</a>', $this->sanitize($config, '<a href="https://0/">Hello world</a>'));
        $this->assertSame('<a>Hello world</a>', $this->sanitize($config, '<a href="//0/login">Hello world</a>'));
        $this->assertSame('<img />', $this->sanitize($config, '<img src="https://0/image.png" />'));
    }

    public function testAllowMediasHostsInSrcset()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src', 'srcset'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/b.png 2x" />',
            $this->sanitize($config, '<img srcset="https://untrusted.com/a.png 1x, https://trusted.com/b.png 2x" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img srcset="https://untrusted.com/a.png 1x" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img src="https://untrusted.com/a.png" />')
        );
    }

    public function testAllowMediasSchemesInSrcset()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('picture')
            ->allowElement('source', ['srcset'])
            ->allowElement('img', ['src'])
            ->allowMediaSchemes(['https'])
        ;

        $this->assertSame(
            '<picture><source /><img /></picture>',
            $this->sanitize($config, '<picture><source srcset="http://trusted.com/a.png 1x"><img src="http://trusted.com/a.png"></picture>')
        );

        $this->assertSame(
            '<picture><source srcset="https://trusted.com/a.png 1x" /></picture>',
            $this->sanitize($config, '<picture><source srcset="https://trusted.com/a.png 1x, ftp://trusted.com/b.png 2x"></picture>')
        );
    }

    public function testAllowMediasHostsInImageSrcset()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('link', ['rel', 'as', 'href', 'imagesrcset'])
            ->allowMediaHosts(['trusted.com'])
            ->allowLinkHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<link rel="preload" as="image" imagesrcset="https://trusted.com/b.png 2x" href="https://trusted.com/fallback.png" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<link rel="preload" as="image" imagesrcset="https://untrusted.com/a.png 1x, https://trusted.com/b.png 2x" href="https://trusted.com/fallback.png">')
        );

        $this->assertSame(
            '<link rel="preload" as="image" href="https://trusted.com/fallback.png" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<link rel="preload" as="image" imagesrcset="https://untrusted.com/a.png 1x" href="https://trusted.com/fallback.png">')
        );
    }

    public function testAllowedSrcsetIsPreservedWithItsDescriptors()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['src', 'srcset'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png 1x, https://trusted.com/b.png 2x, https://trusted.com/c.png 640w, https://trusted.com/d.png" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png 1x, https://trusted.com/b.png 2x, https://trusted.com/c.png 640w, https://trusted.com/d.png" />')
        );
    }

    public function testSrcsetCommasInsideUrlsAreNotCandidateSeparators()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/a,b.png 1x, https://trusted.com/c,d.png 2x" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a,b.png 1x,https://trusted.com/c,d.png 2x" />')
        );

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png, https://trusted.com/b.png 2x" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png,,, https://trusted.com/b.png 2x" />')
        );
    }

    public function testSrcsetCommasInsideDescriptorParenthesesAreNotCandidateSeparators()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png (a,b)" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png (a,b) , https://evil.com/x.png 1x" />')
        );

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png (a,b), https://trusted.com/c.png 1x" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png (a,b), https://trusted.com/c.png 1x" />')
        );

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png 1x" />',
            $this->sanitize($config, '<img srcset="https://evil.com/x.png (a,b) , https://trusted.com/a.png 1x" />')
        );

        // An unterminated parenthesis swallows the rest of the value, as the specification requires
        $this->assertSame(
            '<img srcset="https://trusted.com/a.png (a,b" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png (a,b" />')
        );

        // Parentheses do not nest, so the first one closes the run and the next comma separates
        $this->assertSame(
            '<img srcset="https://trusted.com/a.png (a(b)" />',
            $this->sanitize($config, '<img srcset="https://trusted.com/a.png (a(b),c) 2x, https://evil.com/x.png 1x" />')
        );
    }

    public function testSrcsetDescriptorWhitespaceIsNormalized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
            ->allowMediaHosts(['trusted.com'])
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png 100w 2x, https://trusted.com/b.png 1x" />',
            $this->sanitize($config, "<img srcset=\"https://trusted.com/a.png \t 100w\n\n2x ,\t https://trusted.com/b.png   1x\" />")
        );
    }

    public function testForceHttpsUrlsInSrcset()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
            ->forceHttpsUrls()
        ;

        $this->assertSame(
            '<img srcset="https://trusted.com/a.png 1x" />',
            $this->sanitize($config, '<img srcset="http://trusted.com/a.png 1x" />')
        );
    }

    public function testAllowRelativeMediasInSrcset()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
        ;

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img srcset="/a.png 1x" />')
        );

        $this->assertSame(
            '<img srcset="/a.png 1x" />',
            $this->sanitize($config->allowRelativeMedias(), '<img srcset="/a.png 1x" />')
        );
    }

    public function testAllowRelativeMediasInSrcsetDoesNotAllowSchemeRelativeHosts()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('img', ['srcset'])
            ->allowMediaHosts(['trusted.com'])
            ->allowRelativeMedias()
        ;

        $this->assertSame(
            '<img srcset="/b.png 2x" />',
            $this->sanitize($config, '<img srcset="https:untrusted.com/a.png 1x, /b.png 2x" />')
        );

        $this->assertSame(
            '<img srcset="https://trusted.com/c.png 3x" />',
            $this->sanitize($config, '<img srcset="https:/untrusted.com/a.png 1x, ///untrusted.com/b.png 2x, https://trusted.com/c.png 3x" />')
        );

        $this->assertSame(
            '<img />',
            $this->sanitize($config, '<img srcset="https:untrusted.com/a.png 1x" />')
        );
    }

    public function testActionAttributeIsSanitized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('form', ['action'])
        ;

        $this->assertSame(
            '<form>Hello world</form>',
            $this->sanitize($config, '<form action="javascript:alert(1)">Hello world</form>')
        );

        $this->assertSame(
            '<form>Hello world</form>',
            $this->sanitize($config, '<form action="data:text/html,foo">Hello world</form>')
        );

        $this->assertSame(
            '<form action="https://symfony.com">Hello world</form>',
            $this->sanitize($config, '<form action="https://symfony.com">Hello world</form>')
        );
    }

    public function testFormactionAttributeIsSanitized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('button', ['formaction'])
            ->allowElement('input', ['type', 'formaction'])
        ;

        $this->assertSame(
            '<button>Submit</button>',
            $this->sanitize($config, '<button formaction="javascript:alert(1)">Submit</button>')
        );

        $this->assertSame(
            '<input type="image" />',
            $this->sanitize($config, '<input type="image" formaction="javascript:alert(1)">')
        );

        $this->assertSame(
            '<button formaction="https://symfony.com">Submit</button>',
            $this->sanitize($config, '<button formaction="https://symfony.com">Submit</button>')
        );
    }

    public function testPosterAttributeIsSanitized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('video', ['poster'])
        ;

        $this->assertSame(
            '<video>Hello world</video>',
            $this->sanitize($config, '<video poster="javascript:alert(1)">Hello world</video>')
        );

        $this->assertSame(
            '<video poster="https://symfony.com/poster.jpg">Hello world</video>',
            $this->sanitize($config, '<video poster="https://symfony.com/poster.jpg">Hello world</video>')
        );
    }

    public function testCiteAttributeIsSanitized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('blockquote', ['cite'])
            ->allowElement('q', ['cite'])
        ;

        $this->assertSame(
            '<blockquote>Hello world</blockquote>',
            $this->sanitize($config, '<blockquote cite="javascript:alert(1)">Hello world</blockquote>')
        );

        $this->assertSame(
            '<q cite="https://symfony.com">Hello world</q>',
            $this->sanitize($config, '<q cite="https://symfony.com">Hello world</q>')
        );
    }

    /**
     * @dataProvider provideUrlAttributesSanitizedAcrossEmbeddingElements
     */
    public function testUrlAttributesAreSanitizedAcrossEmbeddingElements(string $element, string $attribute, string $input, string $expected)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement($element, [$attribute])
        ;

        $this->assertSame($expected, $this->sanitize($config, $input));
    }

    public static function provideUrlAttributesSanitizedAcrossEmbeddingElements(): iterable
    {
        yield 'object data javascript:' => [
            'object', 'data',
            '<object data="javascript:alert(1)">x</object>',
            '<object>x</object>',
        ];
        yield 'object data http kept' => [
            'object', 'data',
            '<object data="https://symfony.com/x">x</object>',
            '<object data="https://symfony.com/x">x</object>',
        ];
        yield 'applet codebase javascript:' => [
            'applet', 'codebase',
            '<applet codebase="javascript:alert(1)">x</applet>',
            '<applet>x</applet>',
        ];
        yield 'applet archive javascript:' => [
            'applet', 'archive',
            '<applet archive="javascript:alert(1)">x</applet>',
            '<applet>x</applet>',
        ];
        yield 'object codebase javascript:' => [
            'object', 'codebase',
            '<object codebase="javascript:alert(1)">x</object>',
            '<object>x</object>',
        ];
        yield 'iframe longdesc javascript:' => [
            'iframe', 'longdesc',
            '<iframe longdesc="javascript:alert(1)">x</iframe>',
            '<iframe>x</iframe>',
        ];
        yield 'img longdesc javascript:' => [
            'img', 'longdesc',
            '<img longdesc="javascript:alert(1)">',
            '<img />',
        ];
    }

    public function testMetaRefreshContentIsSanitized()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('meta', ['http-equiv', 'content'])
        ;

        $this->assertSame(
            '<meta http-equiv="refresh" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="0; url=javascript:alert(1)">')
        );

        $this->assertSame(
            '<meta http-equiv="refresh" content="5; url&#61;https://symfony.com/" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="5; url=https://symfony.com/">')
        );

        $this->assertSame(
            '<meta http-equiv="refresh" content="5" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="5">')
        );
    }

    /**
     * @dataProvider provideMetaRefreshContent
     */
    public function testMetaRefreshContentGrammar(string $content, string $expected)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowStaticElements()
            ->allowLinkHosts(['trusted.example'])
        ;

        $this->assertSame(
            $expected,
            (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="'.$content.'">')
        );
    }

    public static function provideMetaRefreshContent(): iterable
    {
        yield 'canonical' => ['0;url=https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'whitespace separator' => ['0 url=https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'no url keyword' => ['0;https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'whitespace around comma' => ['9 , https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'comma separator' => ['0,https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'fractional time' => ['.5;url=https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'incomplete url keyword' => ['0;uhttps://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'quoted url' => ['0;url=&#039;https://evil.example/&#039;', '<meta http-equiv="refresh" />'];
        yield 'unparsable directive' => ['0:https://evil.example/', '<meta http-equiv="refresh" />'];
        yield 'vertical tab in the url keyword' => ["0;url\x0B=https://trusted.example/x", '<meta http-equiv="refresh" />'];
        yield 'vertical tab as separator' => ["0\x0Bhttps://trusted.example/x", '<meta http-equiv="refresh" />'];

        yield 'allowed host' => [
            '0;url=https://trusted.example/x',
            '<meta http-equiv="refresh" content="0;url&#61;https://trusted.example/x" />',
        ];
        yield 'allowed host without url keyword' => [
            '9 , https://trusted.example/x',
            '<meta http-equiv="refresh" content="9 , https://trusted.example/x" />',
        ];
        yield 'long trailing whitespace' => [
            '0;url=https://trusted.example/x'.str_repeat(' ', 2000).'y',
            '<meta http-equiv="refresh" content="0;url&#61;https://trusted.example/x'.str_repeat('%20', 2000).'y" />',
        ];
        yield 'reload' => ['5', '<meta http-equiv="refresh" content="5" />'];
        yield 'reload with an empty url' => ['0;url&#61;', '<meta http-equiv="refresh" content="0;url&#61;" />'];
        yield 'reload with an empty quoted url' => [
            '0;url&#61;&#039;&#039;',
            '<meta http-equiv="refresh" content="0;url&#61;&#039;&#039;" />',
        ];
        yield 'not a refresh directive' => ['some text', '<meta http-equiv="refresh" content="some text" />'];
    }

    /**
     * An incomplete "url=" keyword is part of the URL rather than a keyword, so the
     * browser navigates to a relative URL that allowRelativeLinks() opts into.
     */
    public function testMetaRefreshIncompleteUrlKeyword()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowStaticElements()
            ->allowLinkHosts(['trusted.example'])
            ->allowRelativeLinks()
        ;

        foreach (['0;u//evil.example/', '0;ur//evil.example/', '0;url//evil.example/'] as $content) {
            $this->assertSame(
                '<meta http-equiv="refresh" content="'.$content.'" />',
                (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="'.$content.'">')
            );
        }

        $this->assertSame(
            '<meta http-equiv="refresh" />',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<meta http-equiv="refresh" content="0;url=https://evil.example/">')
        );
    }

    public function testCustomAttributeSanitizer()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div', ['data-attr'])
            ->withAttributeSanitizer(new class implements AttributeSanitizerInterface {
                public function getSupportedElements(): ?array
                {
                    return ['div'];
                }

                public function getSupportedAttributes(): ?array
                {
                    return ['data-attr'];
                }

                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    return 'new value';
                }
            })
        ;

        $this->assertSame(
            '<div data-attr="new value">Hello world</div>',
            $this->sanitize($config, '<div data-attr="old value">Hello world</div>')
        );
    }

    public function testWildcardAttributeSanitizerIsCalled()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div', ['data-foo', 'data-bar'])
            ->withAttributeSanitizer(new class implements AttributeSanitizerInterface {
                public function getSupportedElements(): ?array
                {
                    return null;
                }

                public function getSupportedAttributes(): ?array
                {
                    return null;
                }

                public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
                {
                    return strrev($value);
                }
            })
        ;

        $this->assertSame(
            '<div data-foo="cba" data-bar="zyx">Hello world</div>',
            $this->sanitize($config, '<div data-foo="abc" data-bar="xyz">Hello world</div>')
        );
    }

    public function testMaxInputLengthIsAppliedToTextContext()
    {
        $config = (new HtmlSanitizerConfig());

        $input = str_repeat('A', $config->getMaxInputLength() + 100);
        $expected = str_repeat('A', $config->getMaxInputLength());

        $this->assertSame($expected, (new HtmlSanitizer($config))->sanitizeFor('textarea', $input));
        $this->assertSame($expected, (new HtmlSanitizer($config))->sanitizeFor('title', $input));
    }

    public function testMaxInputLengthDoesNotCutAMultibyteCharacter()
    {
        $config = (new HtmlSanitizerConfig())->allowSafeElements();

        $this->assertSame('<p>abcde</p>', $this->sanitize($config->withMaxInputLength(9), '<p>abcde§f</p>'));
        $this->assertSame('<p>abcde</p>', $this->sanitize($config->withMaxInputLength(10), '<p>abcde日f</p>'));
        $this->assertSame('<p>abcde日</p>', $this->sanitize($config->withMaxInputLength(11), '<p>abcde日f</p>'));
        $this->assertSame('abcde', (new HtmlSanitizer($config->withMaxInputLength(6)))->sanitizeFor('textarea', 'abcde日'));
    }

    private function sanitize(HtmlSanitizerConfig $config, string $input): string
    {
        return (new HtmlSanitizer($config))->sanitize($input);
    }

    public function testAllowRelativeLinksRejectsColonInFirstPathSegment()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('a', ['href'])
            ->allowRelativeLinks()
        ;

        foreach ([':', '::', ':link.php'] as $href) {
            $this->assertSame(
                '<a>Hello world</a>',
                $this->sanitize($config, \sprintf('<a href="%s">Hello world</a>', $href))
            );
        }

        $this->assertSame(
            '<a href="./:link.php">Hello world</a>',
            $this->sanitize($config, '<a href="./:link.php">Hello world</a>')
        );
    }
}
