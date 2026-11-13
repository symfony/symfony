<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\RichTextWidget;

final class TableLayoutTest extends TestCase
{
    public function testAHugeColspanIsClampedToTheRoom()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<table>'.str_repeat('<tr><td colspan="1000" rowspan="1000">x</td><td>y</td></tr>', 50).'</table>')->mount($tui);
        $start = hrtime(true);
        $tui->start();
        $tui->processRender();

        $this->assertLessThan(1_000_000_000, hrtime(true) - $start, 'Well under a second.');
        $this->assertNotNull($tui->getWidgetRect($view->getRoot()));
    }

    public function testTheRowsRealignWhenACellGrows()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<table>
            <tr><td id="a">a</td><td id="b">b</td></tr>
            <tr><td id="c">c</td><td id="d">d</td></tr>
        </table>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $column = static fn (string $id) => $tui->getWidgetRect($view->getById($id))->col;
        $this->assertSame([2, 2], [$column('b'), $column('d')]);

        $text = $view->getById('c')->all()[0] ?? null;
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $text->setText('a longer cell');
        $tui->requestRender();
        $tui->processRender();

        $this->assertSame([14, 14], [$column('b'), $column('d')], 'The first row, unchanged itself, follows the wider column.');
    }
}
