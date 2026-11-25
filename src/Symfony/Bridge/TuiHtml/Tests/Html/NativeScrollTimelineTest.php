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
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

class NativeScrollTimelineTest extends TestCase
{
    public function testCoreTimelinePreservesTheExistingOverflowDetectionWithoutClockFrames()
    {
        foreach ([['hidden', 'height:2', '<div style="height:2"><div>one</div><div>two</div><div>three</div><div>four</div></div>', 'block'], ['auto', 'max-height:4', '<div>one</div>', 'none'], ['auto', 'max-height:2', '<div>one<br>two<br>three<br>four</div>', 'block']] as [$overflow, $size, $content, $expected]) {
            $sheet = new CssStyleSheet();
            $tui = new Tui(null, new VirtualTerminal(50, 20));
            try {
                $html = '<style>@keyframes DetectOverflow{from,to{--More:block}}#box{overflow:'.$overflow.';'.$size.';--More:none;animation:linear DetectOverflow;animation-timeline:scroll(self)}#more{display:var(--More)}</style><div id="box">'.$content.'<p id="more">More</p></div>';
                $view = new HtmlBuilder($sheet)->setDefaultStyles(false)->build($html)->mount($tui);
                $tui->start();
                $tui->processRender();
                self::assertSame($expected, $sheet->getComputedValue($view->getById('more'), 'display'));
                self::assertFalse($sheet->isAnimating());
                if ('block' === $expected && 'auto' === $overflow) {
                    $view->getById('box')->scrollTo(1);
                    $tui->requestRender();
                    $tui->processRender();
                    self::assertSame('block', $sheet->getComputedValue($view->getById('more'), 'display'));
                    self::assertFalse($sheet->isAnimating());
                }
            } finally {
                $tui->stop();
            }
        }
    }

    public function testNearestAndRootTimelinesFollowTheExistingTuiScrollContainers()
    {
        foreach (['nearest', 'root'] as $kind) {
            $sheet = new CssStyleSheet();
            $tui = new Tui(null, new VirtualTerminal(50, 20));
            try {
                $selector = 'root' === $kind ? ':root' : '#box';
                $html = '<style>@keyframes fade{from{opacity:0}to{opacity:1}}'.$selector.'{height:2;overflow:auto}#target{opacity:0;animation:fade linear;animation-timeline:scroll('.$kind.')}</style><div id="box"><p id="target">one<br>two<br>three<br>four</p></div>';
                if ('root' === $kind) {
                    $html = str_replace('<p id="target">', '<div style="overflow:auto;height:4"><p id="target">', $html);
                    $html = str_replace('</p></div>', '</p></div></div>', $html);
                }
                $view = new HtmlBuilder($sheet)->setDefaultStyles(false)->build($html);
                if ('root' === $kind) {
                    $sheet->setScope($view->getById('box'));
                }
                $view->mount($tui);
                $tui->start();
                $tui->processRender();
                self::assertSame('0', $sheet->getComputedValue($view->getById('target'), 'opacity'));
                $scroller = $view->getById('box');
                $scroller->scrollTo(1);
                $tui->requestRender();
                $tui->processRender();
                self::assertSame('0.5', $sheet->getComputedValue($view->getById('target'), 'opacity'), $kind);
                self::assertFalse($sheet->isAnimating());
            } finally {
                $tui->stop();
            }
        }
    }

    public function testNamedAndViewTimelinesPreserveNativeScrollOffsets()
    {
        foreach (['view()' => ['0.4063', '0.5938'], '--subject' => ['0.4063', '0.5938'], '--page' => ['.2', '0.5'], 'view(block 2 2)' => ['.2', '0.5']] as $timeline => $expected) {
            $sheet = new CssStyleSheet();
            $tui = new Tui(null, new VirtualTerminal(50, 20));
            try {
                $prefix = 'view(block 2 2)' === $timeline ? '<div>One</div><div>Two</div><div>Three</div>' : '<div>Before</div>';
                $html = '<style>@keyframes fade{from{opacity:.2}to{opacity:.8}}#wrap{timeline-scope:--subject,--page}#box{height:2;overflow:auto;scroll-timeline:--page block}#target{view-timeline:--subject block;opacity:0;animation:fade linear both;animation-timeline:'.$timeline.'}</style><div id="wrap"><div id="box">'.$prefix.'<div id="target">Subject</div><div>After</div><div>End</div></div></div>';
                $view = new HtmlBuilder($sheet)->setDefaultStyles(false)->build($html)->mount($tui);
                $tui->start();
                $tui->processRender();
                self::assertSame($expected[0], $sheet->getComputedValue($view->getById('target'), 'opacity'), $timeline);
                $view->getById('box')->scrollTo(1);
                $tui->requestRender();
                $tui->processRender();
                self::assertSame($expected[1], $sheet->getComputedValue($view->getById('target'), 'opacity'), $timeline);
                self::assertFalse($sheet->isAnimating());
            } finally {
                $tui->stop();
            }
        }
    }

    public function testAZeroHeightNativeViewRemainsInactiveWithoutSubjectGeometry()
    {
        $sheet = new CssStyleSheet();
        $tui = new Tui(null, new VirtualTerminal(50, 20));
        try {
            $view = new HtmlBuilder($sheet)->setDefaultStyles(false)->build('<style>@keyframes fade{from{opacity:.2}to{opacity:.8}}#box{height:0;overflow:auto}#target{opacity:0;animation:fade linear both;animation-timeline:view()}</style><div id="box"><div id="target">Subject</div><div>End</div></div>')->mount($tui);
            $tui->start();
            $tui->processRender();
            self::assertSame('0', $sheet->getComputedValue($view->getById('target'), 'opacity'));
        } finally {
            $tui->stop();
        }
    }
}
