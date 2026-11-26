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
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeInlineFlowTest extends TestCase
{
    public function testGeneratedInlineRowsPreserveNativeGlueAndPunctuation()
    {
        foreach (['Hello <button>world</button>!', '<button>A</button><button>B</button>', '<button>A</button> <button>B</button>', 'A <span style="color:red">red label</span> <button>go</button>!'] as $content) {
            $this->assertNativeEqual('<p style="margin:0">'.$content.'</p>', 40);
        }
    }

    public function testGlueDropsWhenTheInlineControlMovesToTheNextLine()
    {
        foreach (['Hello <button>world</button>!', '<button>A</button> <button>B</button>', 'A <button>B</button> C'] as $content) {
            foreach ([10, 12] as $columns) {
                $this->assertNativeEqual('<p style="margin:0">'.$content.'</p>', $columns);
            }
        }
    }

    public function testExplicitFlexAndPreservedWhitespaceKeepTheirExistingPolicies()
    {
        $this->assertNativeEqual('<p style="margin:0;display:flex;gap:1ch">Label <button>B</button></p>', 40);
        $this->assertNativeEqual('<p style="margin:0;white-space:pre">A <button>B</button> C</p>', 40);
    }

    public function testTallNativeControlsKeepTheirGeneratedRowAlignment()
    {
        $this->assertNativeEqual('<p style="margin:0">Label <textarea rows="3" style="width:6ch">AB</textarea>!</p>', 40);
    }

    public function testAuthoredVerticalAlignmentOverridesTheGeneratedRowDefault()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p style="margin:0">Label <textarea id="field" rows="3" style="width:6ch;vertical-align:top">AB</textarea></p>');
        $outer = new \Symfony\Component\Tui\Widget\ContainerWidget();
        $outer->add($view->getRoot());
        new FacadeRenderer(new Renderer())->renderFrame($outer, 40, 10);
        $node = $view->getStyleSheet()->getMirrorProjection()['nodes'][$view->getById('field')];
        self::assertSame('top', $view->getRoot()->getPresentation()->getResult()->getComputedStyle($node)['vertical-align']);
    }

    public function testTrueInlineProjectionKeepsTheActualInputFocusAndSourceIdentity()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<p style="margin:0">Label <input id="field" style="width:6ch"></p>');
        $field = $view->getById('field');
        $changes = [];
        $field->on(ChangeEvent::class, static function (ChangeEvent $event) use (&$changes): void { $changes[] = $event->getTarget(); });
        $tui = new Tui(null, new VirtualTerminal(30, 8));
        $tui->setRenderer(new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        $tui->start();
        try {
            $tui->processRender();
            $node = $view->getStyleSheet()->getMirrorProjection()['nodes'][$field];
            $tui->setFocus($field);
            $tui->handleInput('x');
            $tui->processRender();
            self::assertSame($field, $tui->getFocus());
            self::assertSame([$field], $changes);
            self::assertSame('x', $field->getValue());
            self::assertSame($node, $view->getStyleSheet()->getMirrorProjection()['nodes'][$field]);
            self::assertSame(6, $tui->getWidgetRect($field)->col);
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    private function assertNativeEqual(string $html, int $columns): void
    {
        $options = ['columns' => $columns, 'rows' => 10];
        self::assertSame(
            TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), $columns, 10, [229, 229, 229], [0, 0, 0]),
            TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), $columns, 10, [229, 229, 229], [0, 0, 0]),
            $html.' at '.$columns.' columns',
        );
    }
}
