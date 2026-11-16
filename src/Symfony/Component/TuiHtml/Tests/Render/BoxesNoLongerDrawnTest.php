<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ParentInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\CollapsibleWidget;

/**
 * A widget drawn in a frame and not, or elsewhere, in the next one, after a change through the widgets: its rect, and
 * what the pointer finds, are those of a page drawn whole after the same change, not those of the frame before.
 */
final class BoxesNoLongerDrawnTest extends TestCase
{
    private const COLUMNS = 40;
    private const ROWS = 16;

    public static function provideChanges(): iterable
    {
        $class = static fn (string $id, string $class): \Closure => static function (HtmlView $view) use ($id, $class): void {
            $view->getById($id)->addStyleClass($class);
        };
        $three = '<p id="a">One</p><p id="b">Two</p><p id="c">Three</p>';

        yield 'a block becoming a flex row' => ['.x { display: flex }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'a block becoming a grid' => ['.x { display: grid; grid-template-columns: 1fr 1fr }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'a block becoming a table' => ['.x { display: table } .x > p { display: table-cell }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'a block becoming inline' => ['.x, .x > p { display: inline }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'a table row collapsed' => ['.x { visibility: collapse }', '<table><tr id="r"><td>One</td><td><button>B</button></td></tr><tr><td>Two</td><td>2</td></tr></table><p>After</p>', $class('r', 'x')];
        yield 'content-visibility: hidden' => ['.x { content-visibility: hidden }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'a disclosure closing' => ['', '<details open id="d"><summary>More</summary><p>Inside <button>B</button></p></details><p>After</p>', static function (HtmlView $view): void {
            $details = $view->getById('d');
            self::assertInstanceOf(CollapsibleWidget::class, $details);
            $details->collapse();
        }];
        yield 'overflow: clip of no height' => ['.x { overflow: clip; height: 0 }', '<div id="w">'.$three.'</div><p>After</p>', $class('w', 'x')];
        yield 'an item losing its marker' => ['.x { display: block }', '<ul><li id="i">One <button>B</button></li><li>Two</li></ul><p>After</p>', $class('i', 'x')];
        yield 'an absolute box moved off the screen' => ['#w { position: absolute; top: 2em; left: 0 } #w.x { top: -100em }', '<div id="w"><button>B</button> Moved</div><p>After</p>', $class('w', 'x')];
        yield 'a fixed box moved off the screen' => ['#w { position: fixed; top: 2em; left: 0 } #w.x { left: 200ch }', '<div id="w"><button>B</button> Moved</div><p>After</p>', $class('w', 'x')];
        yield 'columns shrinking' => ['#w { columns: 3 } #w.x { columns: 1 }', '<div id="w">'.str_repeat('<p>Text <button>B</button></p>', 6).'</div><p>After</p>', $class('w', 'x')];
        yield 'scrolled out of a scroll box' => ['#w { overflow: auto; height: 3em }', '<div id="w">'.str_repeat('<p>Line <button>B</button></p>', 8).'</div><p>After</p>', static function (HtmlView $view): void {
            $box = $view->getById('w');
            ContainerState::of($box)->scrollBy(4);
            $box->invalidate();
        }];
        yield 'a popover hidden' => ['', '<p>Top</p><div id="p" popover><button>B</button> Pop</div><p>After</p>', static function (HtmlView $view): void {
            $view->hidePopover($view->getById('p'));
        }, static function (HtmlView $view): void {
            $view->showPopover($view->getById('p'));
        }];
        yield 'a dialog closed' => ['', '<p>Top</p><dialog id="g"><button>B</button> Dialog</dialog><p>After</p>', static function (HtmlView $view): void {
            $view->close($view->getById('g'));
        }, static function (HtmlView $view): void {
            $view->showModal($view->getById('g'));
        }];

        // A box drawing nothing from now on, in each kind of layout around it
        $box = '<div id="w"><p>One <button>B</button></p><p>Two</p></div>';
        $layouts = [
            'a flex row' => ['#c { display: flex }', '<div id="c">'.$box.'<p>Side</p></div>'],
            'a flex column' => ['#c { display: flex; flex-direction: column }', '<div id="c">'.$box.'<p>Below</p></div>'],
            'a grid' => ['#c { display: grid; grid-template-columns: 1fr 1fr }', '<div id="c">'.$box.'<p>Side</p><p>Below</p></div>'],
            'a table cell' => ['', '<table><tr><td>'.$box.'</td><td>Side</td></tr><tr><td>Below</td><td>2</td></tr></table>'],
            'an inline-block' => ['#w { display: inline-block }', '<div id="c">Before '.$box.' after</div>'],
            'a float' => ['#w { float: left; width: 12ch }', '<div id="c">'.$box.'<p>Beside the float, some text that wraps around it.</p></div>'],
            'a multi-column box' => ['#c { columns: 2 }', '<div id="c">'.$box.'<p>Three</p><p>Four</p><p>Five</p></div>'],
            'an absolute box' => ['#w { position: absolute; top: 3em; left: 2ch }', '<div id="c"><p>Under</p><p>Under</p></div>'.$box],
            'a cell spanning rows' => ['', '<table><tr><td rowspan="2">'.$box.'</td><td>Side</td></tr><tr><td>2</td></tr><tr><td>Below</td><td>3</td></tr></table>'],
            'a subgrid item' => ['#c { display: grid; grid-template-columns: 1fr 1fr } #s { display: grid; grid-column: 1 / 3; grid-template-columns: subgrid }', '<div id="c"><div id="s">'.$box.'<p>Side</p></div><p>Below</p></div>'],
        ];
        $changes = [
            'content-visibility: hidden' => '#w.x { content-visibility: hidden }',
            'overflow: clip of no height' => '#w.x { overflow: clip; height: 0 }',
            'display: none' => '#w.x { display: none }',
        ];
        // A wide block shown, the box around it now scrolled sideways, as the block around them is laid out again from a
        // paragraph that grew, those after it moved down later, see PositionTracker::shiftChildrenLater()
        $paragraphs = implode('', array_map(static fn (int $i): string => "<p id=\"p$i\">Para $i <a href=\"#p$i\">link</a></p>", range(0, 24)));
        yield 'a wide block shown in a box scrolled sideways' => ['.x { display: none } .big { padding-top: 2em }', '<div style="overflow-x: auto; width: 30ch"><div><pre id="wide" class="x">'.str_repeat('W', 60).'</pre>'.$paragraphs.'</div></div><p>After</p>', static function (HtmlView $view): void {
            $view->getById('p2')->addStyleClass('big');
            $view->getById('wide')->setStyleClasses([]);
        }, null, true, static function (HtmlView $view): void {
            $view->getById('p1')->addStyleClass('big');
        }];
        // A box scrolled out of a scroll container, what it holds overflowing it still shown, then not drawn at all: what it
        // holds forgotten too, the box not taken for one forgotten already
        $lines = implode('', array_map(static fn (int $i): string => "<p>Line $i <button>B</button></p>", range(1, 10)));
        foreach (['its parent of content-visibility: hidden' => ['pp', '.x { content-visibility: hidden }'], 'drawing nothing' => ['w', '.x { height: 0; overflow: clip }']] as $name => [$id, $rule]) {
            yield "a box scrolled out, what overflows it shown, then $name" => ['#s { overflow: auto; height: 5em } #w { height: 1em } '.$rule, '<div id="s"><div id="pp"><div id="w">'.$lines.'</div></div>'.$lines.'</div><p>After</p>', $class($id, 'x'), static function (HtmlView $view): void {
                ContainerState::of($view->getById('s'))->scrollBy(3);
            }];
        }
        // A box scrolled both ways, overflowing it at its last layout, one child exactly as wide as it beside a wider one:
        // laid out again, what is measured at the width without the scrollbar not placed
        $more = implode('', array_map(static fn (int $i): string => "<p id=\"m$i\">Line $i</p>", range(1, 8)));
        yield 'a box scrolled both ways laid out again' => ['#s { overflow: auto; height: 4em; width: 20ch } .x { color: red }', '<div id="s"><pre>'.str_repeat('W', 40).'</pre><p id="exact">'.str_repeat('E', 20).'</p>'.$more.'</div><p>After</p>', $class('m3', 'x'), null, false, static function (HtmlView $view): void {
            $view->getById('m2')->addStyleClass('x');
        }];
        // A box of display: contents in one not drawing its content, drawn, then not again: what it holds forgotten each time
        yield 'a box of display: contents hidden, shown, hidden' => ['#w { display: contents } #pp { border: 1px solid } .x { content-visibility: hidden }', '<p>Top</p><div id="pp" class="x"><div id="w"><button>One</button><button>Two</button></div></div><p>After</p>', $class('pp', 'x'), static function (HtmlView $view): void {
        }, true, static function (HtmlView $view): void {
            $view->getById('pp')->setStyleClasses([]);
        }];
        // For reference: inert, drawn as before, takes no pointer
        yield 'inert' => ['.x { interactivity: inert }', '<p>Top</p>'.$box.'<p>After</p>', $class('w', 'x'), null, false];
        foreach ($layouts as $layout => [$css, $body]) {
            foreach ($changes as $change => $rule) {
                yield "$change in $layout" => [$css.' '.$rule, $body.'<p>After</p>', $class('w', 'x')];
            }
        }
    }

    /**
     * @param \Closure(HtmlView): void        $change What changes after the first frame
     * @param (\Closure(HtmlView): void)|null $before What is done before the first frame, in both
     * @param bool                            $moves  Whether the change moves or hides a widget, or only what takes the pointer
     * @param (\Closure(HtmlView): void)|null $first  A change drawn on its own before, not before the first frame
     */
    #[DataProvider('provideChanges')]
    public function testRectsAndPointerAsAPageDrawnWhole(string $css, string $body, \Closure $change, ?\Closure $before = null, bool $moves = true, ?\Closure $first = null)
    {
        $html = '<style>'.$css.'</style>'.$body;
        // Drawn, changed, drawn again; after a first change drawn on its own
        [$tui, $view] = $this->mount($html, $before);
        if (null !== $first) {
            $first($view);
            $this->render($tui);
        }
        $drawn = self::rects($tui, $view);
        $change($view);
        $this->render($tui);
        if ($moves) {
            $this->assertNotSame($drawn, self::rects($tui, $view), 'The change moves or hides something.');
        }
        // Changed before it was ever drawn
        [$whole, $wholeView] = $this->mount($html, static function (HtmlView $view) use ($before, $first, $change): void {
            $before?->__invoke($view);
            $first?->__invoke($view);
            $change($view);
        });

        $this->assertSame(self::rects($whole, $wholeView), self::rects($tui, $view), 'The rects of the page drawn whole.');
        $found = $expected = [];
        for ($row = 0; $row < self::ROWS; ++$row) {
            for ($col = 0; $col < self::COLUMNS; ++$col) {
                $expected[] = $row.','.$col.' '.self::path($wholeView, $wholeView->widgetAt($row, $col));
                $found[] = $row.','.$col.' '.self::path($view, $view->widgetAt($row, $col));
            }
        }
        $this->assertSame($expected, $found, 'What the pointer finds in the page drawn whole.');
    }

    public function testABoxDrawingNothingFrameAfterFrameIsNotForgottenAgain()
    {
        // A menu of many links closed by a height of 0, beside a paragraph that changes
        $links = implode('', array_map(static fn (int $i): string => "<li><a href=\"#l$i\">Link $i</a></li>", range(1, 200)));
        [$tui, $view] = $this->mount('<style>#menu { overflow: clip; height: 0 }</style><p id="p">One</p><div id="menu"><ul id="links">'.$links.'</ul></div><p>After</p>', null);
        $this->assertNull($tui->getWidgetRect($view->getById('links')));
        // What is written of the rects of the items from now, see PositionTracker::getChildrenWrittenSince()
        $tracker = (fn () => $this->positionTracker)->call($tui->getRenderer());
        $list = $view->getById('links');
        $tracker->getChildrenWrittenSince($list, 0);
        $serial = $tracker->getWriteSerial();

        $view->getById('p')->addStyleClass('x');
        $view->getById('menu')->invalidate();
        $this->render($tui);

        $this->assertSame([], $tracker->getChildrenWrittenSince($list, $serial), 'Its items not forgotten again.');
        $this->assertNull($tui->getWidgetRect($list));
    }

    /**
     * @return array{Tui, HtmlView}
     */
    private function mount(string $html, ?\Closure $before): array
    {
        $tui = new Tui(null, new VirtualTerminal(self::COLUMNS, self::ROWS));
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $before?->__invoke($view);
        $this->render($tui);

        return [$tui, $view];
    }

    private function render(Tui $tui): void
    {
        $tui->requestRender();
        $tui->processRender();
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
            $rects[$path] = null === $rect ? null : [$rect->row, $rect->col, $rect->columns, $rect->rows, ...($rect->clip ?? [])];
            foreach ($widget instanceof ParentInterface ? array_values($widget->all()) : [] as $i => $child) {
                $walk($child, $path.'/'.$i);
            }
        };
        $walk($view->getBody(), 'body');

        return $rects;
    }

    private static function path(HtmlView $view, ?AbstractWidget $widget): string
    {
        $path = [];
        for (; null !== $widget && $widget !== $view->getBody(); $widget = $widget->getParent()) {
            $path[] = array_search($widget, array_values($widget->getParent()?->all() ?? []), true);
        }

        return null === $widget ? '-' : 'body/'.implode('/', array_reverse($path));
    }
}
