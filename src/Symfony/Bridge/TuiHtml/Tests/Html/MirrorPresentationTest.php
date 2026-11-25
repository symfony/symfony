<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Content\NativeContentAdapter;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Html\MirrorPresentation;
use Symfony\Bridge\TuiHtml\Render\DisplayListRenderer;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Html\BoxBuilder;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\Figlet\FontRegistry;

class MirrorPresentationTest extends TestCase
{
    public function testMixedNativeGroupsEnrichTextWithoutChangingSelectorTopology(): void
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><div id="d">A<span>B</span><input id="i">C<strong>D</strong><p id="p">block</p>E</div><p id="empty"></p>');
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new MediaContext(40, 10));
        $snapshot = $sheet->getMirrorProjection();
        $document = $snapshot['document'];
        $elements = iterator_to_array($document->querySelectorAll('*'), false);
        $empty = iterator_to_array($document->querySelectorAll(':empty'), false);
        $projector = new MirrorPresentation();
        $projection = $projector->project($snapshot, $view->getRoot());
        $parent = $snapshot['nodes'][$view->getById('d')];
        self::assertSame('ABCDblockE', $parent->textContent);
        self::assertSame($elements, iterator_to_array($document->querySelectorAll('*'), false));
        self::assertSame($empty, iterator_to_array($document->querySelectorAll(':empty'), false));
        self::assertNull($projection['groups'][$parent][0]['children'][0]['children'][0]->previousSibling);
        self::assertSame($view->getById('i'), $projection['replacements'][$snapshot['nodes'][$view->getById('i')]]);
        $text = $parent->firstChild;
        $sheet->prepare($view->getRoot(), new MediaContext(40, 10));
        $projector->project($sheet->getMirrorProjection(), $view->getRoot());
        self::assertSame($text, $parent->firstChild);
        self::assertSame('ABCDblockE', $parent->textContent);
        $paragraph = $view->getById('p');
        $paragraph->setText('changed');
        $sheet->prepare($view->getRoot(), new MediaContext(40, 10));
        $projector->project($sheet->getMirrorProjection(), $view->getRoot());
        self::assertSame('ABCDchangedE', $parent->textContent);
        self::assertSame($paragraph, $view->getById('p'));
        $paragraph->setText('');
        $sheet->prepare($view->getRoot(), new MediaContext(40, 10));
        $projector->project($sheet->getMirrorProjection(), $view->getRoot());
        self::assertTrue($snapshot['nodes'][$paragraph]->matches(':empty'));
    }

    public function testNativeMovesKeepActualMirrorIdentitiesAndTextOrder(): void
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><div id="d">A<input id="i">B<p id="p">block</p>E</div>');
        $sheet = $view->getStyleSheet();
        $media = new MediaContext(40, 10);
        $projector = new MirrorPresentation();
        $sheet->prepare($view->getRoot(), $media);
        $snapshot = $sheet->getMirrorProjection();
        $projector->project($snapshot, $view->getRoot());
        $parent = $view->getById('d');
        $row = $parent->all()[0];
        $input = $snapshot['nodes'][$view->getById('i')];
        $parent->remove($row);
        $parent->add($row);
        $sheet->prepare($view->getRoot(), $media);
        $after = $sheet->getMirrorProjection();
        $projector->project($after, $view->getRoot());
        self::assertSame($input, $after['nodes'][$view->getById('i')]);
        self::assertSame('blockEAB', $after['nodes'][$parent]->textContent);
    }

    public function testPreparedMirrorUsesCoreBoxesLayoutAndPaintingWithLiveNativeLeaf(): void
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><style>p{margin:0}input{width:10ch}</style><div>A<span>B</span><input id="i" value="old">C<strong>D</strong><p>block</p>E</div>');
        $native = $view->getById('i');
        $native->setValue('fresh');
        $native->beforeRender();
        $media = new MediaContext(40, 10);
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), $media);
        $snapshot = $sheet->getMirrorProjection();
        $projection = new MirrorPresentation()->project($snapshot, $view->getRoot());
        $metrics = new TerminalTextMeasurer();
        $constraints = new TerminalLayoutMetrics($metrics);
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle('monospace', 16, 16), $metrics);
        $states = [];
        foreach ($snapshot['document']->querySelectorAll('[data-html-renderer-state]') as $element) {
            $states[] = [$element, explode(' ', $element->getAttribute('data-html-renderer-state'))];
        }
        $styles = $resolver->resolve($snapshot['document'], 320, 160, $states, null, 0, null, $snapshot['stylesheet']);
        $providers = new \SplObjectStorage();
        foreach ($projection['replacements'] as $element) {
            $widget = $projection['replacements'][$element];
            $providers[$element] = new NativeContentAdapter(\WeakReference::create($widget), $widget->getRenderRevision(), $styles[$element], $metrics, new FontRegistry(), $media);
        }
        $box = new BoxBuilder($styles, $resolver, new ResourceCollection(new ResourceLoader()), new \SplObjectStorage(), static fn ($element) => $providers[$element] ?? null, $projection['groups'])->build($projection['root']);
        $fragment = new LayoutEngine($metrics, $constraints)->layout($box, 320, 160);
        $display = new Painter($metrics, null, $constraints)->paint($fragment);
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), new DisplayListRenderer($metrics, $media)->render($display, 40, 10));
        $text = implode('', $lines);
        self::assertStringContainsString('fresh', $text);
        self::assertStringContainsString('block', $text);
        self::assertStringContainsString('E', $text);
        self::assertSame($native, $view->getById('i'));
        self::assertSame('old', $snapshot['nodes'][$native]->getAttribute('value'));
        self::assertStringNotContainsString('Symfony', json_encode($display->getOperations()));
    }
}
