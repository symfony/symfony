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
    public function testSanitizeForHead(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '',
            (new HtmlSanitizer($config))->sanitizeFor('head', '<div style="width: 100px">Hello world</div>')
        );
    }

    public function testSanitizeForTextarea(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '&lt;div style&#61;&#34;width: 100px&#34;&gt;Hello&lt;/div&gt; world',
            (new HtmlSanitizer($config))->sanitizeFor('textarea', '<div style="width: 100px">Hello</div> world')
        );
    }

    public function testSanitizeForTitle(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '&lt;div style&#61;&#34;width: 100px&#34;&gt;Hello&lt;/div&gt; world',
            (new HtmlSanitizer($config))->sanitizeFor('title', '<div style="width: 100px">Hello</div> world')
        );
    }

    public function testSanitizeDeepNestedString(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertNotEmpty($this->sanitize($config, str_repeat('<div>T', 10000)));
    }

    public function testSanitizeNullByte(): void
    {
        $this->assertSame('Null byte�', $this->sanitize(new HtmlSanitizerConfig(), "Null byte\0"));
        $this->assertSame('Null byte�', $this->sanitize(new HtmlSanitizerConfig(), 'Null byte&#0;'));
    }

    public function testSanitizeDefaultBody(): void
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('div')
        ;

        $this->assertSame(
            '<div>Hello</div> world',
            (new HtmlSanitizer($config))->sanitize('<div style="width: 100px">Hello</div> world')
        );
    }

    public function testAllowElement(): void
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

    public function testAllowElementWithAttribute(): void
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

    public function testBlockElement(): void
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

    public function testDropElement(): void
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

    public function testAllowAttributeOnElement(): void
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

    public function testAllowAttributeEverywhere(): void
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

    public function testDropAttributeOnElement(): void
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

    public function testDropAttributeEverywhere(): void
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

    public function testForceAttribute(): void
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

    public function testForceHttps(): void
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

    public function testAllowLinksSchemes(): void
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

    public function testAllowLinksHosts(): void
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

    public function testAllowLinksRelative(): void
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

    public function testAllowMediaSchemes(): void
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

    public function testAllowMediasHosts(): void
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

    public function testAllowMediasRelative(): void
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

    public function testCustomAttributeSanitizer(): void
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

    private function sanitize(HtmlSanitizerConfig $config, string $input): string
    {
        return (new HtmlSanitizer($config))->sanitize($input);
    }
}
