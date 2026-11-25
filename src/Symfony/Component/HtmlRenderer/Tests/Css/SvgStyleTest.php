<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\CssRule;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class SvgStyleTest extends TestCase
{
    public function testPresentationAttributesAndCssWideKeywordsUseTheNativeSvgNodes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>rect.reset{fill:initial;opacity:initial;visibility:initial} rect.inherit{fill:unset} text.authored{font-size:11px}</style><svg color="red"><g fill="green"><rect class="inherit"/><rect class="reset" fill="red" opacity=".2" visibility="hidden"/><rect class="current" fill="currentColor"/></g><text font-size="7">Small</text><text class="authored" font-size="7">Authored</text></svg>');
        $source = $document->saveHtml();
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('green', $styles[$document->querySelector('.inherit')]['fill']);
        self::assertSame('black', $styles[$document->querySelector('.reset')]['fill']);
        self::assertSame('1', $styles[$document->querySelector('.reset')]['opacity']);
        self::assertSame('visible', $styles[$document->querySelector('.reset')]['visibility']);
        self::assertSame('red', $styles[$document->querySelector('.current')]['color']);
        self::assertSame('7px', $styles[$document->querySelector('text')]['font-size']);
        self::assertSame('11px', $styles[$document->querySelector('.authored')]['font-size']);
        self::assertSame($source, $document->saveHtml());
    }

    public function testPresentationAttributeValuesCannotInjectDeclarations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><svg><rect fill="red;color:blue!important"/></svg><div fill="red"></div>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        self::assertSame('#000000', $styles[$document->querySelector('rect')]['color']);
        self::assertArrayNotHasKey('fill', $styles[$document->querySelector('div')]);
    }

    public function testMarkerShorthandResetsInheritedLonghandsBeforeCascadeCompetition()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>g{marker:url(#tip)}path{marker:none;marker-end:url(#end)}path.reset{marker-start:url(#start);marker:none}</style><svg><g><path/><path class="reset"/></g></svg>');
        $styles = new StyleResolver()->resolve($document, 400, 200);
        $first = $styles[$document->querySelector('path')];
        self::assertSame('none', $first['marker-start']);
        self::assertSame('none', $first['marker-mid']);
        self::assertSame('url(#end)', $first['marker-end']);
        $reset = $styles[$document->querySelector('.reset')];
        foreach (['marker-start', 'marker-mid', 'marker-end'] as $property) {
            self::assertSame('none', $reset[$property]);
        }
    }

    public function testNativeXmlSvgDocumentUsesTheSameCascadeAndPreservesRootIdentity()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg" color="red"><style>:root{--paint:green}rect{fill:var(--paint)}text{font-size:11px}</style><g stroke="blue"><rect fill="black"/><text font-size="7">Text</text></g></svg>');
        $source = $document->saveXml();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        self::assertSame('green', $styles[$document->querySelector('rect')]['fill']);
        self::assertSame('blue', $styles[$document->querySelector('rect')]['stroke']);
        self::assertSame('red', $styles[$document->documentElement]['color']);
        self::assertSame('11px', $styles[$document->querySelector('text')]['font-size']);
        self::assertSame($source, $document->saveXml());
    }

    public function testCaseSensitiveSvgNamesMatchWithinXmlAndMixedHtmlDocuments()
    {
        $xml = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><style>feFlood{flood-color:red}feflood{stroke:blue}</style><filter><feFlood/></filter></svg>');
        $styles = new StyleResolver()->resolve($xml, 400, 200);
        $flood = $xml->querySelector('feFlood');
        self::assertSame('red', $styles[$flood]['flood-color']);
        self::assertArrayNotHasKey('stroke', $styles[$flood]);
        $html = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>DIV.outer feFlood{flood-color:red}div FEFLOOD{stroke:blue}DIV > P{color:green}</style><div class="outer"><div><svg><filter><feFlood data-html-renderer-html=""/></filter></svg></div><p>HTML</p></div>');
        \Symfony\Component\HtmlRenderer\Vector\SvgNames::normalize($html->querySelector('svg'));
        $source = $html->saveHtml();
        $styles = new StyleResolver()->resolve($html, 400, 200);
        $flood = $html->querySelector('feFlood');
        self::assertSame('red', $styles[$flood]['flood-color']);
        self::assertArrayNotHasKey('stroke', $styles[$flood]);
        self::assertSame('green', $styles[$html->querySelector('p')]['color']);
        self::assertSame($source, $html->saveHtml());
        $parser = new CssParser();
        $compiled = array_map(static fn (CssRule $rule): CssRule => new CssRule($rule->selector->forNativeTree(), $rule->declarations, $rule->media, $rule->order), $parser->parse($html->querySelector('style')->textContent));
        $styles = new StyleResolver()->resolve($html, 400, 200, [], null, 0, null, new ParsedStylesheet($compiled));
        self::assertSame('red', $styles[$flood]['flood-color']);
    }
}
