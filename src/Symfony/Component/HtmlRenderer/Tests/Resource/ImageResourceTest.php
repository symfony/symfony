<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Resource;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\HtmlRenderer\Vector\SvgDocument;

final class ImageResourceTest extends TestCase
{
    public function testDecodesDataUrlsWithoutExternalAccess()
    {
        $loader = new ResourceLoader();
        $this->assertSame('hello', $loader->load('data:text/plain;base64,aGVsbG8='));
        $this->assertSame('hello world', $loader->load('data:text/plain,hello%20world'));
        $this->assertNull($loader->load('https://example.org/image.png'));
        $this->assertNull($loader->load('file:///etc/passwd'));
        $this->assertNull($loader->load('data:image/png;base64,?invalid'));
        $this->assertNull((new ResourceLoader(null, 3))->load('data:text/plain,1234'));
    }

    public function testNonStringLoaderResultsAreUnavailable()
    {
        foreach ([null, [], false, 42] as $value) {
            $this->assertNull((new ResourceLoader(static fn () => $value))->load('resource.png'));
        }
    }

    public function testRasterMetadataDoesNotRequireDecoding()
    {
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlS8AAAAASUVORK5CYII=', true);
        $resource = ImageResource::fromString($data);
        $this->assertSame('image/png', $resource?->mimeType);
        $this->assertSame(1.0, $resource?->width);
        $this->assertSame(1.0, $resource?->height);
        $this->assertSame($data, $resource?->data);
    }

    public function testIntrinsicRasterDimensionsFollowExifOrientation()
    {
        $tiff = 'II'.pack('vV', 42, 8).pack('v', 1).pack('vvVV', 0x112, 3, 1, 6).pack('V', 0);
        $app = "Exif\0\0".$tiff;
        $jpeg = "\xff\xd8\xff\xe1".pack('n', \strlen($app) + 2).$app."\xff\xc0".pack('nCnnC', 17, 8, 16, 32, 3)."\x01\x11\0\x02\x11\0\x03\x11\0\xff\xd9";
        $resource = ImageResource::fromString($jpeg);
        $this->assertSame(16.0, $resource?->width);
        $this->assertSame(32.0, $resource?->height);
    }

    public function testSvgSourceKeepsLocalPaintReferencesAndDropsActiveContent()
    {
        $svg = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="run()"><script>run()</script><animate attributeName="href" to="https://example.org"/><image href="file:///private"/><rect fill="url('#local')"/><circle style="fill:u\72l(https://example.org)"/><use href="#local"/></svg>
            SVG;
        $resource = ImageResource::fromString($svg);
        $this->assertStringContainsString("url('#local')", $resource->data);
        $this->assertStringContainsString('href="#local"', $resource->data);
        $this->assertStringNotContainsString('script', $resource->data);
        $this->assertStringNotContainsString('onload', $resource->data);
        $this->assertStringNotContainsString('https:', $resource->data);
        $this->assertStringNotContainsString('file:', $resource->data);
    }

    public function testSvgComputedStylesAreEncodedWithoutMutatingTheDocument()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg class="icon" width="16" height="16"><rect style="fill:blue" width="16" height="16"/></svg>');
        $svg = $document->querySelector('svg');
        $before = $document->saveHtml();
        $fill = 'red';
        $cascade = static function (\Dom\Element $element) use (&$fill): array { return 'rect' === $element->localName ? ['fill' => $fill] : []; };
        $first = ImageResource::fromSvg($svg, $cascade);
        $this->assertStringContainsString('fill:red', $first->data);
        $fill = 'green';
        $second = ImageResource::fromSvg($svg, $cascade);
        $this->assertStringContainsString('fill:green', $second->data);
        $this->assertNotSame($first->data, $second->data);
        $this->assertSame($before, $document->saveHtml());
    }

    public function testSvgStyleEmbeddingStopsBeforeExceedingItsByteBudget()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg>'.str_repeat('<rect width="1" height="1"/>', 129).'</svg>');
        $calls = 0;
        $value = str_repeat('x', 65536);
        $resource = ImageResource::fromSvg($document->querySelector('svg'), static function () use (&$calls, $value): array {
            ++$calls;

            return ['fill' => $value];
        });
        $this->assertNull($resource);
        $this->assertLessThan(129, $calls);
    }

    public function testSvgKeepsFractionalIntrinsicGeometryAndRejectsEntities()
    {
        $resource = ImageResource::fromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12.5 6.25"><path d="M0 0h12.5v6.25z"/></svg>');
        $this->assertSame('image/svg+xml', $resource?->mimeType);
        $this->assertSame(12.5, $resource?->width);
        $this->assertSame(6.25, $resource?->height);
        $this->assertNull(SvgDocument::parse('<!DOCTYPE svg [<!ENTITY payload SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&payload;</svg>'));
    }
}
