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
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Internal\StyleBridge;
use Symfony\Bridge\TuiHtml\Style\Direction;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;
use Symfony\Bridge\TuiHtml\Widget\TextWidget;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;

/**
 * A layout renders some children once without tracking positions to
 * measure them: their cached render must not reuse the rects of an earlier frame.
 * Nor must a render kept in another size, like after a terminal resized and back.
 */
final class StaleRectsTest extends TestCase
{
    public function testRectsFollowAChangeInANestedRow()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $outer = new ContainerWidget();
        $row = StyleBridge::set(new ContainerWidget(), new Style(null, null, null, null, null, null, null, null, null, null, Direction::Horizontal, 1));
        $row->add($a = StyleBridge::set(new TextWidget('a'), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 0)));
        $row->add($b = StyleBridge::set(new TextWidget('b'), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 0)));
        $outer->add($row);
        $tui->add($outer);
        $tui->start();
        $tui->processRender();
        $this->assertSame(2, $tui->getWidgetRect($b)->col);
        $a->setText('a longer text');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(14, $tui->getWidgetRect($b)->col);
        $this->assertSame(13, $tui->getWidgetRect($a)->columns);
    }

    public function testAnUnchangedSubtreeKeepsItsRects()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $tui->add($top = new TextWidget('top'));
        $tui->add($box = new ContainerWidget());
        $box->add($inner = new TextWidget('inner'));
        $tui->start();
        $tui->processRender();
        $this->assertSame(1, $tui->getWidgetRect($inner)->row);
        $top->setText("top\nnow two rows");
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(2, $tui->getWidgetRect($inner)->row, 'Moved with its cached parent.');
    }

    public function testRectsAfterAResizeAndBack()
    {
        $html = '<div>'.str_repeat('<p>A paragraph with enough words to wrap differently at forty columns than at a hundred.</p>', 6).'</div>';
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $this->assertSame($this->freshRects($html, 100), self::rects($tui, $view), 'The rects of the widths drawn, not of the render kept for 40 columns.');
    }

    public function testTheContentOfAnOpenDisclosureAfterAResizeAndBack()
    {
        $html = '<details open><summary>A summary</summary><p>The content of an open disclosure, which it draws itself under its summary.</p></details><p>After it.</p>';
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $this->assertSame($this->freshRects($html, 100), self::rects($tui, $view), 'What it draws itself is not tracked at its summary.');
    }

    public function testTheContentOfAnOpenDisclosureMovedOrRedrawn()
    {
        $html = '<p id="top">top</p><details open id="d"><summary>A summary</summary><p>The content of an open disclosure.</p><button>Press</button></details><p>After it.</p>';
        $grow = static fn (HtmlView $view) => self::text($view->getById('top'))->setText("top\nnow two rows");
        [$tui, , $view] = $this->mount($html, 60);
        $grow($view);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame($this->freshRects($html, 60, $grow), self::rects($tui, $view), 'Moved with the disclosure taken from its cache.');
        $summary = static fn (HtmlView $view) => $view->getById('d')->setSummary('Another summary');
        $summary($view);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame($this->freshRects($html, 60, static function (HtmlView $view) use ($grow, $summary) {
            $grow($view);
            $summary($view);
        }), self::rects($tui, $view), 'Its content taken from its cache under a summary drawn again.');
        $this->assertNotNull($tui->getWidgetRect($view->querySelector('button')));
    }

    public function testRectsOfFlexAndGridItemsAfterAResizeAndBack()
    {
        $html = '<div style="display: flex"><div>side</div><div style="flex: 1"><section><p>A paragraph in a flex item that wraps at forty columns.</p><p>Another one, as long as it is.</p></section></div></div>'
            .'<div style="display: grid; grid-template-columns: auto 1fr 2fr"><span>k</span><div><b>v</b> a value that wraps at forty columns</div><p>A note in the last column, that wraps too.</p></div>';
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $this->assertSame($this->freshRects($html, 100), self::rects($tui, $view));
    }

    public function testACachedSubtreeMovedAfterAResizeAndBack()
    {
        $html = '<p id="top">top</p><div style="display: flex"><div>side</div><div style="flex: 1"><section><p>A paragraph with enough words to wrap differently at forty columns than at a hundred.</p></section></div></div>';
        $grow = static fn (HtmlView $view) => self::text($view->getById('top'))->setText(str_repeat('The text above grows and moves what follows. ', 6));
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $grow($view);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame($this->freshRects($html, 100, $grow), self::rects($tui, $view));
    }

    public function testAnAnchorAfterAResizeAndBack()
    {
        $html = '<style>#a { anchor-name: --a; margin: 0 auto; width: 10ch } #side { position: fixed; position-anchor: --a; top: anchor(top); left: anchor(right) }</style><p>first</p><p id="a">anchor</p><p id="side">beside</p>';
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $this->assertSame(55, $tui->getWidgetRect($view->getById('side'))->col, 'Beside the anchor centered in 100 columns, its box not that of 40 columns.');
    }

    public function testAMultiColumnBoxAfterAResizeAndBack()
    {
        $html = '<style>div { columns: 2; column-gap: 2ch } p { margin: 0 } section { border: 1px solid; padding: 0 1ch }</style>'
            .'<div id="box"><section><p>A box sliced across the columns has its top edge in the first piece only and its bottom edge in the last.</p></section></div>';
        [$tui, $terminal, $view] = $this->mount($html, 100);
        $this->resize($tui, $terminal, 40);
        $this->resize($tui, $terminal, 100);
        $view->getById('box')->invalidate();
        [$freshTui, , $freshView] = $this->mount($html, 100);
        $this->assertSame(self::lines($freshTui, $freshView, 100), self::lines($tui, $view, 100), 'Three lines are not broken across columns, by the widows and orphans of the text drawn, not of the one of 40 columns.');
    }

    public function testTheContentOfACellSpanningRowsIsTrackedWhereItIsDrawn()
    {
        [$tui, , $view] = $this->mount('<table><tr><td id="span" rowspan="3" style="border: 1px solid">Drawn across three rows</td><td style="border: 1px solid">one</td></tr>'
            .'<tr><td style="border: 1px solid">two</td></tr><tr><td style="border: 1px solid">three</td><td rowspan="2" style="border: 1px solid">across two rows,<br>the last one<br>taller</td></tr><tr><td>four</td><td>five</td></tr></table>', 100);
        $lines = self::lines($tui, $view, 100);
        foreach (['span' => 'Drawn across three rows', 'span2' => 'across two rows,'] as $id => $text) {
            $cell = 'span' === $id ? $view->getById('span') : $view->querySelectorAll('td')[4];
            $rect = $tui->getWidgetRect(self::text($cell));
            $this->assertNotNull($rect);
            $this->assertSame($text, rtrim(mb_substr(preg_replace('/\e\[[0-9;]*m/', '', $lines[$rect->row]), $rect->col, mb_strlen($text))), 'The text of the cell is tracked where it is drawn.');
        }
    }

    /**
     * @return array{Tui, VirtualTerminal, HtmlView}
     */
    public function testTheLinesOfATextBesideFloatsMeasuredInAColumnOfAFlexRow()
    {
        // Measured as if no float were beside it, its render beside them taken after: the columns are cut where the lines
        // of that render start, not of the measure
        $text = str_repeat('Lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam. ', 3);
        $html = '<style>p { margin: 0 } .f { float: left; width: 5ch; height: 4em }</style><div style="display: flex"><div style="columns: 2; column-gap: 2ch"><div class="f">F</div><p>'.$text.'</p><p id="s">%s</p></div><p>side</p></div>';
        [$tui, , $view] = $this->mount(\sprintf($html, 'one'), 60);
        self::text($view->getById('s'))->setText('two');
        $tui->requestRender();
        $tui->processRender();

        $this->assertSame($this->freshRects(\sprintf($html, 'two'), 60), self::rects($tui, $view));
    }

    public function testABoxNoLongerDrawnAfterAClassToggleForgetsItsRect()
    {
        $style = '<style>.flat, .flat select { display: contents } .gone { display: none }</style>';
        $page = static fn (string $class): string => $style.'<p>Top</p><div id="w" class="'.$class.'"><button id="b">Press</button><select id="s"><option>A</option><option>B</option></select></div><p>After</p>';
        foreach (['flat', 'gone'] as $class) {
            [$tui, , $view] = $this->mount($page(''), 60);
            $this->assertNotNull($tui->getWidgetRect($view->getById('s')));
            // The row the controls were drawn on
            $row = $tui->getWidgetRect($view->getById('b'))->row;
            $view->getById('w')->addStyleClass($class);
            $tui->requestRender();
            $tui->processRender();
            [$whole, , $wholeView] = $this->mount($page($class), 60);
            $this->assertSame(self::rects($whole, $wholeView), self::rects($tui, $view), "The rects of a page built whole with .$class, not those of the boxes drawn before.");
            for ($col = 0; $col < 20; ++$col) {
                $this->assertSame(self::path($wholeView, $wholeView->widgetAt($row, $col)), self::path($view, $view->widgetAt($row, $col)), "The widget at column $col with .$class.");
            }
        }
    }

    private static function path(HtmlView $view, ?AbstractWidget $widget): ?string
    {
        $path = [];
        for (; null !== $widget && $widget !== $view->getBody(); $widget = $widget->getParent()) {
            $path[] = array_search($widget, array_values($widget->getParent()?->all() ?? []), true);
        }

        return null === $widget ? null : implode('/', array_reverse($path));
    }

    private function mount(string $html, int $columns): array
    {
        $terminal = new VirtualTerminal($columns, 40);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $terminal, $view];
    }

    private function resize(Tui $tui, VirtualTerminal $terminal, int $columns): void
    {
        $terminal->simulateResize($columns, 40);
        $tui->requestRender();
        $tui->processRender();
    }

    /**
     * @param (\Closure(HtmlView): void)|null $change
     *
     * @return array<string, list<int>|null>
     */
    private function freshRects(string $html, int $columns, ?\Closure $change = null): array
    {
        [$tui, , $view] = $this->mount($html, $columns);
        if (null !== $change) {
            $change($view);
            $tui->requestRender();
            $tui->processRender();
        }

        return self::rects($tui, $view);
    }

    /**
     * The rects of the widgets of a view, by their place in it.
     *
     * @return array<string, list<int>|null>
     */
    private static function rects(Tui $tui, HtmlView $view): array
    {
        $rects = [];
        $walk = static function (AbstractWidget $widget, string $path) use (&$walk, &$rects, $tui): void {
            $rect = $tui->getWidgetRect($widget);
            $rects[$path] = null === $rect ? null : [$rect->row, $rect->col, $rect->columns, $rect->rows];
            foreach ($widget instanceof ParentInterface ? array_values($widget->all()) : [] as $i => $child) {
                $walk($child, $path.'/'.$i);
            }
        };
        $walk($view->getBody(), 'body');

        return $rects;
    }

    /**
     * @return list<string>
     */
    private static function lines(Tui $tui, HtmlView $view, int $columns): array
    {
        for ($root = $view->getBody(); null !== $root->getParent(); $root = $root->getParent()) {
        }

        return $tui->getRenderer()->renderFrame($root, $columns, 40)->toArray();
    }

    private static function text(AbstractWidget $widget): TextWidget|RichTextWidget
    {
        while (!$widget instanceof TextWidget && !$widget instanceof RichTextWidget && $widget instanceof ParentInterface) {
            $widget = array_values($widget->all())[0];
        }
        if (!$widget instanceof TextWidget && !$widget instanceof RichTextWidget) {
            throw new \LogicException(\sprintf('No text widget, but a "%s".', $widget::class));
        }

        return $widget;
    }
}
