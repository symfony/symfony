<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;

#[RequiresPhpExtension('gd')]
final class SvgProjectionTest extends TestCase
{
    public function testPreparedSvgDescendantsUseTheNativeCascadeAndNoticeSourceEditsBeforeTheCachedReturn()
    {
        $view = new HtmlBuilder()->setDefaultStyles(false)->build('<!doctype html><style>.hot rect{fill:red}.cool rect{fill:blue}</style><svg id="a" class="hot" viewBox="0 0 10 10"><rect width="10" height="10"/></svg><svg id="b" class="cool" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        $document = new \ReflectionProperty(HtmlView::class, 'document')->getValue($view);
        $source = $document->saveHtml();
        $sheet = $view->getStyleSheet();
        $media = new MediaContext();
        $sheet->prepare($view->getRoot(), $media);
        $projection = $sheet->getMirrorProjection();
        $a = $document->querySelector('#a rect');
        $b = $document->querySelector('#b rect');
        self::assertCount(2, $projection['document']->querySelectorAll('svg rect'));
        self::assertSame(['fill' => 'red'], $sheet->svgDeclarations($a, ['fill']));
        self::assertSame(['fill' => 'blue'], $sheet->svgDeclarations($b, ['fill']));
        $mirrorA = $projection['document']->querySelector('#a rect');
        $mirrorB = $projection['document']->querySelector('#b rect');
        self::assertSame($source, $document->saveHtml());
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame($mirrorA, $projection['document']->querySelector('#a rect'));
        $a->setAttribute('style', 'fill:green');
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame(['fill' => 'green'], $sheet->svgDeclarations($a, ['fill']));
        self::assertNotSame($mirrorA, $projection['document']->querySelector('#a rect'));
        self::assertSame($mirrorB, $projection['document']->querySelector('#b rect'));
        $sheet->add('.cool rect{fill:purple}');
        $sheet->prepare($view->getRoot(), $media);
        self::assertSame(['fill' => 'purple'], $sheet->svgDeclarations($b, ['fill']));
    }

    public function testForeignSnapshotUsesOnlyExplicitRulesAndRefreshesForNativeSourceMutations()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><g fill="green"><rect id="a" style="fill:unset"/></g><rect id="b"/></svg>');
        $sheet = CssStyleSheet::fromString(':root{--paint:red}#b{fill:var(--paint)}');
        $before = $document->saveXml();
        $read = $sheet->svgSnapshot($document->documentElement);
        self::assertSame(['fill' => 'green'], $read($document->querySelector('#a'), ['fill']));
        self::assertSame(['fill' => 'red'], $read($document->querySelector('#b'), ['fill']));
        self::assertSame($before, $document->saveXml());
        $document->querySelector('#b')->setAttribute('style', 'fill:blue');
        self::assertSame(['fill' => 'blue'], $sheet->svgSnapshot($document->documentElement)($document->querySelector('#b'), ['fill']));
    }

    public function testRegisteredSvgUsesWidgetClassesAcrossSourceEditsAndMediaChanges()
    {
        $view = new HtmlBuilder()->setDefaultStyles(false)->build('<!doctype html><style>.selected rect{fill:red}@media(min-width:50ch){.selected rect{fill:blue}}</style><svg id="s" class="source" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');
        $document = new \ReflectionProperty(HtmlView::class, 'document')->getValue($view);
        $sheet = $view->getStyleSheet();
        $widget = $view->getById('s');
        $widget->addStyleClass('selected');
        $rect = $document->querySelector('rect');
        $sheet->prepare($view->getRoot(), new MediaContext(20));
        self::assertSame(['fill' => 'red'], $sheet->svgDeclarations($rect, ['fill']));
        $rect->setAttribute('height', '9');
        $sheet->prepare($view->getRoot(), new MediaContext(20));
        self::assertSame(['fill' => 'red'], $sheet->svgDeclarations($rect, ['fill']));
        $sheet->prepare($view->getRoot(), new MediaContext(80));
        self::assertSame(['fill' => 'blue'], $sheet->svgDeclarations($rect, ['fill']));
        $document->querySelector('svg')->setAttribute('fill', 'green');
        $sheet->prepare($view->getRoot(), new MediaContext(80));
        $widget->addStyleClass('extra');
        $sheet->prepare($view->getRoot(), new MediaContext(80));
        self::assertSame('green', $sheet->getMirrorProjection()['document']->querySelector('svg')->getAttribute('fill'));
    }

    public function testForeignCacheRetainsOnlyOneDocumentAndReleaseDocumentsDropsIt()
    {
        $sheet = CssStyleSheet::fromString('rect{fill:red}');
        $first = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        $reference = \WeakReference::create($first);
        $sheet->svgSnapshot($first->documentElement);
        unset($first);
        gc_collect_cycles();
        self::assertNotNull($reference->get());
        $second = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        $sheet->svgSnapshot($second->documentElement);
        gc_collect_cycles();
        self::assertNull($reference->get());
        $reference = \WeakReference::create($second);
        unset($second);
        $sheet->releaseDocuments();
        gc_collect_cycles();
        self::assertNull($reference->get());
    }

    public function testDetachedSvgSnapshotPreservesTheCallersNativeNodes()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>');
        $svg = $document->documentElement->cloneNode(true);
        $sheet = CssStyleSheet::fromString('rect{fill:red}');
        self::assertSame(['fill' => 'red'], $sheet->svgSnapshot($svg)($svg->firstElementChild, ['fill']));
        self::assertNull($svg->parentNode);
        self::assertSame($document, $svg->ownerDocument);
    }

    public function testNaturalSvgViewportHintsPreserveTerminalTextLabels()
    {
        $rendered = FacadeScenario::render('<!doctype html><svg width="160" height="32" viewBox="0 0 160 32"><text x="80" y="20" text-anchor="middle" font-size="16" fill="red">Label</text></svg>', []);
        self::assertStringContainsString('Label', $rendered);
    }

    public function testUnboundFirstPaintReadsCssBeforeProducingPixelsAndKeepsNaturalMeasurements()
    {
        $view = new HtmlBuilder()->setDefaultStyles(false)->build('<!doctype html><style>rect{fill:red}</style><svg id="s" width="40" height="16" viewBox="0 0 40 16"><rect width="40" height="16"/></svg>');
        $widget = $view->getById('s');
        self::assertSame(5, $widget->drawnColumns(80));
        self::assertStringContainsString('255;0;0', implode('', $widget->render(new RenderContext(5, 1))));
        self::assertSame(5, $widget->drawnColumns(80));
    }

    public function testRegisteredSvgKeepsOwnedShadowStylesheetScopes()
    {
        $view = new HtmlBuilder()->setDefaultStyles(false)->build('<!doctype html><style>rect{fill:blue}</style><div><template shadowrootmode="open"><style>rect{fill:red}</style><svg id="s" width="40" height="16"><rect width="40" height="16"/></svg></template></div>');
        $document = new \ReflectionProperty(HtmlView::class, 'document')->getValue($view);
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new MediaContext());
        self::assertSame(['fill' => 'red'], $sheet->svgDeclarations($document->querySelector('rect'), ['fill']));
    }

    public function testForeignImplicitColorLeavesTheUnboundTerminalContextAuthoritative()
    {
        $view = new HtmlBuilder()->setDefaultStyles(false)->build('<!doctype html><svg id="s" width="16" height="16" fill="currentColor"><rect width="16" height="16"/></svg>');
        $output = implode('', $view->getById('s')->render(new RenderContext(2, 1)));
        self::assertStringContainsString('229;229;229', $output);
        self::assertStringNotContainsString('255;255;255', $output);
    }

    public function testForeignProjectionKeepsAuthoredInheritanceAndRelativePaintColors()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg"><g color="red"><rect id="a" fill="currentColor"/><rect id="b" style="color:currentColor" fill="currentColor"/></g><rect id="plain" fill="currentColor"/><rect id="initial" style="color:initial"/></svg>');
        $sheet = new CssStyleSheet();
        $read = $sheet->svgSnapshot($document->documentElement);
        self::assertSame(['color' => 'red', 'fill' => 'currentColor'], $read($document->querySelector('#a'), ['color', 'fill']));
        self::assertSame(['color' => 'red', 'fill' => 'currentColor'], $read($document->querySelector('#b'), ['color', 'fill']));
        self::assertSame(['fill' => 'currentColor'], $read($document->querySelector('#plain'), ['color', 'fill']));
        self::assertSame(['color' => 'initial'], $read($document->querySelector('#initial'), ['color']));
    }
}
