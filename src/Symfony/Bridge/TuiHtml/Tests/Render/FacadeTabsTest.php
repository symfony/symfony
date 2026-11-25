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
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\TabChangeEvent;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeTabsTest extends TestCase
{
    public function testNativeTabChromeKeepsCoreHtmlBodyLayoutInAllPositions(): void
    {
        foreach (['top', 'bottom', 'left', 'right'] as $position) {
            $html = '<style>p{margin:0;color:green}</style><tui-tabs position="'.$position.'"><section title="First"><p>First <b>body</b></p></section><section title="Second">Second body</section></tui-tabs>';
            self::assertSame(TerminalCellSnapshot::normalize(FacadeScenario::render($html, ['columns' => 40, 'rows' => 15]), 40, 15, [229, 229, 229], [0, 0, 0]), TerminalCellSnapshot::normalize(FacadeScenario::render($html, ['columns' => 40, 'rows' => 15], true), 40, 15, [229, 229, 229], [0, 0, 0]), $position);
        }
    }

    public function testBodyLinksAndNestedFramesUseTheChromeContentOrigin(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<tui-tabs position="left"><section title="First"><p id="link" style="margin:0"><a href="/target">Link</a></p><iframe style="width:15ch;height:4lh" srcdoc="&lt;input id=inner&gt;"></iframe></section></tui-tabs>');
        $outer = new \Symfony\Component\Tui\Widget\ContainerWidget();
        $outer->add($view->getRoot());
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->renderFrame($outer, 40, 15);
        $link = $view->getById('link');
        $rect = $renderer->getWidgetRect($link);
        self::assertNotNull($rect);
        self::assertGreaterThan(0, $rect->col);
        self::assertSame('tui-link-'.spl_object_id($link).'-0', $renderer->getLinkAt($rect->row, $rect->col));
        $field = $view->getFrames()[0]->getById('inner');
        $fieldRect = $renderer->getWidgetRect($field);
        self::assertNotNull($fieldRect);
        self::assertGreaterThan($rect->col, $fieldRect->col);
    }

    public function testActiveBodyUsesTheActualNativeFocusEventsAndLifecycle(): void
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<tui-tabs id="tabs"><section title="First"><input id="first"></section><section title="Second"><input id="second"></section></tui-tabs>');
        $tabs = $view->getById('tabs');
        $first = $view->getById('first');
        $find = static function ($widget) use (&$find) {
            if ('second' === $widget->getId()) {
                return $widget;
            }
            foreach ($widget instanceof \Symfony\Component\Tui\Widget\ParentInterface ? $widget->all() : [] as $child) {
                if (null !== $found = $find($child)) {
                    return $found;
                }
            }

            return null;
        };
        $second = $find($tabs->getTabs()[1]->getContent());
        $changes = $switches = [];
        $second->on(ChangeEvent::class, static function ($event) use (&$changes): void { $changes[] = $event->getTarget(); });
        $tabs->on(TabChangeEvent::class, static function ($event) use (&$switches): void { $switches[] = $event->getTarget(); });
        $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            self::assertNotNull($tui->getWidgetRect($first));
            self::assertNull($tui->getWidgetRect($second));
            $tui->setFocus($tabs);
            $tui->handleInput("\x1b[C");
            $tui->processRender();
            self::assertSame([$tabs], $switches);
            self::assertNull($first->getContext());
            self::assertNotNull($second->getContext());
            self::assertNotNull($tui->getWidgetRect($second));
            $tui->setFocus($second);
            $tui->handleInput('x');
            $tui->processRender();
            self::assertSame([$second], $changes);
            self::assertSame('x', $second->getValue());
            self::assertSame($second, $tui->getFocus());
        } finally {
            $view->unmount();
            $tui->stop();
        }
        self::assertNull($second->getContext());
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            self::assertSame(1, $tabs->getActiveTabIndex());
            self::assertNotNull($tui->getWidgetRect($second));
            self::assertSame('x', $second->getValue());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }
}
