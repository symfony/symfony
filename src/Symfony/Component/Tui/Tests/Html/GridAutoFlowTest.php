<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class GridAutoFlowTest extends TestCase
{
    private const ITEMS = '<p>1</p><p>2</p><p>3</p><p>4</p><p>5</p>';

    public function testItemsFillTheRowsColumnAfterColumn()
    {
        $this->assertSame([
            '┌─┐ ┌─┐ ┌─┐',
            '│1│ │3│ │5│',
            '└─┘ └─┘ └─┘',
            '┌─┐ ┌─┐',
            '│2│ │4│',
            '└─┘ └─┘',
        ], $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column }'));
    }

    public function testWithoutRowsEachItemHasItsColumn()
    {
        $this->assertSame(['┌─┐ ┌─┐ ┌─┐', '│1│ │2│ │3│', '└─┘ └─┘ └─┘'], $this->render('.g { grid-auto-flow: column }', '<p>1</p><p>2</p><p>3</p>'));
    }

    public function testTheShorthandAndTheWidthOfTheImplicitColumns()
    {
        $expected = [
            '┌────┐ ┌────┐ ┌────┐',
            '│1   │ │3   │ │5   │',
            '└────┘ └────┘ └────┘',
            '┌────┐ ┌────┐',
            '│2   │ │4   │',
            '└────┘ └────┘',
        ];
        $this->assertSame($expected, $this->render('.g { grid: auto auto / auto-flow 6ch }'));
        $this->assertSame($expected, $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column; grid-auto-columns: 6ch }'));
    }

    public function testExplicitColumnsShareWhatTheImplicitOnesLeave()
    {
        $this->assertSame([
            '┌──────────────────────────────┐ ┌─┐ ┌─┐',
            '│1                             │ │3│ │5│',
        ], \array_slice($this->render('.g { grid-template: auto auto / 1fr; grid-auto-flow: column }'), 0, 2));
    }

    public function testASubgridCacheFollowsChangedParentTracksAtTheSameWidth()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>#g { display: grid; grid-template-columns: 10ch 10ch } #g.changed { grid-template-columns: 5ch 15ch } #sub { display: grid; grid-template-columns: subgrid; grid-column: span 2 }</style><div id="g"><div id="sub"><div>A</div><div id="b">B</div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(10, $tui->getWidgetRect($view->getById('b'))->col);
        $view->getById('g')->addStyleClass('changed');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(5, $tui->getWidgetRect($view->getById('b'))->col);
        $tui->stop();
    }

    public function testAnEmptyGridTrackPaintsNoBackgroundColumn()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: min-content 1fr } #empty { background: #ff0000 }</style><div class="g"><div id="empty"></div><div>X</div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringNotContainsString('48;2;255;0;0', $terminal->getOutput());
        $tui->stop();
    }

    public function testAnEmptyImplicitMinContentColumnHasNoWidth()
    {
        foreach (['column', 'row'] as $flow) {
            $this->assertSame(['  X'], $this->render('.g { grid-template-columns: min-content; grid-auto-flow: '.$flow.'; grid-auto-columns: min-content } .g p { border: 0; grid-row: 1 } .g p:nth-child(2) { grid-column: 2 } .g p:nth-child(3) { grid-column: 3 }', '<p></p><p></p><p>X</p>'));
        }
    }

    public function testDenseFillsTheCellsLeftBefore()
    {
        $this->assertSame([
            '┌─┐ ┌──────┐ ┌─┐',
            '│1│ │2 tall│ │4│',
            '└─┘ │      │ └─┘',
            '┌─┐ │      │',
            '│3│ │      │',
            '└─┘ └──────┘',
        ], $this->render('.g { grid-template-rows: auto auto; grid-auto-flow: column dense } .w { grid-row: span 2 }', '<p>1</p><p class="w">2 tall</p><p>3</p><p>4</p>'));
    }

    /**
     * @return list<string>
     */
    private function render(string $css, string $items = self::ITEMS): array
    {
        $terminal = new VirtualTerminal(40, 12);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; column-gap: 1ch } .g p { border: 1px solid } '.$css.'</style><div class="g">'.$items.'</div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 12);
        $buffer->write($terminal->getOutput());
        $tui->stop();

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
