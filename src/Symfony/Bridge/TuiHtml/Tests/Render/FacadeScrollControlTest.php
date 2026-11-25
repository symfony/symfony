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
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeScrollControlTest extends TestCase
{
    public function testGeometryTrialsKeepFocusUaAndOneCommittedPseudoTransitionAcrossTimes(): void
    {
        $time = 0.0;
        $started = 0;
        $sheet = new \Symfony\Bridge\TuiHtml\Css\CssStyleSheet();
        $sheet->setAnimationHandlers(static function () use (&$started): void { ++$started; }, static function () use (&$time): float { return $time; });
        $view = new HtmlBuilder($sheet)->useHtmlRenderer()->build('<style>#s{height:6;overflow:auto;width:20ch}#s::scroll-button(up){content:"U";color:blue;transition:color 1s linear}#s::scroll-button(up):disabled{color:red}input:focus{font-weight:700}</style><div id="s">one<br>two<br>three<br>four<br>five<br>six<br>seven<br>eight</div><input id="field">');
        $view->getById('field')->setFocused(true);
        $outer = new \Symfony\Component\Tui\Widget\ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->setDefaultForeground('#123456');
        $color = static function () use ($view): string {
            foreach ($view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations() as $operation) {
                if ('text' === $operation['type'] && 'U' === $operation['text']) {
                    return \Symfony\Bridge\TuiHtml\Css\Value::color($operation['color'], new \Symfony\Bridge\TuiHtml\Style\MediaContext())->toHex();
                }
            }
            throw new \LogicException('The up control must be painted.');
        };
        $renderer->renderFrame($outer, 40, 15);
        self::assertSame('#cd0000', $color());
        $snapshot = $sheet->getMirrorProjection();
        $result = $view->getRoot()->getPresentation()->getResult();
        self::assertSame('700', $result->getComputedStyle($snapshot['nodes'][$view->getById('field')])['font-weight']);
        self::assertSame('#123456', $result->getComputedStyle($snapshot['nodes'][$view->getById('s')])['color']);
        $time = .5;
        $view->getById('s')->scrollTo(1);
        $renderer->renderFrame($outer, 40, 15);
        self::assertSame('#cd0000', $color());
        $time = 1.0;
        $view->getRoot()->invalidate();
        $renderer->renderFrame($outer, 40, 15);
        self::assertNotSame('#cd0000', $color());
        self::assertNotSame('#0000ee', $color());
        $time = 1.5;
        $view->getRoot()->invalidate();
        $renderer->renderFrame($outer, 40, 15);
        self::assertSame('#0000ee', $color());
        self::assertSame(1, $started);
    }

    public function testNativeViewClickPagesTheActualContainerThroughCoreGeneratedControls(): void
    {
        foreach ([false, true] as $tabs) {
            $html = '<style>#s{height:6;overflow:auto;border:1px solid;width:20ch}#s::scroll-button(up){content:"Up"}#s::scroll-button(down){content:"Down"}</style><div id="s"><p>one</p><p>two</p><p>three</p><p>four</p><p>five</p></div>';
            if ($tabs) {
                $html = '<tui-tabs><section title="First">'.$html.'</section></tui-tabs>';
            }
            $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
            $scroller = $view->getById('s');
            $renderer = new FacadeRenderer(new Renderer());
            $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, $renderer);
            $view->mount($tui);
            $tui->start();
            try {
                $tui->processRender();
                $found = null;
                for ($row = 0; $row < 15; ++$row) {
                    for ($col = 0; $col < 40; ++$col) {
                        if ([$scroller, 'down'] === $renderer->getScrollControlAt($row, $col)) {
                            $found = [$row, $col];
                            break 2;
                        }
                    }
                }
                self::assertNotNull($found);
                self::assertGreaterThan(0, $scroller->getClientHeight());
                self::assertTrue($view->click(...$found));
                self::assertSame(max(1, (int) floor(.85 * $scroller->getClientHeight())), $scroller->getScrollTop());
                $tui->processRender();
                self::assertSame([$scroller, 'down'], $renderer->getScrollControlAt(...$found));
                self::assertNull($tui->getFocus());
            } finally {
                $view->unmount();
                $tui->stop();
            }
        }
    }

    public function testNativeMarkerClickRevealsItsActualTargetAndPreservesFocus(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>#s{height:6;overflow:auto;border:1px solid;width:20ch;scroll-marker-group:after}p{margin:0}p::scroll-marker{content:attr(data-label)}</style><div id="s"><p id="a" data-label="A">one<br>two<br>three</p><p id="b" data-label="B">four<br>five<br>six</p></div>');
        $scroller = $view->getById('s');
        $renderer = new FacadeRenderer(new Renderer());
        $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, $renderer);
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $found = null;
            for ($row = 0; $row < 15; ++$row) {
                for ($col = 0; $col < 40; ++$col) {
                    if ([$scroller, 'marker:1'] === $renderer->getScrollControlAt($row, $col)) {
                        $found = [$row, $col];
                        break 2;
                    }
                }
            }
            self::assertNotNull($found);
            self::assertTrue($view->click(...$found));
            $tui->processRender();
            self::assertGreaterThan(0, $scroller->getScrollTop());
            self::assertNull($tui->getFocus());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testNativeRepeatedSmallScrollsUseCommittedSnapHistoryInsideAndOutsideTabs(): void
    {
        foreach ([false, true] as $tabs) {
            $html = '<style>#s{height:3;overflow:auto;scroll-snap-type:y mandatory}section.slide{height:3;scroll-snap-align:start}</style><div id="s"><section class="slide">one</section><section class="slide">two</section><section class="slide">three</section><section class="slide">four</section></div>';
            if ($tabs) {
                $html = '<tui-tabs><section title="First">'.$html.'</section></tui-tabs>';
            }
            $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
            $scroller = $view->getById('s');
            $renderer = new FacadeRenderer(new Renderer());
            $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, $renderer);
            $view->mount($tui);
            $tui->start();
            try {
                $tui->processRender();
                foreach ([[1, 3], [1, 6], [-1, 3]] as [$delta, $expected]) {
                    $scroller->scrollBy($delta);
                    $tui->requestRender();
                    $tui->processRender();
                    self::assertSame($expected, $scroller->getScrollTop());
                }
            } finally {
                $view->unmount();
                $tui->stop();
            }
        }
    }
}
