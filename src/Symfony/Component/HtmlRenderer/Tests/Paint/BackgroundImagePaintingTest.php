<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Paint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class BackgroundImagePaintingTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="4" height="2"><rect width="4" height="2" fill="red"/></svg>';

    public function testHeadlessImagePatternRetainsFractionalOriginSizeAndClipping()
    {
        $uri = 'data:image/svg+xml,'.rawurlencode(self::SVG);
        $result = new HtmlRenderer()->render('<!doctype html><div style="width:20.5px;height:8.5px;padding:1.5px;border:.25px solid black;background-image:url('.$uri.');background-size:4.5px 2.25px;background-position:right 1.25px bottom .5px;background-origin:content-box;background-clip:content-box;background-repeat:no-repeat"></div>', new RenderingContext(100, 100));
        $operations = $result->getDisplayList()->getOperations();
        $images = array_values(array_filter($operations, static fn (array $op): bool => 'background-image' === $op['type']));
        self::assertCount(1, $images);
        self::assertSame('image/svg+xml', $images[0]['mime-type']);
        self::assertSame(self::SVG, $images[0]['data']);
        self::assertEqualsWithDelta(16.5, $images[0]['pattern']['x']['start'], 1e-10);
        self::assertEqualsWithDelta(7.5, $images[0]['pattern']['y']['start'], 1e-10);
        self::assertSame(4.5, $images[0]['pattern']['x']['size']);
        self::assertSame(2.25, $images[0]['pattern']['y']['size']);
        self::assertSame(1, $images[0]['pattern']['x']['count']);
        $clips = array_values(array_filter($operations, static fn (array $op): bool => 'push-clip' === $op['type']));
        self::assertSame([1.75, 1.75, 20.5, 8.5], [$clips[0]['x'], $clips[0]['y'], $clips[0]['width'], $clips[0]['height']]);
        self::assertSame('border', $operations[array_key_last($operations)]['type']);
    }

    public function testRepeatedPatternsRemainOneOperationAcrossLargeAreas()
    {
        $uri = 'data:image/svg+xml,'.rawurlencode(self::SVG);
        $operations = new HtmlRenderer()->render('<!doctype html><div style="width:1000000px;height:1000000px;background-image:url('.$uri.')"></div>', new RenderingContext(1000000, 1000000))->getDisplayList()->getOperations();
        self::assertCount(1, $operations);
        self::assertSame('background-image', $operations[0]['type']);
        self::assertNull($operations[0]['pattern']['x']['count']);
        self::assertSame(4.0, $operations[0]['pattern']['x']['size']);
        self::assertSame(2.0, $operations[0]['pattern']['y']['size']);
    }

    public function testResourcesAreReadOnceAndMissingLayersKeepTheSurvivingPaintOrder()
    {
        $reads = [];
        $loader = new ResourceLoader(static function (string $url) use (&$reads): ?string {
            $reads[] = $url;

            return 'icon.svg' === $url ? self::SVG : null;
        });
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div id="a" style="width:100px;height:20px;background-image:url(missing.svg),url(icon.svg),linear-gradient(red,blue)"></div>');
        $session = new HtmlRenderer()->createSession($document, new RenderingContext(100, 100, null, $loader));
        $first = $session->render()->getDisplayList()->getOperations();
        self::assertSame(['gradient', 'background-image'], array_column($first, 'type'));
        $document->getElementById('a')->setAttribute('style', 'width:100px;height:20px;background-image:url(icon.svg);background-size:8px 4px');
        $second = $session->render()->getDisplayList()->getOperations();
        self::assertSame(['missing.svg', 'icon.svg'], $reads);
        self::assertSame(8.0, $second[0]['pattern']['x']['size']);
        self::assertSame(4.0, $second[0]['pattern']['y']['size']);
    }

    public function testDefaultResourcePolicyDoesNotFetchUrls()
    {
        $operations = new HtmlRenderer()->render('<!doctype html><div style="height:10px;background:red url(https://example.com/image.png)"></div>', new RenderingContext(100, 100))->getDisplayList()->getOperations();
        self::assertSame(['rectangle'], array_column($operations, 'type'));
        self::assertSame('red', $operations[0]['color']);
    }
}
