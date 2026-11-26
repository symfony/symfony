<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Widget\HtmlRootWidget;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeFrameTest extends TestCase
{
    public function testTerminalFrameFitIsUserAgentPolicyBelowAuthoredWidthConstraints()
    {
        foreach ([1, 2] as $density) {
            foreach (['' => 35, 'max-width:none' => 38, 'min-width:40ch' => 40] as $declaration => $expected) {
                $view = new HtmlBuilder()->useHtmlRenderer()->build('<iframe id="frame" style="width:38ch;'.$declaration.'" srcdoc="Child"></iframe>');
                $outer = new ContainerWidget();
                $outer->add($view->getRoot());
                $renderer = new FacadeRenderer(new Renderer());
                $renderer->setMediaContext(new \Symfony\Bridge\TuiHtml\Style\MediaContext()->withCellSize(new \Symfony\Bridge\TuiHtml\Style\CellSize(8 * $density, 16 * $density)));
                $renderer->renderFrame($outer, 35, 15);
                $node = $view->getStyleSheet()->getMirrorProjection()['nodes'][$view->getById('frame')];
                self::assertSame((float) ($expected * 8 * $density), $view->getRoot()->getPresentation()->getResult()->getBounds($node)[0]->width, $declaration);
            }
        }
    }

    public function testNestedScopesKeepRealWidgetRectsAndExposeContentExtent()
    {
        $html = '<p style="margin:0">'.str_repeat('Line<br>', 12).'</p><input id="deep">';
        for ($depth = 0; $depth < 3; ++$depth) {
            $html = '<iframe id="frame" style="width:20ch;height:5lh;overflow:auto" srcdoc="'.htmlspecialchars($html).'"></iframe>';
        }
        $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->renderFrame($outer, 60, 25);
        $leaf = $view;
        for ($depth = 0; $depth < 3; ++$depth) {
            $leaf = $leaf->getFrames()[0];
            self::assertInstanceOf(HtmlRootWidget::class, $leaf->getRoot());
            self::assertNotNull($renderer->getWidgetRect($leaf->getRoot()));
        }
        $parent = $view->getFrames()[0]->getFrames()[0];
        $frame = $parent->getById('frame');
        $node = $parent->getStyleSheet()->getMirrorProjection()['nodes'][$frame];
        $geometry = $parent->getRoot()->getPresentation()->getResult()->getScrollGeometry($node);
        self::assertGreaterThan(0, $geometry->maxY);
        self::assertNotNull($renderer->getWidgetRect($leaf->getById('deep')));
        $renderer->reveal($leaf->getById('deep'));
        self::assertGreaterThan(0, $frame->getScrollTop());
        $renderer->renderFrame($outer, 60, 25);
        $rect = $renderer->getWidgetRect($leaf->getById('deep'));
        self::assertTrue($rect->contains($rect->row, $rect->col), var_export([$rect, $renderer->getWidgetRect($view->getRoot()), $renderer->getWidgetRect($view->getFrames()[0]->getRoot()), $renderer->getWidgetRect($parent->getRoot()), $frame->getScrollTop()], true));
    }

    public function testNestedFieldsKeepNativeFocusEventsAndRemount()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<input id="outer"><iframe style="width:20ch;height:5lh" srcdoc="&lt;input id=inner accesskey=k&gt;"></iframe>');
        $child = $view->getFrames()[0];
        $field = $child->getById('inner');
        $changes = [];
        $field->on(\Symfony\Component\Tui\Event\ChangeEvent::class, static function ($event) use (&$changes): void { $changes[] = $event->getTarget(); });
        $tui = new \Symfony\Component\Tui\Tui(null, new \Symfony\Component\Tui\Terminal\VirtualTerminal(40, 15), null, null, new FacadeRenderer(new Renderer()));
        for ($mount = 0; $mount < 2; ++$mount) {
            $view->mount($tui);
            $tui->start();
            try {
                $tui->processRender();
                $tui->setFocus($view->getById('outer'));
                $tui->getFocusManager()->focusNext();
                self::assertSame($field, $tui->getFocus());
                $tui->handleInput('x');
                $tui->processRender();
                self::assertNotNull($tui->getWidgetRect($field));
                self::assertSame($field, $changes[$mount]);
                $tui->setFocus($view->getById('outer'));
                $tui->handleInput("\x1bk");
                self::assertSame($field, $tui->getFocus());
            } finally {
                $view->unmount();
                $tui->stop();
            }
        }
    }

    public function testNestedDocumentUsesIndependentCoreProjectionAndAllocatedViewport()
    {
        $html = '<style>p{color:red}</style><iframe id="frame" style="width:20ch;height:5lh" srcdoc="'.htmlspecialchars('<style>p{margin:0;color:blue}@media(width < 15ch){p{color:green}}</style><p id="inner">Child</p>').'"></iframe><p id="after">After</p>';
        $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
        $child = $view->getFrames()[0];
        self::assertInstanceOf(HtmlRootWidget::class, $child->getRoot());
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->renderFrame($outer, 40, 15);
        $node = $child->getStyleSheet()->getMirrorProjection()['nodes'][$child->getById('inner')];
        self::assertSame('blue', $child->getRoot()->getPresentation()->getResult()->getComputedStyle($node)['color']);
        self::assertNotNull($renderer->getWidgetRect($child->getById('inner')));
        self::assertNotNull($renderer->getWidgetRect($view->getById('after')));
        self::assertSame([], $view->getStyleSheet()->getStyles($child->getById('inner')));
        $view->getById('frame')->setStyle(new \Symfony\Component\Tui\Style\Style()->withMaxColumns(12));
        $renderer->renderFrame($outer, 40, 15);
        self::assertSame('green', $child->getRoot()->getPresentation()->getResult()->getComputedStyle($node)['color']);
        $revision = $view->getRoot()->getRenderRevision();
        $renderer->renderFrame($outer, 40, 15);
        self::assertSame($revision, $view->getRoot()->getRenderRevision());
        self::assertSame($node, $child->getStyleSheet()->getMirrorProjection()['nodes'][$child->getById('inner')]);
        $view->getById('frame')->setStyle(new \Symfony\Component\Tui\Style\Style()->withHidden(true));
        $renderer->renderFrame($outer, 40, 15);
        self::assertFalse($renderer->allowsFocus($child->getById('inner')));
    }
}
