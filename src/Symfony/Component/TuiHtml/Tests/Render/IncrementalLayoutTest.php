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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Render\Renderer;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Style\StyleProviderInterface;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

/**
 * A block laid out again for a child that changed takes the children before and after it as they were: what it draws
 * is what a page built with the change from the start draws.
 */
final class IncrementalLayoutTest extends TestCase
{
    private const COLUMNS = 60;
    private const ROWS = 120;
    private const PARAGRAPHS = 24;

    /**
     * Each step: the content of an element replaced, a class given to one, or one filling the rows left, then a frame;
     * and how the page is made, see page().
     *
     * @return iterable<string, array{list<array{string, string, string}>, list<string>}>
     */
    public static function provideChanges(): iterable
    {
        $slot = static fn (string ...$contents): array => array_map(static fn (string $html): array => ['html', 'slot', $html], $contents);
        yield 'as tall' => [$slot('<p>Two</p>', '<p>Three</p>', '<p>Four</p>'), []];
        yield 'taller then shorter' => [$slot('<p>Two</p><p>more</p><p>and more</p>', '<p>Three</p>', '<p>Four</p>'), []];
        yield 'emptied then filled' => [$slot('', '<p>Two <a href="#p3">link</a></p>', '<p>Three</p>'), []];
        yield 'margins and a list' => [$slot('<ul><li>a</li><li>b</li></ul>', '<h2>Title</h2><p>x</p>', '<p>y</p>'), []];
        yield 'the block moved down' => [[...$slot('<p>Two</p>'), ['html', 'title', 'Title<br>on two lines'], ...$slot('<p>Three</p>')], []];
        yield 'a sibling hidden then shown' => [[...$slot('<p>Two</p>'), ['class', 'p5', 'gone'], ...$slot('<p>Three</p>'), ['class', 'p5', ''], ...$slot('<p>Four</p>')], []];
        yield 'a sibling given a margin' => [[...$slot('<p>Two</p>'), ['class', 'p20', 'far'], ...$slot('<p>Three</p>')], []];
        yield 'in a box of no box' => [$slot('<p>Two</p>', '<p>Three</p><p>more</p>', '<p>Four</p>'), ['contents']];
        // Its children moved after they are laid out, by the ::before of the block, each time
        yield 'after a ::before' => [$slot('<p>Two</p>', '<p>Three</p><p>more</p>', '<p>Four</p>'), ['before']];
        // Its bottom margin carried through the empty block after it, to the paragraph after that
        yield 'its margin through an empty block' => [$slot('<p class="far">Two</p>', '<p>Three</p>', '<p class="far">Four</p>'), ['plain']];
        // Floats going on past the block, beside the paragraphs after it, then not, then again
        $floated = static fn (string $text): string => '<div class="flt" style="height: 4em">'.$text.'</div><p>Beside</p>';
        yield 'floats going on past it in turn' => [$slot($floated('F'), '<p>Three</p>', $floated('G'), $floated('H'), '<p>Four</p>'), ['plain']];
        // Past the article too, the paragraph after it beside them
        yield 'floats going on past the article in turn' => [$slot($floated('F'), '<p>Three</p>', $floated('G'), $floated('H'), '<p>Four</p>'), ['plain', 'last']];
        // A paragraph pulled up over those before it, the rows before it fewer than its pull, then more
        $tall = '<p>Two</p><p>more</p><p>and more</p>';
        yield 'pulled up over a block shrinking' => [[['class', 'p2', 'pull'], ...$slot($tall, '', $tall, '<p>Three</p>', '')], ['first']];
        yield 'pulled up over a block of no border shrinking' => [[['class', 'p1', 'pull'], ...$slot($tall, '', $tall, '<p>Three</p>', ''), ['class', 'p1', ''], ['class', 'p2', 'pull'], ...$slot($tall, '', '<p>Four</p>')], ['first', 'plain']];
        // The first child, empty, then not
        yield 'the first one emptied then filled' => [$slot('', '<p>Two</p>', '', '<p>Three</p>'), ['first']];
        // A block growing from 2 to 5 rows, then shrinking, a sibling after the next one pulled up over what is before it
        // by a negative margin, less or more than the rows between, or collapsing with the margin of its parent
        $rows = static fn (int $count): string => implode('', array_map(static fn (int $i): string => "<p>r$i</p>", range(1, $count)));
        foreach (['pull1', 'pull4', 'pull9'] as $pull) {
            foreach (['p4' => [], 'p3' => ['first']] as $pulled => $options) {
                yield "grown then shrunk, $pulled pulled by .$pull" => [[['class', $pulled, $pull], ...$slot($rows(2), $rows(5), $rows(2), $rows(5))], $options];
            }
            yield "grown then shrunk, a first child pulled by .$pull with its parent" => [[['class', 'inner', $pull], ...$slot($rows(2), $rows(5), $rows(2), $rows(5))], []];
        }
        // The empty block after it pulling the next one up by its negative margins, which collapse through it, the rows
        // before it fewer than its pull, then more; one of a positive margin too, drawn as its rows
        foreach (['neg', 'neg-up'] as $class) {
            yield "grown then shrunk, the empty block after it .$class" => [[['class', 'spacer', $class], ...$slot($rows(2), '', $rows(5), $rows(2), '')], ['plain']];
            yield "grown then shrunk, the empty block after it .$class, the next one pulled too" => [[['class', 'spacer', $class], ['class', 'p3', 'pull1'], ...$slot($rows(5), '', $rows(2)), ['class', 'p3', 'pull4'], ...$slot($rows(5), '')], ['plain']];
        }
        // The block itself pulling the next one up while empty, its negative margin collapsing through it, not when
        // it holds blocks, which it is pulled over
        yield 'emptied then filled, pulled up by a negative margin' => [[['class', 'slot', 'neg'], ...$slot('', $rows(2), '', $rows(5), '', '')], ['plain']];
        yield 'emptied then filled, pulled up, the empty block after it too' => [[['class', 'slot', 'neg'], ['class', 'spacer', 'neg'], ...$slot('', $rows(2), '', $rows(5), ''), ['class', 'spacer', 'neg-up'], ...$slot($rows(2), '', $rows(5))], ['plain']];
        // The empty block alone changing: the next one pulled by another negative margin, all else as it was
        yield 'the negative margin of an empty block changed' => [[['class', 'spacer', 'neg'], ['class', 'spacer', 'neg1'], ['class', 'spacer', 'neg'], ['class', 'spacer', 'neg1']], ['plain']];
        yield 'filling the rows left' => [[...$slot('<p>Two</p>'), ['fill', 'slot', ''], ...$slot('<p>Three</p>')], []];
        // The blocks after one that grew moved down at once, their rects telling it when read
        $tall = '<p>Two</p><p>more</p><p>and more</p>';
        yield 'two blocks growing in turn' => [[...$slot($tall), ['html', 'slot2', $tall], ...$slot('<p>Three</p>'), ['html', 'slot2', ''], ...$slot($tall, '')], ['two']];
        yield 'grown in a box scrolled' => [$slot($tall, '<p>Three</p>', $tall), ['scrolled']];
        yield 'shrunk in a box scrolled' => [$slot('<p>Two</p>', '', $tall, '<p>Three</p>', ''), ['scrolled']];
        // Scrolled down, the slot above what is shown, then partly shown
        yield 'grown and shrunk in a box scrolled down' => [[['scroll', 'scrollbox', '6'], ...$slot($tall, '<p>Three</p>', ''), ['scroll', 'scrollbox', '-4'], ...$slot($tall, '<p>Four</p>')], ['scrolled']];
        yield 'grown in a box that clips' => [$slot($tall, '<p>Three</p>', $tall), ['hidden']];
        // A box after them holding blocks, taken from its cache, moved where they are
        yield 'two blocks growing in turn, a box after the second' => [[...$slot($tall), ['html', 'slot2', $tall], ['html', 'slot2', ''], ...$slot('<p>Three</p>')], ['two', 'boxed']];
        // A box scrolled after them, its rows cut where it is now
        $lines = static fn (int $from, int $to): string => implode('', array_map(static fn (int $i): string => "<p id=\"sc$i\">Scrolled $i <a href=\"#p$i\">link</a></p>", range($from, $to)));
        yield 'a box scrolled moved down' => [[...$slot($tall), ['html', 'scroller', $lines(1, 7)], ['html', 'scroller', $lines(2, 8)], ...$slot('<p>Three</p>'), ['html', 'scroller', $lines(1, 6)]], ['scroller']];
        yield 'grown then a sibling hidden' => [[...$slot($tall), ['class', 'p5', 'gone'], ...$slot('<p>Three</p>'), ['class', 'p5', ''], ...$slot($tall)], []];
        // A sibling that is no plain block a while
        foreach (['moved' => 'rel', 'absolute' => 'abs', 'floated' => 'flt', 'wider than the box' => 'wide', 'an item' => 'item'] as $name => $class) {
            yield "a sibling $name then not" => [[...$slot('<p>Two</p>'), ['class', 'p6', $class], ...$slot('<p>Three</p>'), ['class', 'p6', ''], ...$slot('<p>Four</p>')], []];
        }
    }

    /**
     * @param list<array{string, string, string}> $changes
     * @param list<string>                        $options
     */
    #[DataProvider('provideChanges')]
    public function testAChangedPageIsDrawnAsThePageBuiltWithTheChange(array $changes, array $options)
    {
        $parts = ['title' => 'Title', 'slot' => '<p>One</p>'];
        $classes = [];
        $filled = [];
        $scrolled = [];
        [$tui, $terminal, $view] = self::mount(self::page($parts, $classes, $options));
        foreach ($changes as [$change, $id, $value]) {
            if ('scroll' === $change) {
                ContainerState::of($view->getById($id))->scrollBy((int) $value);
                $view->getById($id)->invalidate();
                $scrolled[$id] = ($scrolled[$id] ?? 0) + (int) $value;
            } elseif ('html' === $change) {
                $view->setInnerHtml($id, $value);
                $parts[$id] = $value;
            } elseif ('class' === $change) {
                $view->getById($id)->setStyleClasses('' === $value ? [] : [$value]);
                $classes[$id] = $value;
            } else {
                $view->getById($id)->expandVertically(true);
                $filled[] = $id;
            }
            self::frame($tui);
            [$builtTui, $builtTerminal, $built] = self::mount(self::page($parts, $classes, $options), $filled, $scrolled);
            $this->assertSame(self::drawn($builtTui, $builtTerminal, $built), self::drawn($tui, $terminal, $view));
        }
    }

    /**
     * Where a sibling no plain block a while is, from pages drawn without it: not from the page built with the change,
     * which draws it the same way.
     */
    public function testASiblingThatIsNoPlainBlockIsDrawnAsSuch()
    {
        $rects = static fn (Tui $tui, HtmlView $view, string ...$ids): array => array_map(static fn (string $id): array => [($rect = $tui->getWidgetRect($view->getById($id)))?->row, $rect?->columns], $ids);
        [$plainTui, , $plain] = self::mount(self::page());
        [$goneTui, , $gone] = self::mount(self::page(classes: ['p6' => 'gone']));
        [$tui, , $view] = self::mount(self::page());
        $view->setInnerHtml('slot', '<p>Two</p>');
        self::frame($tui);
        $view->setInnerHtml('slot', '<p>One</p>');
        self::frame($tui);

        // Moved down a row, the others where they were
        $view->getById('p6')->setStyleClasses(['rel']);
        self::frame($tui);
        [[$row], [$nextRow]] = $rects($plainTui, $plain, 'p6', 'p7');
        $this->assertSame([[$row + 1], [$nextRow]], array_map(static fn (array $rect): array => [$rect[0]], $rects($tui, $view, 'p6', 'p7')));

        // Out of the flow: the next one where it is without it
        $view->getById('p6')->setStyleClasses(['abs']);
        self::frame($tui);
        $this->assertSame($rects($goneTui, $gone, 'p7', 'p20'), $rects($tui, $view, 'p7', 'p20'));

        // Floated, as wide as set
        $view->getById('p6')->setStyleClasses(['flt']);
        self::frame($tui);
        $this->assertSame(12, $rects($tui, $view, 'p6')[0][1]);

        // Hidden: as if not there
        $view->getById('p6')->setStyleClasses(['gone']);
        self::frame($tui);
        $this->assertSame($rects($goneTui, $gone, 'p7', 'p20'), $rects($tui, $view, 'p7', 'p20'));

        $view->getById('p6')->setStyleClasses([]);
        self::frame($tui);
        $this->assertSame($rects($plainTui, $plain, 'p5', 'p6', 'p7', 'p20'), $rects($tui, $view, 'p5', 'p6', 'p7', 'p20'));
    }

    public function testTheChildrenBeforeAndAfterAChangeAreNotPlacedAgain()
    {
        [$tui, , $view] = self::mount(self::page());
        $view->setInnerHtml('slot', '<p>Two</p>');
        self::frame($tui);
        $first = $tui->getWidgetRect($view->getById('p1'));
        $last = $tui->getWidgetRect($view->getById('p'.self::PARAGRAPHS));

        $view->setInnerHtml('slot', '<p>Three</p>');
        self::frame($tui);

        $this->assertNotNull($first);
        $this->assertSame($first, $tui->getWidgetRect($view->getById('p1')));
        $this->assertSame($last, $tui->getWidgetRect($view->getById('p'.self::PARAGRAPHS)));
    }

    public function testTheChildrenBeforeAndAfterAChangeInABoxScrolledAreNotRenderedAgain()
    {
        // Overflowing it: laid out at the width left by its scrollbar, as the frame before, the others not rendered again
        // (their rects are cut again to what the box shows, each frame)
        [$tui, , $view] = self::mount(self::page(options: ['scrolled']));
        $view->setInnerHtml('slot', '<p>Two</p>');
        self::frame($tui);
        $renders = static fn (): array => array_map(static fn (string $id) => $view->getById($id)->getCurrentRenderCache(), ['p1', 'inner', 'p'.self::PARAGRAPHS]);
        $before = $renders();

        $view->setInnerHtml('slot', '<p>Three</p>');
        self::frame($tui);

        $this->assertNotNull($before[0]);
        // The same objects: not rendered again
        $this->assertSame($before, $renders());
    }

    public function testTheChildrenBeforeAndAfterAChangeInABoxThatClipsAreNotCutAgain()
    {
        [$tui, , $view] = self::mount(self::page(options: ['hidden']));
        $view->setInnerHtml('slot', '<p>Two</p>');
        self::frame($tui);
        $rects = static fn (): array => array_map(static fn (string $id) => $tui->getWidgetRect($view->getById($id)), ['p1', 'inner', 'p'.self::PARAGRAPHS]);
        $before = $rects();

        $view->setInnerHtml('slot', '<p>Three</p>');
        self::frame($tui);

        $this->assertNotNull($before[0]?->clip, 'Cut by the box.');
        // The same objects: not cut again
        $this->assertSame($before, $rects());
    }

    public function testTheCountersOfTheSiblingsAfterAChangeAreThoseItLeaves()
    {
        // The items after the slot numbered after those in it: again when it holds more or fewer, as they were when it
        // holds as many
        $items = static fn (string $text, int $count): string => implode('', array_map(static fn (int $i): string => "<div class=\"item\">$text $i</div>", range(1, $count)));
        $page = static fn (string $slot): string => '<!DOCTYPE html><html><head><style>body { counter-reset: c } .item { counter-increment: c } .item::before { content: counter(c) ". " }</style></head><body><main><div id="slot">'.$slot.'</div><div id="list">'.$items('Item', 60).'</div></main><p class="item">After</p></body></html>';
        $screen = static function (VirtualTerminal $terminal): string {
            $screen = new ScreenBuffer(self::COLUMNS, self::ROWS);
            $screen->write($terminal->getOutput());

            return $screen->getStyledScreen();
        };
        [$tui, $terminal, $view] = self::mount($page($items('Slot', 3)));
        foreach ([$items('Other', 3), $items('Fewer', 2), $items('More', 4), '<p>None</p>', $items('Back', 3)] as $slot) {
            $view->setInnerHtml('slot', $slot);
            self::frame($tui);
            [, $builtTerminal] = self::mount($page($slot));

            $this->assertSame($screen($builtTerminal), $screen($terminal), $slot);
        }
    }

    public function testABlockOfManyChildrenMatchesWhatOneOfThemHolds()
    {
        // Its children mirrored again for the selectors only when they changed: the focus and the invalid controls in
        // one of them still seen by it
        $html = '<!DOCTYPE html><html><head><style>#box > h3 { display: none } #box:focus-within > h3 { display: block } fieldset:invalid > h2 { display: none }</style></head><body><fieldset id="box"><h2>Valid</h2><h3>Focused</h3>'.implode('', array_map(static fn (int $i): string => "<p id=\"f$i\"><input id=\"i$i\" value=\"$i\" required> Field $i</p>", range(1, 20))).'</fieldset></body></html>';
        [$tui, , $view] = self::mount($html);
        $row = static fn (): ?int => $tui->getWidgetRect($view->getById('i1'))?->row;
        $start = $row();
        $tui->setFocus($view->getById('i5'));
        self::frame($tui);
        $this->assertGreaterThan($start, $row(), 'Its heading of the focus shown.');
        $tui->setFocus(null);
        self::frame($tui);
        $this->assertSame($start, $row());

        $view->setInnerHtml('f7', '<input id="i7" value="" required> Field 7');
        self::frame($tui);
        $this->assertLessThan($start, $row(), 'Its heading of a valid one hidden.');
        $view->setInnerHtml('f7', '<input id="i7" value="7" required> Field 7');
        self::frame($tui);
        $this->assertSame($start, $row());
    }

    public function testTheBlocksMovedLaterInADisclosureCutByItsHeightAreCutWhereTheyAre()
    {
        // Those moved down past its height take no pointer, those moved into it do
        $page = static fn (string $slot): string => '<!DOCTYPE html><html><body><details open style="height: 12em; overflow: hidden"><summary>Summary</summary><div id="slot">'.$slot.'</div>'.implode('', array_map(static fn (int $i): string => "<p id=\"p$i\">Paragraph $i <a href=\"#p$i\">link</a></p>", range(1, self::PARAGRAPHS))).'</details><p id="after">After</p></body></html>';
        [$tui, $terminal, $view] = self::mount($page('<p>One</p>'));
        foreach (['<p>Two</p><p>more</p><p>and more</p>', '<p>Three</p>', '<p>Four</p><p>more</p>'] as $slot) {
            $view->setInnerHtml('slot', $slot);
            self::frame($tui);
            [$builtTui, $builtTerminal, $built] = self::mount($page($slot));

            $this->assertSame(self::drawn($builtTui, $builtTerminal, $built), self::drawn($tui, $terminal, $view), $slot);
        }
    }

    public function testTheChildrenOfABlockReplacedOnceSomeWereMovedLaterArePlacedAnew()
    {
        // The paragraphs after the slot moved down at once, see PositionTracker::shiftChildrenLater(), then others in their
        // place, of the object ids those may have been freed for
        $page = static fn (string $slot, string $text): string => '<!DOCTYPE html><html><body><article id="article"><div id="slot" style="border: 1px solid">'.$slot.'</div>'.implode('', array_map(static fn (int $i): string => "<p id=\"p$i\">$text $i <a href=\"#p$i\">link</a></p>", range(1, self::PARAGRAPHS))).'</article></body></html>';
        $tall = '<p>Two</p><p>more</p><p>and more</p>';
        [$tui, $terminal, $view] = self::mount($page('<p>One</p>', 'Paragraph'));
        $view->setInnerHtml('slot', $tall);
        self::frame($tui);
        foreach (['Other', 'Another', 'Yet another'] as $text) {
            // The widgets taken out freed, their object ids taken again by those built next
            gc_collect_cycles();
            $view->setInnerHtml('article', '<div id="slot" style="border: 1px solid">'.$tall.'</div>'.implode('', array_map(static fn (int $i): string => "<p id=\"p$i\">$text $i <a href=\"#p$i\">link</a></p>", range(1, self::PARAGRAPHS))));
            self::frame($tui);
            [$builtTui, $builtTerminal, $built] = self::mount($page($tall, $text));

            $this->assertSame(self::drawn($builtTui, $builtTerminal, $built), self::drawn($tui, $terminal, $view), $text);
        }
    }

    public function testTheFloatsOfAnItemGoOnPastItOnceItsListHoldsNoMarkerAnyMore()
    {
        // Kept in their item while a marker of the list is drawn, which they would cover; then beside the items after it,
        // and past the list, beside the paragraph after it; then kept again
        $page = static fn (string $class): string => '<!DOCTYPE html><html><head><style>.flt { float: left; width: 6ch; height: 6em } .none { list-style: none }</style></head><body><main><ul><li id="a" class="'.$class.'">One</li><li class="none"><div class="flt">F</div>Beside it</li><li class="none">Following item</li></ul><p id="after">After the list</p></main></body></html>';
        $screen = static function (VirtualTerminal $terminal): string {
            $screen = new ScreenBuffer(self::COLUMNS, self::ROWS);
            $screen->write($terminal->getOutput());

            return $screen->getScreen();
        };
        [$tui, $terminal, $view] = self::mount($page(''));
        foreach (['none', ''] as $class) {
            $view->getById('a')->setStyleClasses('' === $class ? [] : [$class]);
            self::frame($tui);
            [$builtTui, $builtTerminal, $built] = self::mount($page($class));

            $this->assertSame($screen($builtTerminal), $screen($terminal), $class);
            $this->assertEquals($builtTui->getWidgetRect($built->getById('after')), $tui->getWidgetRect($view->getById('after')), $class);
        }
    }

    public function testTheMarksOfTheLinksToFragmentsOfTheLinesChangedAreNotWritten()
    {
        // A page with none, then one in a line changed among many, then another line changed
        $html = '<!DOCTYPE html><html><body><div id="slot"><p>One</p></div>'.implode('', array_map(static fn (int $i): string => "<p id=\"p$i\">Paragraph $i</p>", range(1, 30))).'</body></html>';
        [$tui, $terminal, $view] = self::mount($html);
        $marks = "\x1b]8;".RichTextWidget::FRAGMENT_LINK;
        $this->assertStringNotContainsString($marks, $terminal->getOutput());

        $view->setInnerHtml('slot', '<p><a href="#p30">Two</a></p>');
        self::frame($tui);
        $this->assertStringNotContainsString($marks, $terminal->getOutput());

        $view->getById('p30')->setText('Paragraph 30, again');
        self::frame($tui);
        $this->assertStringNotContainsString($marks, $terminal->getOutput());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideArticles(): iterable
    {
        // It keeps its floats, its own not asked about each time: those of its children are
        yield 'keeping its floats' => ['article { background: #000080 }', 30];
        // Its floats may go on past it, which those of its children tell: those that changed only asked again
        yield 'letting them go on past it' => ['', 30];
        yield 'letting them go on past it, four times as long' => ['', 120];
    }

    #[DataProvider('provideArticles')]
    public function testTheSiblingsOfAChangedChildAreNotAskedAboutTheirFloatsAgain(string $css, int $blocks)
    {
        $html = '<!DOCTYPE html><html><head><style>'.$css.'</style></head><body><h1>Title</h1><article><div id="slot"><p>One</p></div>'.str_repeat('<div><p>Block</p></div>', $blocks).'</article></body></html>';
        $terminal = new VirtualTerminal(self::COLUMNS, self::ROWS);
        $renderer = new Renderer();
        $renderer->getStyleSheet()->addProvider($provider = new class implements StyleProviderInterface {
            public int $asked = 0;

            public function prepare(AbstractWidget $root, MediaContext $media): void
            {
            }

            public function getStyles(AbstractWidget $widget): array
            {
                return [];
            }

            public function getElementStyles(AbstractWidget $widget, string $element): array
            {
                return [];
            }

            public function containsFloats(AbstractWidget $widget): bool
            {
                ++$this->asked;

                return false;
            }
        });
        $tui = new Tui(null, $terminal, null, null, $renderer);
        $tui->start();
        $view = (new HtmlBuilder())->build($html);
        $view->mount($tui);
        self::frame($tui);
        $view->setInnerHtml('slot', '<p>Two</p>');
        self::frame($tui);

        $provider->asked = 0;
        $view->setInnerHtml('slot', '<p>Three</p>');
        self::frame($tui);

        $this->assertLessThan(10, $provider->asked);
    }

    /**
     * @param array<string, string> $parts   The content of the heading and of the slot
     * @param array<string, string> $classes The class of each paragraph
     * @param list<string>          $options The slot in a box of no box of its own ("contents"), first ("first"), of
     *                                       no border, an empty block after it ("plain"); a ::before to the article
     *                                       ("before"), the article in a box scrolled ("scrolled"), another slot
     *                                       near its end ("two")
     */
    private static function page(array $parts = ['title' => 'Title', 'slot' => '<p>One</p>'], array $classes = [], array $options = []): string
    {
        $paragraphs = static fn (int $from, int $to): string => implode('', array_map(static fn (int $i): string => "<p id=\"p$i\" class=\"".($classes["p$i"] ?? '')."\">Paragraph $i with <em>some</em> text and <a href=\"#p$i\">a link</a>.</p>", range($from, $to)));
        $slot = \in_array('plain', $options, true) ? '<div id="slot" class="'.($classes['slot'] ?? '').'">'.$parts['slot'].'</div><div id="spacer" class="'.($classes['spacer'] ?? 'spacer').'"></div>' : '<div id="slot" class="card">'.$parts['slot'].'</div>';
        $slot = \in_array('contents', $options, true) ? '<div style="display: contents">'.$slot.'</div>' : $slot;
        $before = \in_array('before', $options, true) ? 'article::before { content: "Before" }' : '';
        $first = \in_array('first', $options, true);
        // The slot last in the article, a paragraph after it
        [$last, $after] = \in_array('last', $options, true) ? [$slot, '<p id="after">After the article</p>'] : ['', ''];
        $slot = '' !== $last ? '' : $slot;
        $two = \in_array('two', $options, true) ? '<div id="slot2" class="card">'.($parts['slot2'] ?? '').'</div>' : '';
        [$open, $close] = match (true) {
            \in_array('scrolled', $options, true) => ['<div id="scrollbox" style="height: 20em; overflow: auto">', '</div>'],
            \in_array('hidden', $options, true) => ['<div style="height: 40em; overflow: hidden">', '</div>'],
            default => ['', ''],
        };
        $two .= \in_array('boxed', $options, true) ? '<div class="box"><p id="inner2">Inner <a href="#p2">two</a></p></div>' : '';
        $scroller = \in_array('scroller', $options, true) ? '<div id="scroller" style="height: 4em; overflow: auto">'.($parts['scroller'] ?? implode('', array_map(static fn (int $i): string => "<p id=\"sc$i\">Scrolled $i</p>", range(1, 6)))).'</div>' : '';

        return '<!DOCTYPE html><html><head><style>'.$before.' .spacer { margin: 1em 0 } .card { border: 1px solid; padding: 0 1ch } p + p { color: #aaaaaa } .gone { display: none } .far { margin: 3em 0 } .rel { position: relative; top: 1em } .abs { position: absolute; top: 0; left: 4ch } .flt { float: left; width: 12ch } .wide { width: 200ch; overflow: hidden } .item { display: list-item; list-style: square inside } .pull { margin-top: -3em } .pull1 { margin-top: -1em } .pull4 { margin-top: -4em } .pull9 { margin-top: -9em } .box { margin-top: 1em } .neg { margin: -2em 0 0 } .neg1 { margin: -1em 0 0 } .neg-up { margin: -2em 0 1em }</style></head><body><h1 id="title">'.$parts['title'].'</h1>'.$open.'<article>'.($first ? $slot : '').$paragraphs(1, 2).($first ? '' : $slot).$paragraphs(3, 6).$scroller.$paragraphs(7, 10).'<div class="box"><p id="inner" class="'.($classes['inner'] ?? '').'">Inner <a href="#p1">paragraph</a></p></div>'.$paragraphs(11, 20).$two.$paragraphs(21, self::PARAGRAPHS).$last.'</article>'.$after.$close.'</body></html>';
    }

    /**
     * @param list<string>       $filled   The ids of the elements filling the rows left
     * @param array<string, int> $scrolled The rows each scroll container is scrolled down by, by id
     *
     * @return array{Tui, VirtualTerminal, HtmlView}
     */
    private static function mount(string $html, array $filled = [], array $scrolled = []): array
    {
        $terminal = new VirtualTerminal(self::COLUMNS, self::ROWS);
        $tui = new Tui(null, $terminal, null, null, new Renderer());
        $tui->start();
        $view = (new HtmlBuilder())->build($html);
        foreach ($filled as $id) {
            $view->getById($id)->expandVertically(true);
        }
        foreach ($scrolled as $id => $rows) {
            ContainerState::of($view->getById($id))->scrollBy($rows);
        }
        $view->mount($tui);
        self::frame($tui);

        return [$tui, $terminal, $view];
    }

    private static function frame(Tui $tui): void
    {
        $tui->requestRender();
        $tui->processRender();
    }

    /**
     * The screen as the frames drew it, where each paragraph is, and the link at each cell.
     *
     * @return array{string, array<string, mixed>, list<string>, list<string>}
     */
    private static function drawn(Tui $tui, VirtualTerminal $terminal, HtmlView $view): array
    {
        $screen = new ScreenBuffer(self::COLUMNS, self::ROWS);
        $screen->write($terminal->getOutput());
        $rects = [];
        foreach ([...array_map(static fn (int $i): string => 'p'.$i, range(1, self::PARAGRAPHS)), 'inner', 'inner2', 'after', ...array_map(static fn (int $i): string => 'sc'.$i, range(1, 8))] as $id) {
            if (null === $widget = $view->querySelector("#$id")) {
                continue;
            }
            $rect = $tui->getWidgetRect($widget);
            $rects[$id] = null === $rect ? null : [$rect->row, $rect->col, $rect->columns, $rect->rows];
        }
        $renderer = $tui->getRenderer();
        \assert($renderer instanceof Renderer);
        $links = [];
        $named = [];
        for ($row = 0; $row < self::ROWS; ++$row) {
            for ($col = 0; $col < self::COLUMNS; ++$col) {
                if (null !== $link = $renderer->getFragmentLinkAt($row, $col)) {
                    $links[] = "$row:$col:$link";
                }
                // By the order they come in, their names being of the widgets
                if (null !== $link = $renderer->getLinkAt($row, $col)) {
                    $links[] = "$row:$col:id:".($named[$link] ??= \count($named));
                }
            }
        }

        // What the pointer finds, by the place of the widget in the view
        $found = [];
        for ($row = 0; $row < self::ROWS; ++$row) {
            for ($col = 0; $col < self::COLUMNS; $col += 3) {
                $path = [];
                for ($widget = $view->widgetAt($row, $col); null !== $widget && $widget !== $view->getBody(); $widget = $widget->getParent()) {
                    $path[] = array_search($widget, array_values($widget->getParent()?->all() ?? []), true);
                }
                $found[] = null === $widget ? '-' : implode('/', array_reverse($path));
            }
        }

        return [$screen->getStyledScreen(), $rects, $links, $found];
    }
}
