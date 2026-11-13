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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class GridLineNamesTest extends TestCase
{
    public function testItemsArePlacedOnNamedLines()
    {
        $rects = $this->layout(
            '.grid { display: grid; grid-template-columns: [full-start] 10ch [content-start] 20ch [content-end] 10ch [full-end]; grid-template-rows: [top] auto [middle] auto }
             #wide { grid-column: full-start / full-end }
             #content { grid-column: content; grid-row: middle }
             #side { grid-column-start: content-end }',
            '<div class="grid"><p id="wide">wide</p><p id="content">content</p><p id="side">side</p></div>',
        );

        $this->assertSame([0, 0, 40], $rects['wide'], 'From the first named line to the last.');
        $this->assertSame([1, 10, 20], $rects['content'], 'A name alone: content-start to content-end, on the row of the line "middle".');
        $this->assertSame([1, 30, 10], $rects['side'], 'From content-end, one track: the first row where it is free.');
    }

    public function testAreasNameTheirEdges()
    {
        $rects = $this->layout(
            '.grid { display: grid; grid-template-columns: 10ch 10ch 10ch; grid-template-areas: "head head head" "nav main main" }
             #a { grid-column: main-start / main-end; grid-row: head }
             #b { grid-area: nav }
             #c { grid-area: nowhere }',
            '<div class="grid"><p id="a">a</p><p id="b">b</p><p id="c">c</p></div>',
        );

        $this->assertSame([0, 10, 20], $rects['a'], 'The edges of an area are lines: main-start, main-end, and head for the row.');
        $this->assertSame([1, 0, 10], $rects['b']);
        $this->assertSame(10, $rects['c'][2], 'No such area nor lines: placed automatically.');
    }

    /**
     * @return array<string, array{int, int, int}> Row, column and width by id
     */
    private function layout(string $css, string $html): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(60, 10));
        $view = (new HtmlBuilder())->build('<style>p { margin: 0 } '.$css.'</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();

        $rects = [];
        foreach ($view->querySelectorAll('[id]') as $widget) {
            $rect = $tui->getWidgetRect($widget);
            $rects[$widget->getId()] = [$rect->row, $rect->col, $rect->columns];
        }

        return $rects;
    }
}
