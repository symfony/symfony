<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Event\CollapseEvent;
use Symfony\Component\Tui\Event\ExpandEvent;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\TuiHtml\Event\FormSubmitEvent;
use Symfony\Component\TuiHtml\Event\ToggleEvent;
use Symfony\Component\TuiHtml\Exception\InvalidArgumentException;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Internal\ContainerState;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\CheckboxWidget;
use Symfony\Component\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;
use Symfony\Component\TuiHtml\Widget\FormWidget;
use Symfony\Component\TuiHtml\Widget\LinkedTextWidget;
use Symfony\Component\TuiHtml\Widget\SelectListWidget;

final class HtmlViewSetInnerHtmlTest extends TestCase
{
    private const COLS = 60;
    private const ROWS = 16;

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideShapes(): iterable
    {
        $style = '<style>p + p { color: red } .hot { font-weight: bold } li:last-child { text-decoration: underline }</style>';
        yield 'block content of a <div>' => [$style.'<h1>T</h1><div id="slot"><p>one</p></div><p>after</p>', 'slot', '<p>two</p><p class="hot">three</p>'];
        yield 'items of a <ul>' => [$style.'<ul id="slot"><li>a</li></ul><p>after</p>', 'slot', '<li>b</li><li class="hot">c</li>'];
        yield 'rows of a <tbody>' => ['<table><tbody id="slot"><tr><td>a</td><td>1</td></tr></tbody></table><p>after</p>', 'slot', '<tr><td>bb</td><td>22</td></tr><tr><td>c</td><td>333</td></tr>'];
        yield 'options of a <select>' => ['<select id="slot" size="3" aria-label="s"><option>a</option></select>', 'slot', '<option>b</option><option selected>c</option>'];
        yield 'a <span> that holds a block now' => ['<div>before <span id="slot">inline</span> after</div>', 'slot', '<div>a block</div>'];
        // A <style> the splice adds or removes displays elements outside it otherwise: built again like the page
        yield 'a <style> added that makes an element outside a block' => ['<p>one <em>two</em> three</p><div id="slot"></div>', 'slot', '<style>em { display: block }</style>'];
        yield 'a <style> removed that made an element outside a block' => ['<p>one <em>two</em> three</p><div id="slot"><style>em { display: block }</style></div>', 'slot', '<p>none</p>'];
        yield 'a <style> added with the content' => ['<div id="slot"><p>one</p></div><p class="x">styled</p>', 'slot', '<style>.x { color: green; padding-left: 2ch }</style><p>two</p>'];
    }

    #[DataProvider('provideShapes')]
    public function testTheFrameIsTheOneOfThePageBuiltWhole(string $body, string $id, string $html)
    {
        [$view, $tui, $terminal] = $this->mount($body);
        $view->setInnerHtml($id, $html);
        $full = $this->mount(self::innerReplaced($body, $id, $html));

        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
        $this->assertSameElements($body, $id, $html, $full, [$view, $tui]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideStyleSplices(): iterable
    {
        $page = '<h1>T</h1><p id="p1">one <em>two</em> three <a href="#x">link</a></p><div id="wrap"><span class="tag">s</span> <b>bold</b> <input id="in" aria-label="in"></div>'
            .'<ul><li>a</li><li class="x">b <button id="bt">go</button></li></ul><table><tr><td>c1</td><td class="x">c2</td></tr></table><div class="row"><div>r1</div><div>r2</div></div><div id="slot"><p>old</p></div><p class="x">tail <a href="#y">y</a></p>';
        $boxes = '<p>a <b class="abs" style="position: absolute">abs</b> <span class="ib" style="display: inline-block">box</span> b <em>em</em> c <span class="if" style="display: inline-flex"><i>1</i><i>2</i></span></p><pre>a long line of preformatted text that goes on and on past the width of the terminal</pre><div id="slot"></div>';
        $svg = '<p>pic <svg width="8" height="2" viewBox="0 0 8 2"><rect width="8" height="2" fill="red"/></svg> after</p><div id="slot"></div>';
        $svgVar = '<style>rect { fill: var(--c, red) }</style>'.$svg;
        $styles = [
            'the review repro' => ['<p>one <em>two</em> three</p><div id="slot"></div>', 'em { display: block }'],
            'a color only' => [$page, '.x { color: green }'],
            'a padding of a block' => [$page, '.x { padding-left: 2ch }'],
            'an inline element displayed as a block' => [$page, 'em { display: block }'],
            'an inline box with a border' => [$page, '.tag { display: inline-block; border: 1px solid }'],
            'items inline' => [$page, 'li { display: inline }'],
            'cells as blocks' => [$page, 'td { display: block }'],
            'an element hidden' => [$page, '#wrap { display: none }'],
            'an element without a box' => [$page, '#wrap { display: contents }'],
            'a float' => [$page, '.tag { float: right }'],
            'out of the flow' => [$page, 'b { position: absolute }'],
            'a flex row' => [$page, '.row { display: flex }'],
            'a flex row inline' => [$page, '#wrap { display: inline-flex; flex-direction: column }'],
            'a ::before with content' => [$page, 'p::before { content: "> " }'],
            'in a state' => [$page, 'a:hover { display: block }'],
            'in a media' => [$page, '@media (min-width: 10px) { em { display: block } }'],
            'in a layer' => [$page, '@layer base { em { display: block } }'],
            'by :has() of the content' => [$page, 'body:has(#slot b) em { display: block }'],
            'an ancestor of the slot' => ['<div id="w"><span id="slot">x</span> after</div>', '#w { display: inline }'],
            'the slot itself' => ['<p>before <span id="slot">x</span> after</p>', '#slot { display: block }'],
            'a scroll container' => [$page, '#wrap { overflow: auto; max-height: 1 }'],
            'every element' => [$page, '* { display: block }'],
            'the body' => [$page, 'body { display: flex }'],
            'a width' => [$page, '.tag { display: inline-block; width: 6ch }'],
            'a scoped style' => [$page, '@scope { p { display: inline } }'],
            'an SVG shape' => [$svg, 'rect { fill: blue }'],
            'an SVG shape by a custom property' => [$svg, 'svg { --c: blue } rect { fill: var(--c) }'],
            'an SVG shape by a registered property' => [$svg, '@property --d { syntax: "<color>"; inherits: true; initial-value: blue } rect { fill: var(--d) }'],
            'an SVG shape by a custom property alone' => [$svgVar, 'svg { --c: blue }'],
            'a layout rule then an SVG one of the same element' => ['<p class="card">a <b>b</b> c</p><div id="slot"></div>', '.card { display: flex } p { fill: red }'],
            // At-rules the builder does not read: the styles only
            'a counter style' => ['<ol style="list-style-type: stars"><li>a</li><li>b</li></ol><div id="slot"></div>', '@counter-style stars { system: cyclic; symbols: "*" "+"; suffix: " " }'],
            'keyframes' => ['<style>p { animation: fade 1s paused }</style><p>a</p><div id="slot"></div>', '@keyframes fade { from { color: red } to { color: blue } }'],
            'a registered property' => ['<style>p { color: var(--q) }</style><p>a</p><div id="slot"></div>', '@property --q { syntax: "<color>"; inherits: true; initial-value: blue }'],
            'an SVG shape by a function' => ['<style>rect { fill: --pick() }</style>'.$svg, '@function --pick() { result: blue }'],
            'an SVG hidden' => [$svg, 'svg { display: none }'],
            'a border of an inline block' => [$boxes, '.ib { border: 1px solid }'],
            'a width of an inline block' => [$boxes, '.ib { width: 8ch }'],
            'a padding of an inline element' => [$boxes, 'em { padding-left: 2ch }'],
            'a ::before of an inline element' => [$boxes, 'em::before { content: "*" }'],
            'a scroll container sideways' => [$boxes, 'pre { overflow-x: auto }'],
            'a column of an inline flex' => [$boxes, '.if { flex-direction: column }'],
            'visually hidden' => [$boxes, '.abs { clip: rect(0 0 0 0) }'],
        ];
        foreach ($styles as $name => [$body, $css]) {
            yield $name.', added' => [$body, 'slot', '<style>'.$css.'</style><p>new <b>b</b></p>'];
            yield $name.', removed' => [str_replace('<div id="slot">', '<div id="slot"><style>'.$css.'</style>', str_replace('<span id="slot">', '<span id="slot"><style>'.$css.'</style>', $body)), 'slot', '<p>none</p>'];
        }
        yield 'the same style kept by another element' => ['<style>em { display: block }</style><p>one <em>two</em></p><div id="slot"><style>em { display: block }</style></div>', 'slot', ''];
        $controls = '<p>a <input id="i" aria-label="i" value="v"> b <button id="b">go</button> c <select id="s" aria-label="s"><option>x</option><option>y</option></select> d <textarea id="t" aria-label="t">t</textarea></p><div id="slot"></div><p>after</p>';
        foreach (['input { display: block }', 'button { float: left }', 'select { display: none }', 'textarea { display: block; width: 10ch }', 'input { position: absolute }', 'button { display: inline-block; border: 1px solid }', '#s { display: block }', 'input, button, select, textarea { display: block }', 'p { display: flex }', 'p { display: grid }', 'select { display: inline-block; width: 12ch; border: 1px solid }', 'select, input { display: contents }'] as $css) {
            yield 'a control: '.$css.', added' => [$controls, 'slot', '<style>'.$css.'</style>'];
            yield 'a control: '.$css.', removed' => [str_replace('<div id="slot">', '<div id="slot"><style>'.$css.'</style>', $controls), 'slot', ''];
        }
        yield 'a style replaced by another' => ['<p>one <em>two</em> <b>three</b></p><div id="slot"><style>em { display: block }</style></div>', 'slot', '<style>b { display: block }</style>'];
    }

    #[DataProvider('provideStyleSplices')]
    public function testAStyleSpliceIsTheOneOfThePageBuiltWhole(string $body, string $id, string $html)
    {
        [$view, $tui, $terminal] = $this->mount($body);
        $view->setInnerHtml($id, $html);
        $full = $this->mount(self::innerReplaced($body, $id, $html));

        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
        $cycle = static fn (Tui $tui) => array_map(static fn ($widget) => $widget::class.'#'.$widget->getId(), $tui->getFocusManager()->getCycle());
        $this->assertSame($cycle($full[1]), $cycle($tui));
        $this->assertSameElements($body, $id, $html, $full, [$view, $tui]);
    }

    /**
     * The widget of each element with an id is the one of the page built whole: of the same class, at the same place
     * in the tree. Not when the markup of the document spliced reads as another document, like a <div> in a <p>, which
     * the page built whole cannot have. (Not where it was drawn: the renderer keeps the rect of a widget drawn before,
     * like a kept control that a style sheet displays as contents now.).
     *
     * @param array{HtmlView, Tui, VirtualTerminal} $full
     * @param array{HtmlView, Tui}                  $spliced
     */
    private function assertSameElements(string $body, string $id, string $html, array $full, array $spliced): void
    {
        $markup = self::innerReplaced($body, $id, $html);
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$markup.'</body></html>', \LIBXML_NOERROR);
        if ($document->body?->innerHTML !== $markup) {
            return;
        }
        $of = static function (array $mounted, string $id): ?string {
            try {
                $widget = $mounted[0]->getById($id);
            } catch (InvalidArgumentException) {
                return null;
            }
            $path = [];
            for ($child = $widget; null !== $parent = $child->getParent(); $child = $parent) {
                \assert($parent instanceof \Symfony\Component\Tui\Widget\ParentInterface);
                array_unshift($path, array_search($child, array_values($parent->all()), true));
            }

            return $widget::class.' at '.implode('.', $path);
        };
        foreach ($document->querySelectorAll('[id]') as $element) {
            $elementId = (string) $element->getAttribute('id');
            $this->assertSame($of($full, $elementId), $of($spliced, $elementId), 'The widget of #'.$elementId);
        }
    }

    public function testAScopedStyleIsReadOnceBySplices()
    {
        $sources = static fn (HtmlView $view): int => \count((new \ReflectionProperty($view->getStyleSheet(), 'sources'))->getValue($view->getStyleSheet()));
        [$view, $tui, $terminal] = $this->mount('<div><style>@scope { p { color: red } }</style><p>kept</p></div><div id="slot"></div>');
        $before = $sources($view);
        // A scoped style added, then the body built again for a style sheet (every element displayed otherwise)
        $view->setInnerHtml('slot', '<div><style>@scope { b { color: blue } }</style><b>new</b></div>');
        $view->setInnerHtml('slot', '<div><style>@scope { b { color: blue } }</style><b>new</b></div><style>* { display: block }</style>');
        $view->setInnerHtml('slot', '<div><style>@scope { b { color: blue } }</style><b>new</b></div><style>* { display: block; float: none }</style>');

        $this->assertSame($before + 2, $sources($view), 'The scoped one of the slot and the last style sheet: each read once.');
        $html = '<div><style>@scope { b { color: blue } }</style><b>new</b></div><style>* { display: block; float: none }</style>';
        $full = $this->mount(self::innerReplaced('<div><style>@scope { p { color: red } }</style><p>kept</p></div><div id="slot"></div>', 'slot', $html));
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
    }

    public function testAStyleThatChangesTheStylesOnlyBuildsNothingElse()
    {
        [$view] = $this->mount('<p id="a">one <em>two</em></p><div id="slot"></div><p id="b" class="x">three</p>');
        $a = $view->getById('a');
        $b = $view->getById('b');
        $view->setInnerHtml('slot', '<style>.x { color: green } em { font-weight: bold }</style>');

        $this->assertSame($a, $view->getById('a'));
        $this->assertSame($b, $view->getById('b'));
    }

    public function testAStyleThatBuildsAnElementOtherwiseBuildsWhatHoldsItOnly()
    {
        [$view] = $this->mount('<p id="a">one <em>two</em></p><div id="slot"></div><p id="b">three</p>');
        $a = $view->getById('a');
        $b = $view->getById('b');
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertNotSame($a, $view->getById('a'));
        $this->assertSame($b, $view->getById('b'));
    }

    public function testAStyleThatBuildsMostOfTheBodyOtherwiseBuildsItWhole()
    {
        $page = str_repeat('<p>one <em>two</em></p>', 20).'<p id="x">three</p><div id="slot"></div>';
        [$view, $tui, $terminal] = $this->mount($page);
        $x = $view->getById('x');
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertNotSame($x, $view->getById('x'), 'Built again with the body, which costs less than its parts.');
        $full = $this->mount(self::innerReplaced($page, 'slot', '<style>em { display: block }</style>'));
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
    }

    public function testAStyleThatBuildsTheFieldsOfAFormOtherwiseBuildsThemOnly()
    {
        // Most of the body, but its controls, which a body built again labels and binds again, cost more than them
        $page = '<h1 id="x">T</h1><form>'.implode('', array_map(static fn (int $i) => '<p><label for="f'.$i.'">F'.$i.'</label> <input id="f'.$i.'"> <em>hint</em></p>', range(1, 20))).'</form><div id="slot"></div>';
        [$view, $tui, $terminal] = $this->mount($page);
        $x = $view->getById('x');
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertSame($x, $view->getById('x'), 'Not built again with the body.');
        $full = $this->mount(self::innerReplaced($page, 'slot', '<style>em { display: block }</style>'));
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
    }

    public function testAnUnknownIdOrAForeignWidgetIsRefused()
    {
        [$view] = $this->mount('<div id="slot"></div>');
        try {
            $view->setInnerHtml('nope', '<p>x</p>');
            $this->fail('An unknown id is refused.');
        } catch (InvalidArgumentException) {
        }
        $this->expectException(InvalidArgumentException::class);
        $view->setInnerHtml(new FormWidget(), '<p>x</p>');
    }

    public function testTheWidgetOfABlockContainerIsKept()
    {
        [$view] = $this->mount('<div id="slot"><p>one</p></div>');
        $slot = $view->getById('slot');
        $view->setInnerHtml($slot, '<p id="two">two</p>');

        $this->assertSame($slot, $view->getById('slot'));
        $this->assertSame($slot, $view->getById('two')->getParent());
    }

    public function testTheFocusOrderIsTheOneOfTheTree()
    {
        [$view, $tui] = $this->mount('<button id="a">A</button><div id="slot"><button id="old">Old</button></div><button id="z">Z</button>');
        $view->setInnerHtml('slot', '<button id="b">B</button><button id="c">C</button>');
        $tui->setFocus($view->getById('a'));
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }

        $this->assertSame(['b', 'c', 'z'], $order);
    }

    public function testAnAutofocusElementAddedIsNotFocused()
    {
        [$view, $tui] = $this->mount('<button id="a" autofocus>A</button><div id="slot"></div>');
        $focus = $tui->getFocus();
        $view->setInnerHtml('slot', '<button id="b" autofocus>B</button>');

        $this->assertSame($focus, $tui->getFocus());
    }

    public function testLabelsNameAcrossTheFragment()
    {
        [$view] = $this->mount('<div id="slot"><label for="x">Old name</label></div><input id="x"><label for="y">Outside</label>');
        $view->setInnerHtml('slot', '<p>no label</p><input id="y">');
        $this->assertNull($view->getById('x')->getLabel(), 'The label removed names it no more.');
        $this->assertSame('Outside', $view->getById('y')->getLabel(), 'A label outside names a control of the fragment.');

        $view->setInnerHtml('slot', '<label for="x">New name</label>');
        $this->assertSame('New name', $view->getById('x')->getLabel());
    }

    public function testControlsOutsideAreBoundToAFormOfTheFragment()
    {
        [$view] = $this->mount('<input id="a" name="a" form="f" aria-label="a" value="1"><div id="slot"></div><button id="go" form="f" name="go" value="y">Go</button>');
        $view->setInnerHtml('slot', '<form id="f"><input name="in" aria-label="in" value="2"></form>');
        $values = null;
        $view->querySelector('form')->onSubmit(static function (FormSubmitEvent $e) use (&$values) {
            $values = $e->getValues();
        });
        $view->getById('go')->activate();

        $this->assertSame(['a' => '1', 'in' => '2', 'go' => 'y'], $values, 'In the order of the document.');
    }

    public function testResetGivesControlsBoundLateTheirDefaultsAsBuilt()
    {
        $outside = '<input id="a" name="a" form="f" aria-label="a" value="init"><select id="s" name="s" form="f" aria-label="s"><option>one</option><option>two</option></select><input type="checkbox" id="c" name="c" form="f" aria-label="c" checked>';
        [$view] = $this->mount($outside.'<div id="slot"></div><button type="reset" id="r" form="f">R</button>');
        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $checkbox = $view->getById('c');
        $this->assertInstanceOf(CheckboxWidget::class, $checkbox);
        // Changed by the user before their form is added
        $view->getById('a')->setValue('typed');
        $select->getList()->setSelectedIndex(1);
        $checkbox->setChecked(false);
        $view->setInnerHtml('slot', '<form id="f"><input name="in" aria-label="in"></form>');
        $view->getById('r')->activate();

        $this->assertSame('init', $view->getById('a')->getValue());
        $this->assertSame(0, $select->getList()->getSelectedIndex());
        $this->assertTrue($checkbox->isChecked());
    }

    public function testWidgetsOutsideTheContentBuiltAgainWithItAreKept()
    {
        // The <span> holds a block now: its <div> is built again, the controls beside it kept as a browser keeps them
        [$view, $tui] = $this->mount('<div><input id="a" aria-label="a" value="init"> <select id="s" aria-label="s"><option>one</option><option>two</option></select> <span id="slot">inline</span></div><button id="z">Z</button>');
        $input = $view->getById('a');
        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $input->setValue('typed');
        $select->getList()->setSelectedIndex(1);
        $tui->setFocus($input);
        $pressed = 0;
        $input->on(SubmitEvent::class, static function () use (&$pressed) {
            ++$pressed;
        });
        $focusEvents = 0;
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function () use (&$focusEvents) {
            ++$focusEvents;
        });
        $view->setInnerHtml('slot', '<div>a block</div>');

        $this->assertSame($input, $view->getById('a'));
        $this->assertSame('typed', $input->getValue());
        $this->assertSame($select, $view->getById('s'));
        $this->assertSame(1, $select->getList()->getSelectedIndex());
        $this->assertSame($input, $tui->getFocus());
        $this->assertSame(0, $focusEvents, 'The focus does not move, not even for a moment.');
        $input->handleInput("\r");
        $this->assertSame(1, $pressed, 'The listeners of the page stay.');
        $tui->getFocusManager()->focusNext();
        $this->assertSame($select->getField(), $tui->getFocus());
        $tui->getFocusManager()->focusNext();
        $this->assertSame('z', $tui->getFocus()?->getId());
    }

    public function testAFormBuiltAgainAroundKeptControlsSubmitsAndResetsThem()
    {
        [$view] = $this->mount('<form id="f"><input id="a" name="a" aria-label="a" value="init"> <span id="slot">inline</span><input id="b" name="b" aria-label="b" value="b"><button id="go" name="go" value="y">Go</button><button type="reset" id="r">R</button></form>');
        $input = $view->getById('a');
        $input->setValue('typed');
        $view->setInnerHtml('slot', '<div><input name="in" aria-label="in" value="new"></div>');
        $form = $view->getById('f');
        $this->assertInstanceOf(FormWidget::class, $form);
        $values = [];
        $form->onSubmit(static function (FormSubmitEvent $e) use (&$values) {
            $values[] = $e->getValues();
        });
        $view->getById('go')->activate();

        $this->assertSame($input, $view->getById('a'));
        $this->assertSame([['a' => 'typed', 'in' => 'new', 'b' => 'b', 'go' => 'y']], $values, 'Once, in the order of the document.');
        $view->getById('r')->activate();
        $this->assertSame('init', $input->getValue());
    }

    public function testWhatTheUserChoseInAnAncestorBuiltAgainStays()
    {
        [$view, $tui] = $this->mount('<select id="s" aria-label="s"><option>one</option><option id="slot">two</option><option>three</option></select><details id="d"><summary id="summary">S</summary><p>content</p></details>');
        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $select->getList()->setSelectedIndex(1);
        $tui->setFocus($select->getField());
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $details->toggle();
        $view->setInnerHtml('slot', 'deux');
        $view->setInnerHtml('summary', 'Summary');

        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $this->assertSame(1, $select->getList()->getSelectedIndex());
        $this->assertSame('deux', $select->getList()->getSelectedItem()['label'] ?? null);
        $this->assertSame($select->getField(), $tui->getFocus(), 'The focus is on the <select> built again.');
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $this->assertTrue($details->isExpanded());
        $this->assertSame('Summary', $details->getSummary());
    }

    public function testTheFocusInADetailsBuiltAgainStaysWhereItIs()
    {
        [$view, $tui] = $this->mount('<details id="d"><summary id="summary">S</summary><input id="a" aria-label="a"></details>');
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $details->toggle();
        $input = $view->getById('a');
        $tui->setFocus($input);
        $focusEvents = 0;
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function () use (&$focusEvents) {
            ++$focusEvents;
        });
        $view->setInnerHtml('summary', 'Summary');

        $this->assertNotSame($details, $view->getById('d'));
        $this->assertSame($input, $tui->getFocus());
        $this->assertSame(0, $focusEvents);
    }

    public function testWhatOneSpliceAddsAnotherFinds()
    {
        [$view] = $this->mount('<div id="s1"></div><div id="s2"><span id="n">One</span><input id="y"></div><button id="go" form="f" name="go" value="g">Go</button>');
        $view->setInnerHtml('s1', '<input id="x" aria-labelledby="n"><label for="y">Named</label><input name="a" form="f" aria-label="a" value="1">');
        $view->setInnerHtml('s2', '<span id="n">Two</span><input id="y"><form id="f"><input name="in" aria-label="in" value="2"></form>');
        $values = null;
        $view->querySelector('form')->onSubmit(static function (FormSubmitEvent $e) use (&$values) {
            $values = $e->getValues();
        });
        $view->getById('go')->activate();

        $this->assertSame('Two', $view->getById('x')->getLabel(), 'Named by an element another splice changed.');
        $this->assertSame('Named', $view->getById('y')->getLabel(), 'Named by a label another splice added.');
        $this->assertSame(['a' => '1', 'in' => '2', 'go' => 'g'], $values, 'Bound to a form another splice added.');

        $view->setInnerHtml('s1', '<p>none</p>');
        $view->setInnerHtml('s2', '<input id="y" title="T">');
        $this->assertSame('T', $view->getById('y')->getLabel(), 'The label removed names it no more.');
    }

    public function testTheDefaultButtonIsTheFirstSubmitButtonOfTheDocument()
    {
        [$view] = $this->mount('<form id="f"><div id="s1"><button id="b1">One</button></div><input aria-label="in"><div id="s2"></div></form><button id="go" form="f">Go</button>');
        $view->setInnerHtml('s2', '<button id="b2">Two</button>');
        $this->assertTrue($view->getById('b1')->hasState('default'));
        $this->assertFalse($view->getById('b2')->hasState('default'));

        $view->setInnerHtml('s1', '<p>none</p>');
        $this->assertTrue($view->getById('b2')->hasState('default'), 'The next one, once the default is removed.');
        $this->assertFalse($view->getById('go')->hasState('default'));

        $view->setInnerHtml('s1', '<button id="b3">Three</button>');
        $this->assertTrue($view->getById('b3')->hasState('default'), 'One added before it.');
        $this->assertFalse($view->getById('b2')->hasState('default'));
    }

    public function testTheDescriptionOfAnOptionIsItsTitleOnly()
    {
        // aria-describedby of an <option> is not read, in a page built whole or not: nothing to compute again
        [$view] = $this->mount('<select id="x" size="2" aria-label="x"><option aria-describedby="d" title="T">a</option><option aria-describedby="d">b</option></select><div id="slot"><p id="d">One</p></div>');
        $view->setInnerHtml('slot', '<p id="d">Two</p>');
        $list = $view->getById('x');
        $this->assertInstanceOf(SelectListWidget::class, $list);

        $this->assertSame([['value' => 'a', 'label' => 'a', 'description' => 'T'], ['value' => 'b', 'label' => 'b']], $list->getItems());
    }

    public function testTheFocusOrderIsFoundWithoutGoingThroughTheContentAfterTheSlot()
    {
        // A field in a <details> before: in the order of the tree too
        [$view, $tui] = $this->mount('<details open><summary>S</summary><input aria-label="i"></details><button id="a">A</button><div id="slot"></div><p>tail</p><button id="z">Z</button>');
        $counting = static fn () => new class extends \Symfony\Component\Tui\Widget\ContainerWidget {
            public int $calls = 0;

            public function all(): array
            {
                ++$this->calls;

                return parent::all();
            }
        };
        // Content after the slot that holds nothing to focus, like the paragraphs of a long page: not gone through
        $tail = $counting();
        for ($i = 0; $i < 20; ++$i) {
            $tail->add(new \Symfony\Component\Tui\Widget\TextWidget('paragraph '.$i));
        }
        $body = $view->getBody();
        $body->replace($body->all()[3], $tail);
        // Fields of the application at the end, in the order of the tree: checked once, not at each splice
        $end = $counting();
        $body->add($end);
        $end->add(new \Symfony\Component\Tui\Widget\InputWidget());
        $end->add(new \Symfony\Component\Tui\Widget\InputWidget());
        $tail->calls = 0;
        $view->setInnerHtml('slot', '<p>text</p><button id="b0">B</button>');
        $this->assertSame(0, $tail->calls);
        $end->calls = 0;
        $view->setInnerHtml('slot', '<p>text</p><button id="b">B</button><button id="c">C</button>');

        $this->assertSame(0, $tail->calls);
        $this->assertSame(0, $end->calls);
        $tui->setFocus($view->getById('a'));
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }
        $this->assertSame(['b', 'c', 'z'], $order);

        // In a <details>, which takes the focus: after it
        [$view, $tui] = $this->mount('<button id="a">A</button><details open id="d"><summary>S</summary><div id="slot"></div></details><button id="z">Z</button>');
        $view->setInnerHtml('slot', '<button id="b">B</button>');
        $tui->setFocus($view->getById('a'));
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }
        $this->assertSame(['d', 'b', 'z'], $order);
    }

    public function testTheFocusOrderIsTheOneOfTheTreeWithWidgetsOfTheApplicationAround()
    {
        // Widgets of the application before the page in the Tui, added after it: last in the focus order, first in the tree
        $terminal = new VirtualTerminal(self::COLS, self::ROWS);
        $tui = new Tui(null, $terminal);
        $tui->start();
        $before = new \Symfony\Component\Tui\Widget\ContainerWidget();
        $tui->add($before);
        $view = (new HtmlBuilder())->build('<!DOCTYPE html><html><body><button id="a">A</button><div id="slot"></div><button id="z">Z</button></body></html>');
        $view->mount($tui);
        foreach (['x1', 'x2'] as $id) {
            $before->add((new \Symfony\Component\Tui\Widget\InputWidget())->setId($id));
        }
        $tui->processRender();
        $view->setInnerHtml('slot', '<button id="b">B</button>');
        $tui->setFocus($view->getById('a'));
        $tui->getFocusManager()->focusNext();
        $this->assertSame('b', $tui->getFocus()?->getId(), 'Before the next one of the tree, where it is in the focus order.');
        $tui->getFocusManager()->focusNext();
        $this->assertSame('z', $tui->getFocus()?->getId());

        // Still not in the order of the tree, one more after the page: found again by going through the tree
        $after = new \Symfony\Component\Tui\Widget\ContainerWidget();
        $tui->add($after);
        $after->add((new \Symfony\Component\Tui\Widget\InputWidget())->setId('y'));
        $view->setInnerHtml('slot', '<button id="c">C</button>');
        $tui->setFocus($view->getById('a'));
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }
        $this->assertSame(['c', 'z', 'x1'], $order);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: ?string, 4: list<string>, 5?: bool}>
     */
    public static function provideModalDialogsBuiltAgain(): iterable
    {
        $page = '<button id="opener">Open</button><p><button id="out">Out</button></p>';
        // The <span> holds a block now: the <dialog> is built again, the field beside it kept
        yield 'a slot in the dialog, the focus kept' => [$page.'<dialog id="d"><input id="a" aria-label="a"><span id="slot">x</span><button id="b">B</button></dialog>', 'slot', '<div>block <input id="c" aria-label="c"></div>', 'a', ['c', 'b', 'a', 'c']];
        // The focused element removed: none has the focus, like the focus fixup of a browser, the next Tab in the dialog
        // Displayed inline by the page: the <p> around it is built again, not the dialog
        yield 'a slot in a dialog displayed inline' => ['<style>dialog { display: inline }</style>'.$page.'<p>Text <dialog id="d"><input id="a" aria-label="a"><span id="slot">x</span><button id="b">B</button></dialog> after</p>', 'slot', '<div>block <input id="c" aria-label="c"></div>', 'a', ['c', 'b', 'a', 'c'], false];
        yield 'the content of the dialog, the focus in it' => [$page.'<dialog id="d"><input id="a" aria-label="a"><button id="b">B</button></dialog>', 'd', '<input id="a2" aria-label="a"><button id="c">C</button>', null, ['a2', 'c', 'a2', 'c']];
    }

    /**
     * @param list<string> $tabs
     */
    #[DataProvider('provideModalDialogsBuiltAgain')]
    public function testAModalDialogBuiltAgainStaysModal(string $body, string $slot, string $html, ?string $focus, array $tabs, bool $kept = true)
    {
        [$view, $tui, $terminal] = $this->mount($body);
        $tui->setFocus($view->getById('opener'));
        $dialog = $view->getById('d');
        $view->showModal($dialog);
        $tui->setFocus($view->getById('a'));
        $view->setInnerHtml($slot, $html);

        if ($kept) {
            $this->assertSame($dialog, $view->getById('d'), 'The <dialog> stays, like its element.');
        }
        $dialog = $view->getById('d');
        $this->assertTrue($dialog->hasState('open'));
        $this->assertTrue($dialog->hasState('modal'));
        $this->assertTrue(self::isInert($view->getById('out')), 'The page stays inert.');
        $this->assertFalse(self::isInert($view->getById('c')));
        $this->assertSame($focus, $tui->getFocus()?->getId());
        // A frame drawn, like before the next key
        $tui->processRender();
        $order = [];
        foreach ($tabs as $_) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }
        $this->assertSame($tabs, $order, 'Tab stays in the dialog.');

        // The backdrop over the page, like the page built whole with the dialog opened
        $tui->setFocus($view->getById('c'));
        $full = $this->mount(self::innerReplaced($body, $slot, $html));
        $full[0]->showModal($full[0]->getById('d'));
        $full[1]->setFocus($full[0]->getById('c'));
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));

        $view->close($dialog);
        $this->assertFalse(self::isInert($view->getById('out')));
        $this->assertFalse($dialog->hasState('modal'));
        $this->assertSame('opener', $tui->getFocus()?->getId(), 'The focus before goes back.');
    }

    public function testAnExclusiveAccordionHoldsTheDetailsOfTheTreeOnly()
    {
        $details = static fn (string $id, bool $open = false) => '<details name="x" id="'.$id.'"'.($open ? ' open' : '').'><summary id="s'.$id.'">'.$id.'</summary><p>content</p></details>';
        [$view] = $this->mount($details('a', true).'<div id="slot">'.$details('r', true).'</div>'.$details('b').'<div id="slot2">'.$details('g').'</div>');
        $this->assertFalse($view->getById('r')->isExpanded(), 'One open at most.');
        // Built again whole, opened again as the user left it
        $oldA = $view->getById('a');
        $this->assertInstanceOf(CollapsibleWidget::class, $oldA);
        $view->setInnerHtml('sa', 'A');
        $a = $view->getById('a');
        $this->assertInstanceOf(CollapsibleWidget::class, $a);
        $this->assertTrue($a->isExpanded());
        $b = $view->getById('b');
        $this->assertInstanceOf(CollapsibleWidget::class, $b);
        $b->toggle();
        $this->assertFalse($a->isExpanded(), 'Opening one closes the one built again.');
        $oldA->toggle();
        $oldA->toggle();
        $this->assertTrue($b->isExpanded(), 'The widget it replaced closes none.');
        // Removed, nothing built since: it closes none
        $gone = $view->getById('g');
        $this->assertInstanceOf(CollapsibleWidget::class, $gone);
        $view->setInnerHtml('slot2', '<p>none</p>');
        $gone->toggle();
        $this->assertTrue($b->isExpanded(), 'The one removed closes none.');

        // Those removed leave the group: they keep none closed, nor close those of the tree
        $old = $view->getById('r');
        $this->assertInstanceOf(CollapsibleWidget::class, $old);
        $old->toggle();
        $this->assertFalse($b->isExpanded(), 'Opening one closes the others in the tree.');
        // Removed open: it keeps none closed, nor closes those of the tree
        $view->setInnerHtml('slot', $details('n', true));
        $n = $view->getById('n');
        $this->assertInstanceOf(CollapsibleWidget::class, $n);
        $this->assertTrue($n->isExpanded(), 'The one removed open keeps it closed no more.');
        $old->toggle();
        $old->toggle();
        $this->assertTrue($n->isExpanded(), 'The one removed closes none.');
        $b->toggle();
        $this->assertFalse($n->isExpanded(), 'Opening one closes the one added.');
        $view->setInnerHtml('slot', $details('m', true));
        $this->assertFalse($view->getById('m')->isExpanded(), 'Added open while another is: closed, like a browser.');
    }

    public function testAKeptControlSubmitsItsFormOnce()
    {
        // Its form is not the one built again: bound to it once still
        [$view] = $this->mount('<form id="g"><button id="gb" name="gb" value="1">G</button></form><form id="f"><input id="a" name="a" form="g" aria-label="a" value="x"> <span id="slot">s</span></form>');
        $view->setInnerHtml('slot', '<div>block</div>');
        $submits = 0;
        $view->getById('g')->onSubmit(static function () use (&$submits) {
            ++$submits;
        });
        $view->getById('a')->handleInput("\r");
        $this->assertSame(1, $submits);

        // Its form built again: bound to the new one only
        [$view] = $this->mount('<form id="f"><input id="a" name="a" aria-label="a" value="x"> <span id="slot">s</span><button id="go" name="go" value="1">Go</button></form>');
        $old = $view->getById('f');
        $view->setInnerHtml('slot', '<div>block</div>');
        $submits = [];
        foreach ([$old, $view->getById('f')] as $i => $form) {
            $form->onSubmit(static function (FormSubmitEvent $e) use (&$submits, $i) {
                $submits[] = [$i, $e->getValues()];
            });
        }
        $view->getById('a')->handleInput("\r");
        $this->assertSame([[1, ['a' => 'x', 'go' => '1']]], $submits);
    }

    public function testControlsOutsideAFormBuiltAgainStayBoundToIt()
    {
        [$view] = $this->mount('<form id="f"><input id="a" name="a" aria-label="a" value="x"> <span id="slot">s</span></form><button id="go" form="f" name="go" value="1">Go</button><button type="reset" id="r" form="f">R</button>');
        $view->getById('a')->setValue('typed');
        $view->setInnerHtml('slot', '<div><input name="n" aria-label="n" value="n"></div>');
        $values = [];
        $view->getById('f')->onSubmit(static function (FormSubmitEvent $e) use (&$values) {
            $values[] = $e->getValues();
        });
        $view->getById('go')->activate();
        $this->assertSame([['a' => 'typed', 'n' => 'n', 'go' => '1']], $values);
        $view->getById('r')->activate();
        $this->assertSame('x', $view->getById('a')->getValue());
    }

    public function testAControlLeavesTheFormItsFormAttributeNamesNoMore()
    {
        // A form added before the other of the same id: the control is its, not the other's
        [$view] = $this->mount('<div id="slot"></div><input id="a" name="a" form="f" aria-label="a" value="x"><form id="f"><button id="go" name="go" value="1">Go</button></form>');
        $old = $view->getById('f');
        $view->setInnerHtml('slot', '<form id="f"><button id="go2" name="go2" value="2">Go</button></form>');
        $values = [];
        foreach ([$old, $view->getById('slot')->all()[0]] as $i => $form) {
            $this->assertInstanceOf(FormWidget::class, $form);
            $form->onSubmit(static function (FormSubmitEvent $e) use (&$values, $i) {
                $values[] = [$i, $e->getValues()];
            });
        }
        $view->getById('go')->activate();
        $view->getById('go2')->activate();
        $this->assertSame([[0, ['go' => '1']], [1, ['go2' => '2', 'a' => 'x']]], $values);

        // The first removed, the control goes back to the other, in the order of the document
        $view->setInnerHtml('slot', '');
        $values = [];
        $view->getById('go')->activate();
        $this->assertSame([[0, ['a' => 'x', 'go' => '1']]], $values);
    }

    public function testAControlOutsideARemovedFormSubmitsItNoMore()
    {
        [$view] = $this->mount('<input id="a" name="a" form="f" aria-label="a" value="x"><div id="slot"><form id="f"><input name="in" aria-label="in"><button>Go</button></form></div>');
        $form = $view->querySelector('form');
        $submits = 0;
        $form->onSubmit(static function () use (&$submits) {
            ++$submits;
        });
        $view->getById('a')->handleInput("\r");
        $this->assertSame(1, $submits);
        $view->setInnerHtml('slot', '<p>none</p>');
        $view->getById('a')->handleInput("\r");
        $this->assertSame(1, $submits);
    }

    public function testTheFocusOnAWidgetBuiltAgainGoesNowhereElseOnTheWay()
    {
        [$view, $tui] = $this->mount('<button id="z">Z</button><select id="s" aria-label="s"><option>one</option><option id="slot">two</option></select>');
        // The focus going to the first widget when the focused one is removed: not to it for a moment
        $tui->getFocusManager()->setTabStart(false);
        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $tui->setFocus($select->getField());
        $focused = [];
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function (FocusEvent $e) use (&$focused) {
            $focused[] = $e->getTarget();
        });
        $view->setInnerHtml('slot', 'deux');
        $select = $view->getById('s');
        $this->assertInstanceOf(DropdownWidget::class, $select);

        $this->assertSame([$select->getField()], $focused);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLinksOfTextBuiltAgain(): iterable
    {
        yield 'inline content' => ['<span id="slot">x</span>', '<b>bold</b>'];
        yield 'a block now' => ['<span id="slot">x</span>', '<div>block</div>'];
        yield 'links in the slot before' => ['<span id="slot"><a href="#s">s</a></span>', 'no link'];
    }

    #[DataProvider('provideLinksOfTextBuiltAgain')]
    public function testTheFocusOnALinkOfTextBuiltAgainStaysOnTheLink(string $slot, string $html)
    {
        // The text of the line is built again, its links outside the slot kept, like their elements
        [$view, $tui] = $this->mount('<button id="a">A</button><div><a href="#1">one</a> <a href="#2">two</a> '.$slot.' <a href="#3">three</a></div><button id="z">Z</button>');
        $tui->setFocus($view->getById('a'));
        $tui->getFocusManager()->focusNext();
        $text = $tui->getFocus();
        $this->assertInstanceOf(LinkedTextWidget::class, $text);
        $text->focusLink(1);
        $focusEvents = 0;
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function () use (&$focusEvents) {
            ++$focusEvents;
        });
        $view->setInnerHtml('slot', $html);
        $tui->processRender();

        $focus = $tui->getFocus();
        $this->assertInstanceOf(LinkedTextWidget::class, $focus);
        $this->assertSame('#2', $focus->getCurrentHref());
        $this->assertLessThanOrEqual(1, $focusEvents, 'On the text built again, nowhere else on the way.');
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function provideLinksBecomingCards(): iterable
    {
        // The <a> holds a block now: a card, the <p> built again around it
        $p = '<p>text <a href="#o">other</a> <a href="#c">card <span id="slot">x</span></a> end</p>';
        yield 'on the link that becomes a card' => [$p, 1, '#c'];
        yield 'on the link before it' => [$p, 0, '#o'];
        // A card already, built again: the link around the blocks
        yield 'on a card built again' => ['<div><a href="#c"><div>Title</div>caption <span id="slot">x</span></a></div><p><a href="#o">other</a></p>', 0, '#c'];
    }

    #[DataProvider('provideLinksBecomingCards')]
    public function testTheFocusOnALinkThatWrapsBlocksNowStaysOnIt(string $body, int $link, string $href)
    {
        [$view, $tui] = $this->mount('<button id="a">A</button>'.$body.'<button id="z">Z</button>');
        $tui->setFocus($view->getById('a'));
        $tui->getFocusManager()->focusNext();
        $text = $tui->getFocus();
        $this->assertInstanceOf(LinkedTextWidget::class, $text);
        $text->focusLink($link);
        $focusEvents = 0;
        $tui->getEventDispatcher()->addListener(FocusEvent::class, static function () use (&$focusEvents) {
            ++$focusEvents;
        });
        $view->setInnerHtml('slot', '<div>block</div>');
        $tui->processRender();

        $focus = $tui->getFocus();
        $this->assertInstanceOf(LinkedTextWidget::class, $focus);
        $this->assertSame($href, $focus->getCurrentHref());
        $this->assertLessThanOrEqual(1, $focusEvents);
    }

    public function testControlsKeepTheirStateWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view, $tui] = $this->mount('<p>one <em>two</em> <input id="a" aria-label="a" value="init"></p><details id="d"><summary>S</summary><p>c</p></details><div id="slot"></div>');
        $input = $view->getById('a');
        $input->setValue('typed');
        $tui->setFocus($input);
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $details->toggle();
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertSame($input, $view->getById('a'));
        $this->assertSame('typed', $input->getValue());
        $this->assertSame($input, $tui->getFocus());
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $this->assertTrue($details->isExpanded());
    }

    public function testAPopoverOpenStaysOpenWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view, $tui, $terminal] = $this->mount('<p>one <em>two</em> three</p><div id="p" popover>pop</div><div id="slot"></div>');
        $view->showPopover($view->getById('p'));
        $toggles = 0;
        $view->getById('p')->on(ToggleEvent::class, static function () use (&$toggles) {
            ++$toggles;
        });
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $popover = $view->getById('p');
        $this->assertTrue($popover->hasState('popover-open'));
        $this->assertSame(0, $toggles, 'Without the events of opening it: it is not opened now.');
        $full = $this->mount(self::innerReplaced('<p>one <em>two</em> three</p><div id="p" popover>pop</div><div id="slot"></div>', 'slot', '<style>em { display: block }</style>'));
        $full[0]->showPopover($full[0]->getById('p'));
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
        $view->hidePopover($popover);
        $this->assertFalse($popover->hasState('popover-open'), 'Open still for the view, which closes it.');
    }

    public function testAPopoverStaysAnchoredToItsInvokerWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view, $tui] = $this->mount('<style>[popover] { position-area: bottom span-right; margin: 0 }</style><div style="padding-left: 8"><button id="b" popovertarget="m">Tools</button></div><div popover id="m">One</div><p>one <em>two</em></p><div id="slot"></div>');
        $view->getById('b')->activate();
        $tui->processRender();
        $before = $tui->getWidgetRect($view->getById('m'));
        $this->assertSame(8, $before?->col);
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');
        $tui->processRender();

        $after = $tui->getWidgetRect($view->getById('m'));
        $this->assertSame([$before->row, $before->col, $before->columns], [$after?->row, $after?->col, $after?->columns]);
    }

    public function testAPopoverKeptStaysAnchoredToItsInvokerBuiltAgain()
    {
        // The <p> invoker displayed as flex now is built again; the popover, kept, follows its new widget
        $body = '<style>[popover] { position-area: bottom span-right; margin: 0 } .lead { margin-left: 10ch }</style><p>top</p><div><p class="lead" id="inv">invoker <b>here</b></p></div><div popover id="m">One</div><div id="slot"></div>';
        $html = '<style>.lead { display: flex; margin-left: 20ch }</style><p>moved down</p>';
        [$view, $tui] = $this->mount($body);
        $view->invoke('m', 'show-popover', $view->getById('inv'));
        $popover = $view->getById('m');
        $inv = $view->getById('inv');
        $view->setInnerHtml('slot', $html);
        $tui->processRender();
        $this->assertSame($popover, $view->getById('m'), 'Kept.');
        $this->assertNotSame($inv, $view->getById('inv'), 'Built again.');
        [$full, $fullTui] = $this->mount(self::innerReplaced($body, 'slot', $html));
        $full->invoke('m', 'show-popover', $full->getById('inv'));
        $fullTui->processRender();
        gc_collect_cycles();
        $tui->requestRender(true);
        $tui->processRender();

        $expected = $fullTui->getWidgetRect($full->getById('m'));
        $actual = $tui->getWidgetRect($view->getById('m'));
        $this->assertSame([$expected?->row, $expected?->col], [$actual?->row, $actual?->col]);
    }

    public function testAPopoverStaysAnchoredToItsInvokerBuiltAgainByASplice()
    {
        // The text of the <p> invoker built again with the <span> in it, a line taller
        $body = '<style>[popover] { position-area: bottom span-right; margin: 0 } p { margin: 0 }</style><p id="inv">invoker <span id="slot">x</span></p><div popover id="m">One</div><p>after</p>';
        $html = '<b>bold</b><br>second line';
        [$view, $tui] = $this->mount($body);
        $view->invoke('m', 'show-popover', $view->getById('inv'));
        $tui->processRender();
        $popover = $view->getById('m');
        $inv = $view->getById('inv');
        $view->setInnerHtml('slot', $html);
        gc_collect_cycles();
        $tui->processRender();
        $this->assertSame($popover, $view->getById('m'), 'Kept.');
        $this->assertNotSame($inv, $view->getById('inv'), 'Built again.');
        [$full, $fullTui] = $this->mount(self::innerReplaced($body, 'slot', $html));
        $full->invoke('m', 'show-popover', $full->getById('inv'));
        $fullTui->processRender();

        $expected = $fullTui->getWidgetRect($full->getById('m'));
        $actual = $tui->getWidgetRect($view->getById('m'));
        $this->assertNotNull($actual);
        $this->assertSame([$expected?->row, $expected?->col, $expected?->rows], [$actual->row, $actual->col, $actual->rows]);
    }

    public function testAPopoverOpenBuiltAgainByASpliceStaysOpen()
    {
        // The <span> popover holds a block now: built again with the line around it, open and anchored still
        $body = '<style>[popover] { position-area: bottom span-right; margin: 0 } p { margin: 0 }</style><p style="padding-left: 6ch"><button id="b" popovertarget="m">open</button></p><div>line <span popover id="m">pop <span id="slot">x</span></span> after</div>';
        $html = '<div>block</div>';
        [$view, $tui, $terminal] = $this->mount($body);
        $view->getById('b')->activate();
        $tui->processRender();
        $popover = $view->getById('m');
        $view->setInnerHtml('slot', $html);
        $tui->processRender();

        $this->assertNotSame($popover, $view->getById('m'), 'Built again.');
        $this->assertTrue($view->getById('m')->hasState('popover-open'));
        $full = $this->mount(self::innerReplaced($body, 'slot', $html));
        $full[0]->getById('b')->activate();
        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
        $view->hidePopover($view->getById('m'));
        $this->assertFalse($view->getById('m')->hasState('popover-open'), 'Open for the view, which closes it.');
    }

    public function testAPopoverShownByInterestHidesWithItWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view, $tui] = $this->mount('<p><button id="save" interestfor="tip">Save</button> <input id="field" aria-label="f"></p><div popover="hint" id="tip">Saves</div><p>one <em>two</em></p><div id="slot"></div>');
        $tui->setFocus($view->getById('save'));
        $this->assertTrue($view->getById('tip')->hasState('popover-open'));
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertTrue($view->getById('tip')->hasState('popover-open'));
        $tui->setFocus($view->getById('field'));
        $this->assertFalse($view->getById('tip')->hasState('popover-open'), 'Shown by the interest still, which it loses.');
    }

    public function testAScrollContainerKeepsItsOffsetWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        $body = '<style>#box { overflow: auto; max-height: 3; width: 10ch; white-space: pre } p { margin: 0 }</style><p>one <em>two</em></p><div id="box"><p>0</p><p>1</p><p>2</p><p>3 a long line</p><p>4</p></div><div id="slot"></div>';
        [$view, $tui, $terminal] = $this->mount($body);
        $box = $view->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $box);
        $box->scrollTo(2);
        $box->scrollToColumn(3);
        $tui->processRender();
        $this->assertSame([2, 3], [$box->getScrollTop(), $box->getScrollLeft()]);
        $view->setInnerHtml('slot', '<style>em { display: block } #box { position: static }</style>');
        $tui->processRender();
        $this->assertNotSame($box, $view->getById('box'), 'Built again, its position one of the build properties.');

        $box = $view->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $box);
        $this->assertSame([2, 3], [$box->getScrollTop(), $box->getScrollLeft()]);
    }

    public function testAScrollContainerBuiltAgainAroundTheSpliceKeepsItsOffset()
    {
        // The <span> holds a block now: the box around it, in the line of the text, is built again with the line
        $body = '<style>#box { display: inline-block; overflow: auto; max-height: 3; width: 12ch; vertical-align: top } p { margin: 0 }</style><p>text <span id="box">0<br>1<br>2<br>3<br>4 <span id="slot">x</span></span> after</p>';
        [$view, $tui] = $this->mount($body);
        $box = $view->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $box);
        $box->scrollTo(2);
        $tui->processRender();
        $this->assertSame(2, $box->getScrollTop());
        $view->setInnerHtml('slot', '<div>block</div>');
        $tui->processRender();

        $this->assertNotSame($box, $view->getById('box'), 'Built again.');
        $box = $view->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $box);
        $this->assertSame(2, $box->getScrollTop());
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideTargetTexts(): iterable
    {
        $style = '<style>::target-text { background: yellow; color: black }</style>';
        yield 'a style splice' => [$style.'<p>one <em>two</em> three</p><div id="slot"></div>', 'slot', '<style>em { display: block }</style>', ':~:text=two'];
        yield 'the line around the splice' => [$style.'<p>alpha <span id="slot">x</span> beta</p>', 'slot', '<b>y</b>', ':~:text=beta'];
    }

    #[DataProvider('provideTargetTexts')]
    public function testTheTargetTextOfTextBuiltAgainStays(string $body, string $id, string $html, string $fragment)
    {
        [$view, $tui, $terminal] = $this->mount($body);
        $this->assertTrue($view->navigate($fragment));
        $view->setInnerHtml($id, $html);
        $full = $this->mount(self::innerReplaced($body, $id, $html));
        $full[0]->navigate($fragment);

        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function provideTargetTextsAroundOtherNodes(): iterable
    {
        // A text block built again with a single match: found again by its pattern
        yield 'after a <br>' => ['<p>alpha<br>same words <span id="slot">a</span></p>', 'b', ':~:text=same', ':~:text=same'];
        yield 'text around a block' => ['<div>Intro<p>x</p>same words <span id="slot">a</span></div>', 'b', ':~:text=same', ':~:text=same'];
        yield 'after a <select>' => ['<p>a <select aria-label="s"><option>same</option></select> same words <span id="slot">x</span></p>', 'b', ':~:text=same', ':~:text=same'];
    }

    /**
     * The text ::target-text goes to and where in it, after a splice that builds it again, is that of the page built
     * whole.
     */
    #[DataProvider('provideTargetTextsAroundOtherNodes')]
    public function testTheTargetTextOfATextBlockBuiltAgainAroundOtherNodesStays(string $body, string $html, string $fragment, string $wholeFragment)
    {
        [$view] = $this->mount($body);
        $this->assertTrue($view->navigate($fragment));
        $view->setInnerHtml('slot', $html);
        [$full] = $this->mount(self::innerReplaced($body, 'slot', $html));
        $this->assertTrue($full->navigate($wholeFragment));

        $this->assertNotSame([], self::targetTexts($full));
        $this->assertSame(self::targetTexts($full), self::targetTexts($view));
    }

    /**
     * The texts ::target-text draws: the text of each block, and where its match starts.
     *
     * @return list<array{string, int}>
     */
    private static function targetTexts(HtmlView $view): array
    {
        $targets = [];
        foreach ((new \ReflectionProperty($view->getStyleSheet(), 'targetTexts'))->getValue($view->getStyleSheet()) as [$text, $pattern]) {
            $source = $text->getSourceText();
            preg_match($pattern, $source, $match, \PREG_OFFSET_CAPTURE);
            $targets[] = [$source, $match[1][1] ?? -1];
        }

        return $targets;
    }

    public function testTheTargetTextOfTheElementReplacedGoesWithItWhileAPopoverIsOpen()
    {
        // Its text that of the element whose content is replaced, the popovers open given back by the same splice
        [$view] = $this->mount('<div id="m" popover>pop</div><div id="slot">same text</div>');
        $view->showPopover($view->getById('m'));
        $this->assertTrue($view->navigate(':~:text=same'));
        $view->setInnerHtml('slot', '<style>p { color: red }</style>same other');

        $this->assertTrue($view->getById('m')->hasState('popover-open'));
        $this->assertSame([], self::targetTexts($view));
    }

    public function testTheTargetTextOfTheContentReplacedGoesWithIt()
    {
        $body = '<style>::target-text { background: yellow; color: black }</style><p>keep</p><div id="slot"><p>gone</p></div>';
        [$view, $tui, $terminal] = $this->mount($body);
        $this->assertTrue($view->navigate(':~:text=gone'));
        $view->setInnerHtml('slot', '<p>gone</p>');
        $full = $this->mount(self::innerReplaced($body, 'slot', '<p>gone</p>'));

        $this->assertSame($this->screen($full[1], $full[2]), $this->screen($tui, $terminal), 'Like a range of the nodes removed.');
    }

    public function testAScrollContainerWhoseContentIsReplacedKeepsItsOffset()
    {
        // A <form> is built again whole, not kept like a <div>: its element stays, scrolled, like in a browser
        $lines = '<p>0</p><p>1</p><p>2</p><p>3</p><p>4</p><p>5</p>';
        [$view, $tui] = $this->mount('<style>#f { overflow: auto; max-height: 3 } p { margin: 0 }</style><form id="f">'.$lines.'</form>');
        $form = $view->getById('f');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $form);
        $form->scrollTo(2);
        $tui->processRender();
        $view->setInnerHtml('f', $lines);
        $tui->processRender();

        $this->assertNotSame($form, $view->getById('f'), 'Built again.');
        $form = $view->getById('f');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $form);
        $this->assertSame(2, $form->getScrollTop());
    }

    public function testTheContainersScrolledAreNeitherHeldNorSharedByViews()
    {
        $body = '<style>.box { overflow: auto; max-height: 2 } p { margin: 0 }</style><p>line <span id="slot">x</span></p><div class="box" id="box"><p>0</p><p>1</p><p>2</p><p>3</p></div>';
        [$a, $tuiA] = $this->mount($body);
        [$b, $tuiB] = $this->mount($body);
        $boxA = $a->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $boxA);
        $boxA->scrollTo(2);
        $tuiA->processRender();
        $scrolled = static fn (): int => \count(ContainerState::$scrolled ?? []);
        $before = $scrolled();
        // The box of the other view built again, with the body, for a style sheet: as it was, not scrolled
        $b->setInnerHtml('slot', '<style>* { float: none }</style>');
        $tuiB->processRender();
        $boxB = $b->getById('box');
        $this->assertInstanceOf(\Symfony\Component\TuiHtml\Widget\ContainerWidget::class, $boxB);
        $this->assertSame(0, $boxB->getScrollTop());
        $this->assertSame(2, $boxA->getScrollTop());
        // Not held once the view is gone
        unset($a, $tuiA, $boxA);
        gc_collect_cycles();
        $this->assertLessThan($before, $scrolled());
    }

    public function testTheFocusOnAnElementOutsideStaysWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view, $tui] = $this->mount('<p>one <em>two</em></p><div id="f" tabindex="0">focus me</div><div id="slot"></div>');
        $tui->setFocus($view->getById('f'));
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');
        $tui->processRender();

        $this->assertNotNull($tui->getFocus());
        $this->assertSame($view->getById('f'), $tui->getFocus());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFocusablesBuiltAgainForAStyle(): iterable
    {
        yield 'a <details>' => ['<div><details id="f" class="x"><summary>S</summary>c</details></div>', '.x { float: none }'];
        yield 'a link of a block' => ['<div><p class="x" id="f"><a href="#a">link</a> text</p></div>', '.x { display: flex }'];
        yield 'an element with a tab index' => ['<div><div id="f" class="x" tabindex="0">t</div></div>', '.x { display: flex }'];
    }

    #[DataProvider('provideFocusablesBuiltAgainForAStyle')]
    public function testTheFocusRestoredOnAWidgetBuiltAgainForAStyleStays(string $body, string $css)
    {
        $page = '<button id="a">A</button>'.$body.'<p>after</p><div id="slot"></div><button id="z">Z</button>';
        [$view, $tui] = $this->mount($page);
        $tui->setFocus($view->getById('f'));
        $old = $tui->getFocus();
        $this->assertNotNull($old);
        $view->setInnerHtml('slot', '<style>'.$css.'</style>');
        $tui->processRender();

        $focus = $tui->getFocus();
        $this->assertNotNull($focus);
        $this->assertNotSame($old, $focus, 'Built again.');
        $this->assertSame(self::top($focus), self::top($view->getRoot()), 'In the tree.');
        // One of the focus order stays in it, the next one after it; another, like an element with a tab index, is not
        // in it, built whole too
        if ($focus instanceof FocusableInterface) {
            $this->assertContains($focus, $tui->getFocusManager()->getCycle(), 'In the focus order, not dropped by keepFocusWhile().');
            $tui->getFocusManager()->focusNext();
            $this->assertSame('z', $tui->getFocus()?->getId());
        }
    }

    public function testTheTargetStaysWhenAStyleOfTheSpliceDisplaysThePageOtherwise()
    {
        [$view] = $this->mount('<p>one <em>two</em></p><h2 id="t">target</h2><div id="slot"></div>');
        $this->assertTrue($view->navigate('#t'));
        $view->setInnerHtml('slot', '<style>em { display: block }</style>');

        $this->assertSame($view->getById('t'), $view->getTarget());
        $this->assertTrue($view->getById('t')->hasState('target'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideContentOutsideAModalDialog(): iterable
    {
        // The <table> outside it built again whole, in the place of the inert one
        yield 'a table built again' => ['<div><dialog id="d"><button id="c">c</button></dialog></div><p>t</p><table id="s"><tr><td><button id="x">x</button></td></tr></table>', 's', '<tr><td><button id="y">y</button></td></tr>'];
        // A block beside the dialog in a container whose content is built again, the dialog kept
        yield 'a block beside it' => ['<div id="t"><div><dialog id="d"><button id="c">c</button></dialog></div> <span id="s">x</span></div>', 's', '<div><button id="y">y</button></div>'];
    }

    #[DataProvider('provideContentOutsideAModalDialog')]
    public function testWhatASpliceBuildsAroundAModalDialogIsInert(string $body, string $slot, string $html)
    {
        [$view, $tui] = $this->mount($body);
        $view->showModal($view->getById('d'));
        $view->setInnerHtml($slot, $html);
        $tui->processRender();

        $this->assertTrue(self::isInert($view->getById('y')));
        $this->assertFalse(self::isInert($view->getById('c')));
        $order = [];
        for ($i = 0; $i < 3; ++$i) {
            $tui->getFocusManager()->focusNext();
            $order[] = $tui->getFocus()?->getId();
        }
        $this->assertSame(['c', 'c', 'c'], $order);
        $view->close($view->getById('d'));
        $this->assertFalse(self::isInert($view->getById('y')), 'Given back on close.');
    }

    public function testAnElementOutsideTheBodyIsRefusedBeforeAnythingChanges()
    {
        $terminal = new VirtualTerminal(self::COLS, self::ROWS);
        $tui = new Tui(null, $terminal);
        $tui->start();
        $view = (new HtmlBuilder())->build('<!DOCTYPE html><html><head><style id="st">p { color: red }</style></head><body><p>x</p></body></html>');
        $view->mount($tui);
        $tui->processRender();
        $before = $this->screen($tui, $terminal);
        try {
            $view->setInnerHtml('st', 'p { color: blue }');
            $this->fail('An element of the <head> is refused.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame($before, $this->screen($tui, $terminal));
        $full = $this->mount('<p>x</p>');
        $this->assertNotSame($this->screen($full[1], $full[2]), $before, 'The style of the head applies still.');
    }

    public function testAFrameAddedWhileAnotherIsRemovedIsMounted()
    {
        // The <p> is displayed inline once the <b> is there: its frame goes, not built again, the new one comes
        $frame = static fn (string $id) => '<iframe srcdoc="&lt;button id=&quot;'.$id.'1&quot; tabindex=&quot;-1&quot;&gt;x&lt;/button&gt;&lt;button id=&quot;'.$id.'2&quot;&gt;y&lt;/button&gt;"></iframe>';
        [$view, $tui] = $this->mount('<style>div:has(b) > p { display: inline }</style><div><p>text '.$frame('old').'</p> <span id="slot">x</span></div>');
        $view->setInnerHtml('slot', '<b>b</b><div>'.$frame('new').'</div>');
        $tui->processRender();

        $cycle = array_map(static fn ($widget) => $widget->getId(), $tui->getFocusManager()->getCycle());
        $this->assertSame(['new2'], $cycle, 'Its tab indexes given to the focus manager, like the frames of a page mounted.');
    }

    public function testTheOptionChosenInAFilteredListBuiltAgainStays()
    {
        [$view] = $this->mount('<select id="s" size="3" aria-label="s"><option id="slot">apple</option><option>banana</option><option>cherry</option></select>');
        $list = $view->getById('s');
        $this->assertInstanceOf(SelectListWidget::class, $list);
        $list->setFilter('c');
        $this->assertSame('cherry', $list->getSelectedItem()['label'] ?? null);
        $view->setInnerHtml('slot', 'apricot');

        $list = $view->getById('s');
        $this->assertInstanceOf(SelectListWidget::class, $list);
        $this->assertSame('cherry', $list->getSelectedItem()['label'] ?? null);
    }

    public function testADetailsBuiltAgainOpensWithoutTheEventsOfTheUser()
    {
        [$view, $tui] = $this->mount('<details id="d"><summary id="summary">S</summary><p>c</p></details>');
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $details->toggle();
        $events = [];
        foreach ([ExpandEvent::class, CollapseEvent::class, ToggleEvent::class] as $class) {
            $tui->getEventDispatcher()->addListener($class, static function (object $event) use (&$events) {
                $events[] = $event::class;
            });
        }
        $view->setInnerHtml('summary', 'T');

        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $this->assertTrue($details->isExpanded());
        $this->assertSame([], $events);
    }

    public function testTheFocusablesASpliceRemovesAreNotHeldByTheView()
    {
        [$view] = $this->mount('<button id="a">A</button><div id="slot"></div><button id="z">Z</button>');
        $view->setInnerHtml('slot', '<button id="b">B</button>');
        $removed = \WeakReference::create($view->getById('b'));
        $view->setInnerHtml('slot', '<p>nothing to focus</p>');
        gc_collect_cycles();

        $this->assertNull($removed->get());
    }

    private static function isInert(\Symfony\Component\Tui\Widget\AbstractWidget $widget): bool
    {
        for ($current = $widget; null !== $current; $current = $current->getParent()) {
            if ($current->hasState('inert')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{HtmlView, Tui, VirtualTerminal}
     */
    private static function top(\Symfony\Component\Tui\Widget\AbstractWidget $widget): \Symfony\Component\Tui\Widget\AbstractWidget
    {
        while (null !== $parent = $widget->getParent()) {
            $widget = $parent;
        }

        return $widget;
    }

    private function mount(string $body): array
    {
        $terminal = new VirtualTerminal(self::COLS, self::ROWS);
        $tui = new Tui(null, $terminal);
        $tui->start();
        $view = (new HtmlBuilder())->build('<!DOCTYPE html><html><body>'.$body.'</body></html>');
        $view->mount($tui);
        $tui->processRender();

        return [$view, $tui, $terminal];
    }

    private function screen(Tui $tui, VirtualTerminal $terminal): string
    {
        $terminal->clearOutput();
        $tui->requestRender(true);
        $tui->processRender();
        $screen = new ScreenBuffer(self::COLS, self::ROWS);
        $screen->write($terminal->getOutput());

        return $screen->getStyledScreen();
    }

    /**
     * The body with the content of the element of the id replaced, as markup: the page built whole to compare with.
     */
    private static function innerReplaced(string $body, string $id, string $html): string
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$body.'</body></html>', \LIBXML_NOERROR);
        $document->getElementById($id)->innerHTML = $html;

        return $document->body->innerHTML;
    }
}
