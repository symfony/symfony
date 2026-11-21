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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Event\PressEvent;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\CheckboxWidget;
use Symfony\Component\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

final class HtmlViewPointerTest extends TestCase
{
    private const HTML = <<<'HTML'
        <style>
          .row { display: flex }
          button:hover { color: red }
          .row:hover { font-weight: bold }
        </style>
        <main>
          <p id="intro" title="The introduction">Intro</p>
          <div class="row" title="Actions"><button id="save">Save</button><button id="off" disabled>Off</button></div>
          <label><input type="checkbox" id="box"> Box</label>
          <details id="more"><summary>More</summary><p>Hidden</p></details>
        </main>
        HTML;

    public function testWidgetAt()
    {
        [$tui, $view] = $this->mount();
        $this->assertSame('intro', $view->widgetAt(0, 2)?->getId());
        $this->assertSame('save', $view->widgetAt(1, 2)?->getId());
        $this->assertSame('off', $view->widgetAt(1, 10)?->getId());
        $this->assertSame('row', $view->widgetAt(1, 25)?->getStyleClasses()[0] ?? null, 'Past the items, the row itself.');
        $this->assertNull($view->widgetAt(50, 0), 'Outside the view.');
    }

    public function testAHiddenWidgetHasNoRect()
    {
        [$tui, $view] = $this->mount();
        $row = $view->getById('save')->getParent();
        $this->assertNotNull($tui->getWidgetRect($view->getById('save')));
        StyleBridge::set($row, (StyleBridge::get($row) ?? new Style())->withHidden(true));
        $tui->requestRender();
        $tui->processRender();
        $this->assertNull($tui->getWidgetRect($row));
        $this->assertNull($tui->getWidgetRect($view->getById('save')), 'Nor what is in it, drawn before.');
    }

    public function testHoverSetsTheStateOnTheWidgetAndItsAncestors()
    {
        [$tui, $view] = $this->mount();
        $view->hover(1, 2);
        $this->assertTrue($view->getById('save')->hasState('hover'));
        $this->assertTrue($view->getById('save')->getParent()->hasState('hover'), 'Ancestors are hovered too, like in CSS.');
        $tui->processRender();
        $view->hover(0, 2);
        $this->assertTrue((new \ReflectionProperty(\Symfony\Component\Tui\Tui::class, 'renderRequested'))->getValue($tui), 'Hovering something else repaints.');
        $this->assertFalse($view->getById('save')->hasState('hover'));
        $this->assertTrue($view->getById('intro')->hasState('hover'));
        $tui->processRender();
        $this->assertSame([$view->getById('intro')], array_values(array_filter($view->querySelectorAll('p'), static fn ($w) => $w->hasState('hover'))));
    }

    public function testPressMakesTheWidgetAndItsAncestorsActive()
    {
        [$tui, $view] = $this->mount();
        $save = $view->getById('save');
        $this->assertSame($save, $view->press(1, 2));
        $this->assertTrue($save->hasState('active'));
        $this->assertTrue($save->getParent()->hasState('active'), 'Ancestors are active too, like in CSS.');
        $this->assertNotEmpty($view->querySelectorAll('button:active'));
        $view->press(0, 2);
        $this->assertFalse($save->hasState('active'), 'A new press releases the previous one.');
        $view->release();
        $this->assertFalse($view->getById('intro')->hasState('active'));
        $view->press(1, 2);
        $view->click(1, 2);
        $this->assertFalse($save->hasState('active'), 'A click releases the button.');
    }

    public function testAKeyThatActivatesTheFocusMakesItActiveAMoment()
    {
        [$tui, $view] = $this->mount();
        $save = $view->getById('save');
        $tui->setFocus($save);
        $tui->handleInput('x');
        $this->assertFalse($save->hasState('active'), 'Only the keys that activate.');
        $tui->handleInput("\r");
        $this->assertTrue($save->hasState('active'));
        $this->assertTrue($save->getParent()->hasState('active'), 'Ancestors are active too.');
        usleep(200000);
        $tui->tick();
        $this->assertFalse($save->hasState('active'), 'Released once the moment passed, terminals not telling when the key is.');
        $tui->setFocus($view->getById('box'));
        $tui->handleInput(' ');
        $this->assertTrue($view->getById('box')->hasState('active'));
    }

    public function testClickPressesFocusesAndToggles()
    {
        [$tui, $view] = $this->mount();
        $pressed = 0;
        $view->getById('save')->on(PressEvent::class, static function () use (&$pressed) {
            ++$pressed;
        });
        $this->assertTrue($view->click(1, 2));
        $this->assertSame(1, $pressed);
        $this->assertSame($view->getById('save'), $tui->getFocus());
        $this->assertFalse($view->click(1, 25), 'A disabled button does nothing.');
        $box = $view->getById('box');
        $this->assertInstanceOf(CheckboxWidget::class, $box);
        $rect = $tui->getWidgetRect($box);
        $this->assertTrue($view->click($rect->row, $rect->col));
        $this->assertTrue($box->isChecked());
        $details = $view->getById('more');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $rect = $tui->getWidgetRect($details);
        $view->click($rect->row, $rect->col);
        $this->assertTrue($details->isExpanded());
        $this->assertFalse($view->click(0, 2), 'Text has no action.');
    }

    public function testTitleAt()
    {
        [, $view] = $this->mount();
        $this->assertSame('The introduction', $view->titleAt(0, 0));
        $this->assertSame('Actions', $view->titleAt(1, 2), 'The closest ancestor with a title.');
        $this->assertNull($view->titleAt(50, 0));
    }

    public function testMountInAContainer()
    {
        $terminal = new VirtualTerminal(50, 12);
        $tui = new Tui(null, $terminal);
        $tui->add(new \Symfony\Component\TuiHtml\Widget\TextWidget('Above'));
        $tui->add($host = new \Symfony\Component\TuiHtml\Widget\ContainerWidget());
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(self::HTML)->mount($tui, $host);
        $tui->start();
        $tui->processRender();
        $this->assertSame($host, $view->getRoot()->getParent());
        $this->assertSame('intro', $view->widgetAt(1, 2)?->getId(), 'Coordinates are the terminal ones.');
        $this->assertTrue($view->click(2, 2));
    }

    public function testHiddenWidgetsAreNotHit()
    {
        [$tui, $view] = $this->mount();
        $view->getStyleSheet()->add('#intro.gone { display: none }');
        $intro = $view->getById('intro');
        $rect = $tui->getWidgetRect($intro);
        $intro->addStyleClass('gone');
        $tui->requestRender();
        $tui->processRender();
        $this->assertNotSame($intro, $view->widgetAt($rect->row, $rect->col), 'A hidden widget keeps its last rect, but cannot be hit.');
        $this->assertNotSame('The introduction', $view->titleAt($rect->row, $rect->col));
    }

    public function testNotMounted()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(self::HTML);
        $this->assertNull($view->widgetAt(0, 0));
        $this->assertFalse($view->click(0, 0));
        $this->assertNull($view->titleAt(0, 0));
    }

    public function testHiddenWidgetsLeaveTheFocusCycleAndTheHitTests()
    {
        $terminal = new VirtualTerminal(50, 12);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.gone { display: none }</style><main>
            <input id="a" aria-label="A">
            <div id="gone" class="gone"><input id="b" aria-label="B"></div>
            <input id="c" aria-label="C" style="visibility: hidden">
            <input id="d" aria-label="D">
        </main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(['a', 'd'], array_map(static fn ($widget) => $widget->getId(), $tui->getFocusManager()->getCycle()));
        $this->assertNotNull($tui->getWidgetRect($view->getById('c')), 'An invisible widget keeps its room.');
        $this->assertNotSame('c', $view->widgetAt($tui->getWidgetRect($view->getById('c'))->row, 1)?->getId());
        // Shown again, the input comes back into the cycle
        $view->getById('gone')->removeStyleClass('gone');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(['a', 'b', 'd'], array_map(static fn ($widget) => $widget->getId(), $tui->getFocusManager()->getCycle()));
    }

    public function testAClickOnALabelGoesToItsControl()
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<main>
            <label for="name" id="name-label">Name</label>
            <input id="name">
            <label id="wrap"><input type="checkbox" id="box"> Remember me</label>
            <label for="nowhere" id="orphan">Orphan</label>
        </main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $label = $tui->getWidgetRect($view->getById('name-label'));
        $this->assertTrue($view->click($label->row, $label->col));
        $this->assertSame($view->getById('name'), $tui->getFocus());
        $box = $view->getById('box');
        $this->assertInstanceOf(CheckboxWidget::class, $box);
        $wrap = $tui->getWidgetRect($view->getById('wrap'));
        // On the text of the label, past the box
        $this->assertTrue($view->click($wrap->row, $wrap->col + 8));
        $this->assertTrue($box->isChecked(), 'A click on the label toggles the checkbox.');
        $orphan = $tui->getWidgetRect($view->getById('orphan'));
        $this->assertFalse($view->click($orphan->row, $orphan->col), 'A label for no element does nothing.');
    }

    public function testADisabledFieldsetDisablesItsControlsButTheOnesOfItsLegend()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<fieldset disabled id="set">
            <legend><input type="checkbox" id="toggle" aria-label="Enable"></legend>
            <input id="a" aria-label="A">
            <div><select id="b" aria-label="B"><option>x</option></select></div>
            <p id="text">text</p>
            <fieldset><legend><input id="c" aria-label="C"></legend></fieldset>
        </fieldset>
        <input id="d" aria-label="D">');
        $disabled = static fn (string $id) => $view->getById($id)?->hasState('disabled');
        $this->assertTrue($disabled('set'));
        $this->assertFalse($disabled('toggle'), 'The first legend stays enabled.');
        $this->assertTrue($disabled('a'));
        $this->assertTrue($disabled('b'));
        $this->assertFalse($disabled('text'), 'Only form controls are disabled.');
        $this->assertTrue($disabled('c'), 'The legend of a nested fieldset is not the one of the disabled fieldset.');
        $this->assertFalse($disabled('d'));
    }

    public function testPointerEventsNoneLetsThePointerThrough()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>
            .overlay { pointer-events: none } .overlay .back { pointer-events: auto }
        </style><main id="main"><div class="overlay" id="overlay"><p id="ghost">ghost</p><p id="back" class="back">back</p></div></main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $ghost = $tui->getWidgetRect($view->getById('ghost'));
        $this->assertSame('main', $view->widgetAt($ghost->row, $ghost->col)?->getId(), 'Through the overlay and its text, to what is behind.');
        $back = $tui->getWidgetRect($view->getById('back'));
        $this->assertSame('back', $view->widgetAt($back->row, $back->col)?->getId(), 'A descendant can take the pointer back.');
    }

    public function testCursorAt()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.help { cursor: url(help.png), help }</style><main>
            <p id="text">text</p>
            <input id="input" aria-label="Input">
            <button id="button">Go</button>
            <button id="off" disabled>Off</button>
            <p id="help" class="help">help</p>
        </main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $cursor = static function (string $id) use ($tui, $view) {
            $rect = $tui->getWidgetRect($view->getById($id));

            return $view->cursorAt($rect->row, $rect->col);
        };
        $this->assertSame('default', $cursor('text'));
        $this->assertSame('text', $cursor('input'));
        $this->assertSame('pointer', $cursor('button'));
        $this->assertSame('default', $cursor('off'));
        $this->assertSame('help', $cursor('help'), 'The fallback keyword after an image.');
        $this->assertNull($view->cursorAt(50, 0));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p id="evil" style="cursor: pointer\x1b]52;c;Zm9v\x07">evil</p>')->mount($tui = new Tui(null, new VirtualTerminal(40, 8)));
        $tui->start();
        $tui->processRender();
        $rect = $tui->getWidgetRect($view->getById('evil'));
        $this->assertSame('default', $view->cursorAt($rect->row, $rect->col), 'Never anything but a cursor keyword: the value goes into a terminal sequence.');
    }

    public function testDetailsSharingANameAreAnAccordion()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<main>
            <details name="faq" id="a" open><summary>A</summary><p>a</p></details>
            <details name="faq" id="b" open><summary>B</summary><p>b</p></details>
            <details name="other" id="c" open><summary>C</summary><p>c</p></details>
        </main>');
        $a = $view->getById('a');
        $b = $view->getById('b');
        $c = $view->getById('c');
        $this->assertInstanceOf(CollapsibleWidget::class, $a);
        $this->assertInstanceOf(CollapsibleWidget::class, $b);
        $this->assertInstanceOf(CollapsibleWidget::class, $c);
        $this->assertSame([true, false, true], [$a->isExpanded(), $b->isExpanded(), $c->isExpanded()], 'Only the first of a name opens.');
        $b->expand();
        $this->assertSame([false, true, true], [$a->isExpanded(), $b->isExpanded(), $c->isExpanded()], 'Opening one closes the others of its name.');
    }

    public function testAnInertSubtreeTakesNeitherTheFocusNorThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<main id="main"><div inert id="inert"><button id="off">Off</button></div><button id="on">On</button></main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(['on'], array_map(static fn ($widget) => $widget->getId(), $tui->getFocusManager()->getCycle()));
        $off = $tui->getWidgetRect($view->getById('off'));
        $this->assertSame('main', $view->widgetAt($off->row, $off->col)?->getId());
        $this->assertFalse($view->click($off->row, $off->col));
    }

    public function testTheLinkOfATextUnderThePointerMatchesHover()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>a:hover { color: red }</style><p id="p">Read <a href="https://example.com">the docs</a> or <a href="/blog">the blog</a>.</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $text = $view->getById('p');
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $view->hover(0, 6);
        $tui->processRender();
        $this->assertSame(0, $text->getHoveredLink());
        $this->assertSame(['pointer', 'default'], [$view->cursorAt(0, 6), $view->cursorAt(0, 1)], 'A pointer over a link of a text.');
        $this->assertStringContainsString("\x1b[31m", $text->getText(), 'Its :hover styles.');
        $view->hover(0, 18);
        $tui->processRender();
        $this->assertSame(1, $text->getHoveredLink(), 'A link the terminal cannot follow, relative without a base URL, too.');
        $view->hover(0, 1);
        $tui->processRender();
        $this->assertNull($text->getHoveredLink());
        $this->assertStringNotContainsString("\x1b[31m", $text->getText());
    }

    public function testInteractivityInertIsLikeTheInertAttribute()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.busy { interactivity: inert } .back { interactivity: auto }</style><main id="main"><div class="busy"><button id="off">Off</button><button id="back" class="back">Back</button></div><button id="on">On</button></main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(['back', 'on'], array_map(static fn ($widget) => $widget->getId(), $tui->getFocusManager()->getCycle()), 'Inherited; auto takes it back.');
        $off = $tui->getWidgetRect($view->getById('off'));
        $this->assertSame('main', $view->widgetAt($off->row, $off->col)?->getId());
        $this->assertFalse($view->click($off->row, $off->col));
    }

    public function testADialogShowsWhenOpen()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<main><dialog id="d"><p>Hello</p></dialog></main>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertNull($tui->getWidgetRect($view->getById('d')), 'Closed: not drawn.');
        $view->getById('d')->setState('open');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(3, $tui->getWidgetRect($view->getById('d'))?->rows, 'Open: a box around its content.');
    }

    public function testGridItemsAreHitAtTheirPlace()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.g { display: grid; grid-template-columns: 1fr 1fr; gap: 1 2ch }</style>
            <div class="g" id="g"><p id="a">a</p><p id="b">b</p><p id="c">c</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $rect = static fn (string $id) => [$tui->getWidgetRect($view->getById($id))?->row, $tui->getWidgetRect($view->getById($id))?->col];
        $this->assertSame([0, 0], $rect('a'));
        $this->assertSame([0, 21], $rect('b'), '19 columns each, 2 between.');
        $this->assertSame([2, 0], $rect('c'), 'The next row, after the row gap.');
        $this->assertSame('b', $view->widgetAt(0, 22)?->getId());
        $a = $view->getById('a');
        $this->assertInstanceOf(RichTextWidget::class, $a);
        $a->setText('a text long enough to wrap onto two rows');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame([4, 0], $rect('c'), 'Moved down with the taller row: three rows, then the gap.');
    }

    public function testANegativeZIndexDoesNotTakeThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative"><p id="text">Over it</p><div id="under" style="position: absolute; top: 0; left: 0; width: 20ch; height: 2; background: blue; z-index: -1"></div><div id="over" style="position: absolute; top: 0; left: 30; width: 5ch; height: 1; background: red"></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame('text', $view->widgetAt(0, 2)?->getId(), 'Under the flow.');
        $this->assertSame('over', $view->widgetAt(0, 31)?->getId());
        $tui->stop();
    }

    public function testWhatABoxThatClipsCutsOfAnAbsoluteElementDoesNotTakeThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative; overflow: hidden; width: 10ch; height: 3"><div id="panel" style="position: absolute; top: 1; left: 2; width: 20ch; height: 5; background: blue"><p id="item">item</p></div></div><p id="after">after the card</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame('item', $view->widgetAt(1, 3)?->getId(), 'Inside the card.');
        $this->assertNotSame('panel', $view->widgetAt(1, 15)?->getId(), 'Cut at the side of the card.');
        $this->assertSame('after', $view->widgetAt(3, 3)?->getId(), 'Cut under the card.');
        $tui->stop();
    }

    public function testWhatABoxThatClipsCutsOfATransparentAbsoluteElementDoesNotTakeThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative; overflow: hidden; width: 10ch; height: 3"><div id="panel" style="position: absolute; top: 1; left: 2; width: 20ch; height: 5; opacity: 0">x</div></div><p id="after">after the card</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame($view->getById('panel'), $view->widgetAt(1, 3)?->getParent(), 'Inside the card: its text.');
        $this->assertSame('after', $view->widgetAt(3, 3)?->getId(), 'Cut under the card.');
        $tui->stop();
    }

    public function testARaisedRelativeElementTakesThePointerOverALowerAbsoluteOne()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative"><p id="raised" style="position: relative; width: 10ch; background: red; z-index: 2">Raised</p><div id="badge" style="position: absolute; top: 0; left: 0; width: 5ch; height: 1; background: blue; z-index: 1"></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame('raised', $view->widgetAt(0, 2)?->getId());
        $tui->stop();
    }

    public function testThePointerFollowsTheStackingContexts()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div id="context" style="position: relative; z-index: 1; width: 20ch"><p>Context</p><p id="inner" style="position: absolute; top: 0; left: 0; width: 10ch; background: blue; z-index: 100">Inner</p></div><p id="sibling" style="position: relative; top: -1; left: 4; width: 10ch; background: red; z-index: 2">Sibling</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame('sibling', $view->widgetAt(0, 5)?->getId(), 'A z-index of 2 is over one of 100 inside one of 1.');
        $this->assertSame('inner', $view->widgetAt(0, 2)?->getId());
        $tui->stop();
    }

    private function mount(): array
    {
        $terminal = new VirtualTerminal(50, 12);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $view];
    }
}
