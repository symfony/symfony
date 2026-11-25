<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Vector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Vector\SvgStyle;

final class SvgStyleTest extends TestCase
{
    public function testGroupOpacityAndNonInheritedEffectsRemainSeparateFromInheritedPaint()
    {
        $own = ['opacity' => '50%', 'fill' => 'red', 'clip-path' => 'url(#own)'];
        $parent = ['opacity' => '.4', 'stroke' => 'blue', 'clip-path' => 'url(#parent)', 'mask' => 'url(#mask)', 'filter' => 'blur(2px)'];
        self::assertSame(['opacity' => '0.2', 'fill' => 'red', 'stroke' => 'blue'], SvgStyle::inherit($own, $parent));
        self::assertSame(['fill' => 'red', 'stroke' => 'blue'], SvgStyle::inheritedPaint($own, ['stroke' => 'blue']));
    }

    public function testPresentationStyleAndCallbackDeclarationsKeepTheirPriority()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg><rect fill="blue" style="fill:red;marker:url(#all);marker-end:none"/></svg>');
        $rect = $document->querySelector('rect');
        self::assertSame(['fill' => 'red'], SvgStyle::declared($rect, ['fill']));
        self::assertSame(['fill' => 'green'], SvgStyle::declared($rect, ['fill'], static fn (): array => ['fill' => 'green']));
        $properties = SvgStyle::properties($rect);
        self::assertSame('url(#all)', $properties['marker-start']);
        self::assertSame('none', $properties['marker-end']);
        self::assertArrayNotHasKey('marker', $properties);
    }

    public function testQuotedSemicolonCannotBecomeAnotherInlineDeclaration()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg><rect style="fill:url(&quot;#paint;stroke:red&quot;);color:blue"/></svg>');
        self::assertSame(['fill' => 'url("#paint;stroke:red")', 'color' => 'blue'], SvgStyle::declared($document->querySelector('rect'), ['fill', 'stroke', 'color']));
    }

    public function testGeometryDeclarationsDoNotReplaceAttributesWithUnsupportedExpressions()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg><circle cx="5" r="1" style="cx:12px;r:calc(1px + 2px)"/></svg>');
        self::assertSame(['cx' => '12px'], SvgStyle::geometryDeclared($document->querySelector('circle'), 'circle'));
    }

    public function testSerializationGuardDoesNotTrustPlainValuesWithMalformedPropertyNames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg><rect/></svg>');
        $rect = $document->querySelector('rect');
        self::assertSame(['fill' => 'red'], SvgStyle::serializedStyle($rect, ['fill' => 'red', 'color;stroke' => 'blue', 'color!important' => 'blue', '\\63olor' => 'blue', 'fíll' => 'blue']));
        self::assertSame([], SvgStyle::serializedStyle($rect, ['fill' => 'red!important']));
        self::assertSame(['fill' => 'red'], SvgStyle::serializedStyle($rect, ['fill' => 'red/* comment */']));
        self::assertSame(['fill' => 'url("#paint;stroke:red")'], SvgStyle::serializedStyle($rect, ['fill' => 'url("#paint;stroke:red")']));
    }

    public function testSerializedStyleUsesResolvedValuesWithoutChangingNativeNodes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg><rect fill="blue" style="fill:var(--paint);--paint:red;stroke:none"/></svg>');
        $before = $document->saveHtml();
        self::assertSame(['fill' => 'green', '--paint' => 'red', 'stroke' => 'none', 'font-size' => '7px'], SvgStyle::serializedStyle($document->querySelector('rect'), ['fill' => 'green', 'font-size' => '7px', 'margin' => '20px']));
        self::assertSame($before, $document->saveHtml());
    }

    public function testSvgViewportPresentationLengthsUseCssPixelsWithoutChangingAttributes()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg" width="160" height="96"><rect width="20"/></svg>');
        $hints = [];
        foreach (SvgStyle::presentation($document->documentElement) as $declaration) {
            $hints[$declaration->property] = $declaration->value;
        }
        self::assertSame(['width' => '160px', 'height' => '96px'], $hints);
        self::assertSame('160', $document->documentElement->getAttribute('width'));
        self::assertSame('20', SvgStyle::presentation($document->documentElement->firstElementChild)[0]->value);
    }

    public function testStandaloneInitialColorRetainsComputedCanvasText()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div style="color:red"><svg><rect id="a" style="color:initial"/></svg></div>');
        $result = new HtmlRenderer()->render($document, new RenderingContext(100, 100, null, null, null, null, ['prefers-color-scheme' => 'dark']));
        $rect = $document->querySelector('rect');
        self::assertInstanceOf(\Dom\Element::class, $rect);
        self::assertSame('#ffffff', $result->getComputedStyle($rect)['color']);
    }
}
