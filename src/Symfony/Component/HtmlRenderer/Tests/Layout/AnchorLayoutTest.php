<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Geometry\Rect;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Layout\AnchorGeometry;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class AnchorLayoutTest extends TestCase
{
    public function testAnchorSizesAndInsetsUseTheirNamedGeometry()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}#a{anchor-name:--a;width:20.5px;height:10.25px;margin-left:7.5px}#b{anchor-name:--b;width:30.75px;height:5px}.target{position:fixed;position-anchor:--a;left:anchor(left);top:anchor(bottom);width:calc(anchor-size(--b width) + 2.5px);height:anchor-size(height)}</style><div id="a"></div><div id="b"></div><aside class="target"></aside>', new RenderingContext(200, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
        self::assertSame(7.5, $bounds->x);
        self::assertSame(10.25, $bounds->y);
        self::assertSame(33.25, $bounds->width);
        self::assertSame(10.25, $bounds->height);
    }

    public function testMissingAnchorSizeUsesItsFallbackWithinMath()
    {
        $result = (new HtmlRenderer())->render('<aside style="position:fixed;width:calc(anchor-size(--missing width, 10.5px) + 2px);height:anchor-size(height, 3.75px)"></aside>', new RenderingContext(200, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
        self::assertSame(12.5, $bounds->width);
        self::assertSame(3.75, $bounds->height);
    }

    public function testDependentAnchorSizesRelayoutNestedContentBeforeItsConsumers()
    {
        foreach ([false, true] as $reverse) {
            $first = '<aside id="first" style="position:fixed;position-anchor:--a;position-area:bottom span-right;width:anchor-size(width)"><div id="inside" style="anchor-name:--b;width:50%;height:7.5px"></div></aside>';
            $second = '<nav id="second" style="position:fixed;position-anchor:--b;left:anchor(left);top:anchor(bottom);width:anchor-size(width);height:3px"></nav>';
            $result = (new HtmlRenderer())->render('<style>*{margin:0}</style><div style="anchor-name:--a;width:40.5px;height:10px;margin-left:7.5px"></div>'.($reverse ? $second.$first : $first.$second), new RenderingContext(200, 100));
            $document = $result->getDocument();
            $inside = $result->getBounds($document->querySelector('#inside'))[0];
            $secondBounds = $result->getBounds($document->querySelector('#second'))[0];
            self::assertSame(20.25, $inside->width);
            self::assertSame($inside->width, $secondBounds->width);
            self::assertSame($inside->x, $secondBounds->x);
            self::assertSame($inside->y + $inside->height, $secondBounds->y);
        }
    }

    public function testAnchorDependenciesRetainIdentityWhenResizingChangesFragmentPaths()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}</style><div style="anchor-name:--a;width:40.5px;height:10px;margin-left:7.5px"></div><aside id="parent" style="position:fixed;position-anchor:--a;position-area:bottom span-right;width:anchor-size(width);font-size:16px;line-height:16px"><span>AAAA AAAA AAAA</span><nav id="nested" style="position:absolute;position-anchor:--a;left:anchor(left);top:anchor(bottom);width:calc(anchor-size(width) / 2);height:3px"></nav></aside>', new RenderingContext(200, 200));
        $document = $result->getDocument();
        self::assertSame(48.0, $result->getBounds($document->querySelector('#parent'))[0]->height);
        $nested = $result->getBounds($document->querySelector('#nested'))[0];
        self::assertSame(20.25, $nested->width);
        self::assertSame(7.5, $nested->x);
        self::assertSame(10.0, $nested->y);
    }

    public function testPositionAreaUsesNaturalContentInsteadOfViewportInsets()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}button{display:block;padding:0;border:0;min-height:0;anchor-name:--a;width:20px;height:10px;margin-left:7.5px}aside{position:fixed;position-anchor:--a;position-area:bottom span-right;inset:0;font-size:16px;line-height:16px}</style><button></button><aside>ABC</aside>', new RenderingContext(200, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
        self::assertSame(7.5, $bounds->x);
        self::assertSame(10.0, $bounds->y);
        self::assertSame(24.0, $bounds->width);
        self::assertSame(16.0, $bounds->height);
    }

    public function testAnchorCenterAndFlipTacticsUseFractionalGeometry()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}.anchor{anchor-name:--a;width:20.5px;height:10px;margin-left:160.25px;margin-top:80px}aside{position:fixed;position-anchor:--a;width:10px;height:15px}#flip{position-area:bottom right;position-try-fallbacks:flip-block}#center{top:anchor(top);justify-self:anchor-center}</style><div class="anchor"></div><aside id="flip"></aside><aside id="center"></aside>', new RenderingContext(200, 100));
        $document = $result->getDocument();
        $flipped = $result->getBounds($document->querySelector('#flip'))[0];
        self::assertSame(180.75, $flipped->x);
        self::assertSame(65.0, $flipped->y);
        $centered = $result->getBounds($document->querySelector('#center'))[0];
        self::assertSame(165.5, $centered->x);
        self::assertSame(80.0, $centered->y);
    }

    public function testNamedPositionTryUsesTheFirstFittingArea()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}@position-try --left{position-area:center left}.anchor{anchor-name:--a;width:10px;height:10px;margin-left:180px;margin-top:70px}aside{position:fixed;position-anchor:--a;left:anchor(right);top:anchor(top);width:30px;height:10px;position-try-fallbacks:--missing, --left}</style><div class="anchor"></div><aside></aside>', new RenderingContext(200, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
        self::assertSame(150.0, $bounds->x);
        self::assertSame(70.0, $bounds->y);
    }

    public function testFlipTacticsRetainAnchorInsetsAfterSizeResolution()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}.anchor{anchor-name:--a;width:20.5px;height:10px;margin-left:160.25px;margin-top:80px}aside{position:fixed;position-anchor:--a;left:anchor(left);top:anchor(bottom);width:anchor-size(width);height:15px;position-try:flip-block}</style><div class="anchor"></div><aside></aside>', new RenderingContext(200, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
        self::assertSame(160.25, $bounds->x);
        self::assertSame(65.0, $bounds->y);
        self::assertSame(20.5, $bounds->width);
    }

    public function testAnchorSizePreservesGeometryAtLowPhpFloatPrecision()
    {
        $precision = \ini_get('precision');
        ini_set('precision', '3');
        try {
            $result = (new HtmlRenderer())->render('<style>*{margin:0}#a{anchor-name:--a;width:20.123456789012345px;height:10px}aside{position:fixed;position-anchor:--a;width:anchor-size(width);height:1px}</style><div id="a"></div><aside></aside>', new RenderingContext(200, 100));
            $document = $result->getDocument();
            self::assertSame($result->getBounds($document->querySelector('#a'))[0]->width, $result->getBounds($document->querySelector('aside'))[0]->width);
        } finally {
            ini_set('precision', $precision);
        }
    }

    public function testPositionTryOrderRanksAvailableRoomAndRetainsTieOrder()
    {
        foreach ([['normal', 60, 40.0], ['most-width', 60, 70.0], ['most-width', 95, 75.0]] as [$order, $left, $x]) {
            $result = (new HtmlRenderer())->render('<style>*{margin:0}@position-try --left{position-area:center left}@position-try --right{position-area:center right}.anchor{anchor-name:--a;width:10px;height:10px;margin-left:'.$left.'px;margin-top:5px}aside{position:fixed;position-anchor:--a;position-area:top left;width:20px;height:20px;position-try-fallbacks:--left,--right;position-try-order:'.$order.'}</style><div class="anchor"></div><aside></aside>', new RenderingContext(200, 100));
            $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
            self::assertSame($x, $bounds->x, $order);
            self::assertSame(0.0, $bounds->y, $order);
        }
    }

    public function testFallbackDependenciesAreLazyAndCyclesDoNotPoisonOtherLayers()
    {
        foreach ([20 => [30.0, 40.0], 90 => [90.0, 95.0]] as $top => [$firstY, $secondY]) {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><main><button></button><aside></aside><nav></nav></main>');
            $button = $document->querySelector('button');
            $aside = $document->querySelector('aside');
            $nav = $document->querySelector('nav');
            $first = new Box($aside, 'block', ['position' => 'fixed', 'width' => '20px', 'height' => '10px', 'position-area' => 'bottom span-right'], [], null, null, null, null, 1, null, $button, null, ['--later' => $nav], [[['position-anchor' => '--later', 'position-area' => 'bottom span-right'], []]]);
            $second = new Box($nav, 'block', ['position' => 'fixed', 'width' => '10px', 'height' => '5px', 'position-area' => 'bottom span-right'], [], null, null, null, null, 2, null, $aside);
            $root = new Box($document->querySelector('main'), 'block', ['height' => '100px'], [new Box($button, 'block', ['width' => '20px', 'height' => '10px', 'margin-top' => $top.'px']), $first, $second]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
            $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
            self::assertSame($firstY, $result->getBounds($aside)[0]->y);
            self::assertSame($secondY, $result->getBounds($nav)[0]->y);
        }
    }

    public function testPositionTryShorthandCanRankVerticalRoom()
    {
        foreach (['normal' => 10.0, 'most-height' => 40.0, 'most-block-size' => 40.0] as $order => $y) {
            $result = (new HtmlRenderer())->render('<style>*{margin:0}@position-try --top{position-area:top span-left}@position-try --bottom{position-area:bottom span-left}.anchor{anchor-name:--a;width:10px;height:10px;margin-left:180px;margin-top:30px}aside{position:fixed;position-anchor:--a;position-area:bottom right;width:30px;height:20px;position-try:'.$order.' --top,--bottom}</style><div class="anchor"></div><aside></aside>', new RenderingContext(200, 100));
            $bounds = $result->getBounds($result->getDocument()->querySelector('aside'))[0];
            self::assertSame(160.0, $bounds->x, $order);
            self::assertSame($y, $bounds->y, $order);
        }
    }

    public function testNamedAnchorRetainsFractionalGeometryAndUsesItsScope()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}section{anchor-scope:--a}.anchor{anchor-name:--a;width:20px;height:10px;margin-left:7.5px;margin-top:6.25px}.target{position:fixed;position-anchor:--a;position-area:bottom span-right;width:8px;height:4px}</style><section><div class="anchor"></div></section><section><div class="anchor" id="local"></div><aside class="target"></aside></section>', new RenderingContext(200, 100));
        $document = $result->getDocument();
        $anchor = $result->getBounds($document->querySelector('#local'))[0];
        $target = $result->getBounds($document->querySelector('aside'))[0];
        self::assertSame($anchor->x, $target->x);
        self::assertSame($anchor->y + $anchor->height, $target->y);
    }

    public function testNamedAnchorsPreferNearestAncestorThenLastVisibleNameAndRefreshOnMutation()
    {
        $session = (new HtmlRenderer())->createSession('<style>*{margin:0}.anchor{anchor-name:--a;width:20px;height:10px}#first{margin-left:7.5px}#last{margin-left:30px}.target{position:fixed;position-anchor:--a;position-area:bottom span-right;width:8px;height:4px}</style><div id="first" class="anchor"><aside class="target" id="nested"></aside></div><div id="last" class="anchor"></div><section style="display:none"><div class="anchor"></div></section><aside class="target" id="outside"></aside>', new RenderingContext(200, 100));
        $document = $session->getDocument();
        $result = $session->render();
        self::assertSame(7.5, $result->getBounds($document->querySelector('#nested'))[0]->x);
        self::assertSame(30.0, $result->getBounds($document->querySelector('#outside'))[0]->x);
        $document->querySelector('#last')->removeAttribute('class');
        $result = $session->render();
        self::assertSame(7.5, $result->getBounds($document->querySelector('#outside'))[0]->x);
    }

    public function testScopeRestrictsLookupsAndPositionedAnchorsMustPrecedeTheTarget()
    {
        $result = (new HtmlRenderer())->render('<style>*{margin:0}.anchor{anchor-name:--a;width:20px;height:10px;margin-left:30px}.target{position:fixed;top:0;left:0;position-anchor:--a;position-area:bottom span-right;width:8px;height:4px}</style><div class="anchor" id="global"></div><section style="anchor-scope:all"><aside class="target" id="scoped"></aside></section><aside class="target" id="outside"></aside><div class="anchor" style="position:fixed;left:80px;top:50px"></div>', new RenderingContext(200, 100));
        $document = $result->getDocument();
        self::assertSame(0.0, $result->getBounds($document->querySelector('#scoped'))[0]->x);
        self::assertSame(30.0, $result->getBounds($document->querySelector('#outside'))[0]->x);
        self::assertSame(10.0, $result->getBounds($document->querySelector('#outside'))[0]->y);
    }

    public function testNamedAnchorAliasesHaveABoundedIndex()
    {
        $names = implode(', ', array_map(static fn (int $index): string => '--a'.$index, range(0, 99)));
        $html = '<style>.anchor{anchor-name:'.$names.'}aside{position:fixed;position-anchor:--a0;position-area:bottom}</style>'.str_repeat('<div class="anchor"></div>', 1001).'<aside></aside>';
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Named anchors exceed the document index limit.');
        (new HtmlRenderer())->render($html, new RenderingContext(200, 100));
    }

    public function testImplicitAnchorFollowsScrolledTransformedInvokerAndEscapesClip()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><button></button><aside></aside></main>');
        $button = $document->querySelector('button');
        $popover = $document->querySelector('aside');
        $root = new Box($document->querySelector('main'), 'block', ['width' => '100px', 'height' => '30px', 'font-size' => '10px', 'line-height' => '10px', 'overflow' => 'auto', '--html-scroll-y' => '20', 'transform' => 'translateX(10px)'], [
            new Box(null, 'block', ['height' => '40px']),
            new Box($button, 'inline-block', ['width' => '20px', 'height' => '10px']),
            new Box($popover, 'block', ['position' => 'fixed', 'width' => '30px', 'height' => '10px', 'position-area' => 'bottom span-right', 'transform' => 'translateX(2.5px)'], [], null, null, null, null, 1, null, $button),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        $bounds = $result->getBounds($popover)[0];
        self::assertSame(10.0, $bounds->x);
        self::assertSame(30.0, $bounds->y);
        self::assertSame($popover, $result->hitTest(13, 32));
        self::assertNotSame($popover, $result->hitTest(11, 32));
    }

    public function testNestedAnchorsResolveDependenciesAcrossDocumentOrder()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><main><button id="a"></button><aside><button id="b"></button></aside><nav></nav></main>');
        $a = $document->querySelector('#a');
        $b = $document->querySelector('#b');
        $aside = $document->querySelector('aside');
        $nav = $document->querySelector('nav');
        $second = new Box($nav, 'block', ['position' => 'fixed', 'width' => '10px', 'height' => '10px', 'position-area' => 'bottom span-right'], [], null, null, null, null, 2, null, $b);
        $first = new Box($aside, 'block', ['position' => 'fixed', 'width' => '40px', 'height' => '30px', 'position-area' => 'bottom span-right'], [new Box($b, 'block', ['width' => '10px', 'height' => '10px'])], null, null, null, null, 1, null, $a);
        foreach ([[$first, $second], [$second, $first]] as $layers) {
            $root = new Box($document->querySelector('main'), 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box($a, 'block', ['width' => '20px', 'height' => '10px', 'margin-top' => '30px']), ...$layers]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
            $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
            self::assertSame(40.0, $result->getBounds($aside)[0]->y);
            self::assertSame(50.0, $result->getBounds($nav)[0]->y);
            self::assertSame($nav, $result->hitTest(1, 51));
        }
    }

    public function testFloatGeometryPreservesFractionalCenterAndLegacyViewportConstraint()
    {
        self::assertSame([7.5, 7.125], AnchorGeometry::place(new Rect(5.5, 6.25, 10.5, 4.25), 6.5, 2.5, 100, 100, ['center', 'center']));
        self::assertSame([0.0, 0.0], AnchorGeometry::place(new Rect(2, 2, 10, 5), 20, 10, 100, 100, ['top', 'left']));
    }

    public function testMovingALayerDoesNotReplaceAnEarlierPrincipalAnchorFragment()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><main><button id="a"></button><span id="b"></span><aside></aside><nav></nav></main>');
        $a = $document->querySelector('#a');
        $b = $document->querySelector('#b');
        $aside = $document->querySelector('aside');
        $nav = $document->querySelector('nav');
        $root = new Box($document->querySelector('main'), 'block', [], [
            new Box($a, 'block', ['width' => '20px', 'height' => '10px', 'margin-left' => '40px']),
            new Box($b, 'block', ['width' => '10px', 'height' => '5px', 'margin-left' => '7.5px']),
            new Box($aside, 'block', ['position' => 'fixed', 'width' => '40px', 'height' => '30px', 'position-area' => 'bottom span-right'], [new Box($b, 'block', ['width' => '10px', 'height' => '5px'])], null, null, null, null, 1, null, $a),
            new Box($nav, 'block', ['position' => 'fixed', 'width' => '10px', 'height' => '5px', 'position-area' => 'bottom span-right'], [], null, null, null, null, 2, null, $b),
        ]);
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 100);
        $result = new RenderingResult($document, new \SplObjectStorage(), $layout, (new Painter())->paint($layout));
        self::assertSame(40.0, $result->getBounds($aside)[0]->x);
        self::assertSame(7.5, $result->getBounds($nav)[0]->x);
        self::assertSame(15.0, $result->getBounds($nav)[0]->y);
    }
}
