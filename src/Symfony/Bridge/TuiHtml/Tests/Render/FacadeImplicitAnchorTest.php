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
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\ContainerWidget;

class FacadeImplicitAnchorTest extends TestCase
{
    public function testNativeAnchorsUseTheExistingDocumentLayoutState()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>*{margin:0}#anchor{width:4ch;height:1lh;margin-left:5ch;margin-top:2lh}#popup{position:absolute;position-area:bottom;width:4ch;height:1lh}</style><div id="anchor">A</div><div id="popup">Menu</div>');
        $outer = new ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
        $anchor = $view->getById('anchor');
        $popup = $view->getById('popup');
        $renderer->setImplicitAnchor($popup, $anchor);
        $renderer->renderFrame($outer, 30, 12);
        $anchorRect = $renderer->getWidgetRect($anchor);
        $popupRect = $renderer->getWidgetRect($popup);
        self::assertNotNull($anchorRect);
        self::assertNotNull($popupRect);
        self::assertSame($anchorRect->row + $anchorRect->rows, $popupRect->row);
        self::assertSame($anchorRect->col, $popupRect->col);
        $projection = $view->getStyleSheet()->getMirrorProjection();
        $native = $projection['nodes'][$popup];
        $renderer->setImplicitAnchor($popup, null);
        $renderer->renderFrame($outer, 30, 12);
        self::assertSame($native, $view->getStyleSheet()->getMirrorProjection()['nodes'][$popup]);
        self::assertNotSame($popupRect->row, $renderer->getWidgetRect($popup)->row);
    }

    public function testNativePopoverInputKeepsItsInvokerAsDocumentAnchor()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<style>*{margin:0}button{margin-left:3ch;margin-top:2lh}#popup{inset:auto;margin:0;width:6ch;height:1lh;position-area:bottom span-right}</style><button id="show" popovertarget="popup">Show</button><div id="popup" popover>Menu</div>');
        $renderer = new FacadeRenderer(new Renderer());
        $tui = new HtmlTui(null, new VirtualTerminal(30, 12), null, null, $renderer);
        $view->mount($tui);
        $tui->setFocus($view->getById('show'));
        try {
            $tui->start();
            $tui->processRender();
            $tui->handleInput("\r");
            $tui->processRender();
            $anchor = $renderer->getWidgetRect($view->getById('show'));
            $popup = $renderer->getWidgetRect($view->getById('popup'));
            self::assertNotNull($anchor);
            self::assertNotNull($popup);
            self::assertTrue($view->getById('popup')->hasState('popover-open'));
            self::assertSame($anchor->col, $popup->col);
            self::assertSame($anchor->row + $anchor->rows, $popup->row);
        } finally {
            $tui->stop();
            $view->unmount();
        }
    }
}
