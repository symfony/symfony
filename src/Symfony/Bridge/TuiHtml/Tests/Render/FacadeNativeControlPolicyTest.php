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
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class FacadeNativeControlPolicyTest extends TestCase
{
    public function testComboboxesRemainAtomicInGeneratedInlineRows()
    {
        $this->assertLegacyCells('<label for="city">City</label><input id="city" list="cities" style="border:1px solid;max-width:24ch"><datalist id="cities"><option>Paris</option><option>Lyon</option></datalist>');
    }

    public function testKnownFillControlsUseAllocatedWidthAndRespectAuthoredWidth()
    {
        foreach (['', 'style="width:20ch"'] as $width) {
            $this->assertLegacyCells('<input type="range" min="0" max="10" value="7" '.$width.'>');
            $this->assertLegacyCells('<progress value="75" max="100" '.$width.'></progress>');
        }
    }

    public function testPopupUsesItsPreparedNativeWidthAndItsNaturalOptionHeight()
    {
        $this->assertLegacyCells('<style>select::picker(select){padding-right:2ch;border-color:cyan}</style><label>Fruit <select><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p>', ['keys' => 'f6,enter']);
    }

    public function testPopupPaintingInsideNativeTabsRetainsVisibleRowsWithinTheViewport()
    {
        $actual = FacadeScenario::render('<tui-tabs><section title="One"><style>select::picker(select){padding-right:2ch;border-color:cyan}</style><label>Fruit <select id="fruit"><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p></section></tui-tabs>', ['columns' => 40, 'rows' => 15, 'focus' => '#fruit', 'keys' => 'enter'], true);
        $cells = TerminalCellSnapshot::normalize($actual, 40, 15, [229, 229, 229], [0, 0, 0]);
        $text = implode("\n", array_map(static function (array $row): string {
            $line = '';
            for ($column = 0; $column < 40; ++$column) {
                $line .= $row[$column][0] ?? ' ';
            }

            return $line;
        }, $cells));
        self::assertStringContainsString('Apple', $text);
        self::assertStringContainsString('Banana', $text);
        self::assertStringContainsString('Cherry', $text);
        self::assertStringContainsString('┘', $text);
        self::assertLessThanOrEqual(15, \count($cells));
    }

    public function testOpeningATabsPopupDoesNotChangeItsFlowHeight()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<tui-tabs id="tabs"><section title="One"><label>Fruit <select id="fruit"><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p></section></tui-tabs>');
        $renderer = new FacadeRenderer(new Renderer());
        $renderer->renderFrame($view->getRoot(), 40, 15);
        $result = $view->getRoot()->getPresentation()->getResult();
        $height = $result->getBounds($result->getDocument()->getElementById('tabs'))[0]->height;
        $view->querySelector('#fruit')->setOpen(true);
        $renderer->renderFrame($view->getRoot(), 40, 15);
        $result = $view->getRoot()->getPresentation()->getResult();
        self::assertSame($height, $result->getBounds($result->getDocument()->getElementById('tabs'))[0]->height);
    }

    public function testPopupRowsOutsideTheTabsFlowBoxRemainInteractive()
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build('<tui-tabs><section title="One"><style>select::picker(select){padding-right:2ch;border-color:cyan}</style><label>Fruit <select id="fruit"><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p></section></tui-tabs>');
        $tui = new Tui(null, new VirtualTerminal(40, 15), null, null, new FacadeRenderer(new Renderer()));
        $view->mount($tui);
        try {
            $field = $view->querySelector('#fruit');
            $tui->setFocus($field->getField());
            $tui->start();
            $tui->processRender();
            $tui->handleInput("\r");
            $tui->processRender();
            self::assertTrue($field->isOpen());
            self::assertSame($field->getList(), $view->widgetAt(7, 10));
            self::assertTrue($view->click(7, 10));
            $tui->processRender();
            // Native clicks focus the list; Enter commits its current selection.
            self::assertSame(1, $field->getList()->getSelectedIndex());
            $tui->handleInput("\r");
            $tui->processRender();
            self::assertFalse($field->isOpen());
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }

    public function testHostOverflowClipsThePopupPointerWitness()
    {
        foreach (['visible', 'hidden'] as $overflow) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<tui-tabs style="overflow:'.$overflow.'"><section title="One"><style>select::picker(select){padding-right:2ch;border-color:cyan}</style><label>Fruit <select id="fruit"><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p></section></tui-tabs>');
            $field = $view->querySelector('#fruit');
            $field->setOpen(true);
            new FacadeRenderer(new Renderer())->renderFrame($view->getRoot(), 40, 15);
            $hit = $view->getRoot()->getPresentation()->getWidgetAt(7, 10);
            self::assertSame('visible' === $overflow ? $field->getList() : null, $hit);
        }
    }

    public function testAuthorPopupWidthOverridesTheNativePreferredWidth()
    {
        $actual = FacadeScenario::render('<style>select::picker(select){padding-right:2ch;width:12ch}</style><label>Fruit <select><option>Apple</option><option selected>Banana</option><option>Cherry</option></select></label><p>Below</p>', ['columns' => 40, 'rows' => 15, 'keys' => 'f6,enter'], true);
        $cells = TerminalCellSnapshot::normalize($actual, 40, 15, [229, 229, 229], [0, 0, 0]);
        self::assertSame('┌', $cells[1][6][0]);
        self::assertSame('┐', $cells[1][17][0]);
        self::assertSame('┘', $cells[5][17][0]);
    }

    private function assertLegacyCells(string $html, array $options = []): void
    {
        $options += ['columns' => 40, 'rows' => 15];
        $expected = FacadeScenario::render($html, $options);
        $actual = FacadeScenario::render($html, $options, true);
        self::assertSame(TerminalCellSnapshot::normalize($expected, 40, 15, [229, 229, 229], [0, 0, 0]), TerminalCellSnapshot::normalize($actual, 40, 15, [229, 229, 229], [0, 0, 0]));
    }
}
