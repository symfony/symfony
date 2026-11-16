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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Event\LinkEvent;
use Symfony\Component\TuiHtml\Event\PressEvent;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
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

    public function testPressingAndDraggingASliderMovesItsThumbWithThePointer()
    {
        $terminal = new VirtualTerminal(50, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p>Intro</p><input id="level" type="range" min="0" max="10" value="5"><input id="off" type="range" min="0" max="10" value="5" disabled>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $level = $view->getById('level');
        $changes = [];
        $level->onChange(static function ($event) use (&$changes) { $changes[] = $event->getValue(); });
        $rect = $tui->getWidgetRect($level);
        // The track is the columns before the label of the value, " 5"
        $track = $rect->columns - 2;

        $this->assertSame($level, $view->press($rect->row, $rect->col));
        $this->assertSame(0.0, $level->getValue(), 'The press moves the thumb there.');
        $this->assertSame($level, $tui->getFocus(), 'And takes the focus, like in a browser.');
        $this->assertTrue($view->drag($rect->row + 2, $rect->col + $track - 1), 'Whatever the row.');
        $this->assertSame(10.0, $level->getValue());
        $view->drag($rect->row, $rect->col + 200);
        $this->assertSame(10.0, $level->getValue(), 'Past the track, the maximum.');
        $view->drag($rect->row, $rect->col + intdiv($track - 1, 2));
        $this->assertEqualsWithDelta(5.0, $level->getValue(), 1.0);
        $this->assertSame(['0', '10', (string) $level->getValue()], $changes, 'A change each time the value does.');
        $view->release();
        $this->assertFalse($view->drag($rect->row, $rect->col), 'Released, it does not follow anymore.');

        $off = $view->getById('off');
        $rect = $tui->getWidgetRect($off);
        $view->press($rect->row, $rect->col);
        $this->assertFalse($view->drag($rect->row, $rect->col + 1));
        $this->assertSame(5.0, $off->getValue(), 'A disabled slider does not move.');
        $this->assertSame($level, $tui->getFocus());
        $view->press(0, 1);
        $this->assertFalse($view->drag(0, 3), 'Only a slider follows the pointer.');
    }

    public function testASliderWithPaddingAndABorderPutsTheThumbUnderThePointer()
    {
        $terminal = new VirtualTerminal(30, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<input id="level" type="range" min="0" max="10" value="5" style="padding: 0 3ch 0 4ch; border: 1px solid">')->mount($tui);
        $tui->start();
        $tui->processRender();
        $level = $view->getById('level');
        $rect = $tui->getWidgetRect($level);
        // The border and the padding before the track, its label " 5" and the padding and the border after it
        $first = $rect->col + 1 + 4;
        $last = $rect->col + $rect->columns - 1 - 3 - 2 - 1;

        $view->press($rect->row + 1, $first);
        $this->assertSame(0.0, $level->getValue(), 'The first cell of the track.');
        $view->drag($rect->row + 1, $last);
        $this->assertSame(10.0, $level->getValue(), 'Its last cell.');
        $view->drag($rect->row + 1, $first - 2);
        $this->assertSame(0.0, $level->getValue(), 'Before the track, while dragging: the minimum.');
    }

    public function testAPressOnTheLabelOfASliderFocusesItWithoutMovingIt()
    {
        $terminal = new VirtualTerminal(30, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<input id="level" type="range" min="0" max="100" value="5">')->mount($tui);
        $tui->start();
        $tui->processRender();
        $level = $view->getById('level');
        $rect = $tui->getWidgetRect($level);

        $this->assertSame($level, $view->press($rect->row, $rect->col + $rect->columns - 1));
        $this->assertSame(5.0, $level->getValue(), 'The label, " 5", is not the track.');
        $this->assertSame($level, $tui->getFocus());
        $this->assertTrue($view->drag($rect->row, $rect->col + $rect->columns - 1), 'Dragged from there, it follows.');
        $this->assertSame(100.0, $level->getValue());
    }

    #[RequiresPhpExtension('gd')]
    public function testAClickOnAnAreaOfAnImageMapFollowsIt()
    {
        $image = imagecreatetruecolor(64, 32);
        imagefilledrectangle($image, 0, 0, 63, 31, imagecolorallocate($image, 0, 200, 0));
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        $terminal = new VirtualTerminal(50, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p>Intro</p><img id="map" src="'.$png.'" alt="Map" width="64" height="32" usemap="#nav"><map name="nav"><area shape="rect" coords="0,0,32,32" href="https://example.com/a" alt="A"><area shape="rect" coords="32,0,64,32" href="https://example.com/b" alt="B"></map>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $map = $view->getById('map');
        $followed = [];
        $map->on(LinkEvent::class, static function (LinkEvent $event) use (&$followed) {
            $followed[] = $event->getHref();
        });
        $rect = $tui->getWidgetRect($map);
        // Drawn on 8 columns and 2 rows from the left of its box, the areas 4 columns each

        $this->assertSame('pointer', $view->cursorAt($rect->row, $rect->col + 1), 'Over an area, a pointer, like over a link.');
        $this->assertSame('https://example.com/b', $view->hrefAt($rect->row, $rect->col + 6), 'The target of the area there.');
        $this->assertFalse($view->click($rect->row, $rect->col + 1), 'Left to the terminal, which follows its links itself.');
        $this->assertSame([], $followed);
        $this->assertTrue($view->click($rect->row, $rect->col + 6, followLinks: true));
        $this->assertSame(['https://example.com/b'], $followed);
        $this->assertSame($map, $tui->getFocus(), 'It takes the focus, like a link in a browser.');
        $this->assertSame(1, $map->getCurrentArea(), 'On the area clicked.');
        $view->click($rect->row + 1, $rect->col, followLinks: true);
        $this->assertSame(['https://example.com/b', 'https://example.com/a'], $followed);
        $this->assertSame(0, $map->getCurrentArea());
        $this->assertSame('default', $view->cursorAt(0, 1));
    }

    public function testHrefAt()
    {
        $terminal = new VirtualTerminal(50, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<base href="https://example.com/"><p>Read <a href="docs">the docs</a> or <a href="#top">top</a></p>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $this->assertNull($view->hrefAt(0, 1), 'Not on a link.');
        $this->assertSame('https://example.com/docs', $view->hrefAt(0, 6), 'Against the base, like a browser shows it.');
        $this->assertSame('#top', $view->hrefAt(0, 18), 'A fragment.');
        $this->assertNull($view->hrefAt(3, 1));
    }

    public function testFollowLinkFollowsTheLinkWhereverItIsDrawn()
    {
        $terminal = new VirtualTerminal(50, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div id="top"></div><p id="text">Read <a href="https://example.com/docs">the docs</a> or <a href="#end">the end</a></p><p id="end">End</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $followed = [];
        $tui->addListener(static function (LinkEvent $event) use (&$followed): void {
            $followed[] = $event->getHref();
        });
        [$text, $index, $href] = $view->getLinkAt(0, 6);
        $this->assertSame([$view->getById('text'), 0, 'https://example.com/docs'], [$text, $index, $href]);

        // Something else is drawn there by then
        $view->getById('top')->add((new HtmlBuilder())->setDefaultStyles(false)->build('<button>Over</button>')->getRoot());
        $tui->requestRender();
        $tui->processRender();
        $this->assertNotSame($text, $view->widgetAt(0, 6));
        $this->assertTrue($view->followLink($text, $index));
        $this->assertSame(['https://example.com/docs'], $followed, 'The link, not what is at its cell now.');
        $this->assertSame($text, $tui->getFocus());

        [$text, $index] = $view->getLinkAt(1, 18) ?? [null, null];
        $this->assertNotNull($text, 'The link to a fragment.');
        $this->assertTrue($view->followLink($text, $index));
        $this->assertTrue($view->getById('end')->hasState('target'), 'A fragment of the page: it goes there.');
        $this->assertSame(['https://example.com/docs'], $followed);

        $text->setState('disabled');
        $this->assertNull($view->getLinkAt(1, 6), 'A disabled one is not followed.');
        $this->assertSame('https://example.com/docs', $view->hrefAt(1, 6), 'Its target is still the one under the pointer.');
        $this->assertSame('pointer', $view->cursorAt(1, 6), 'And the pointer over it, as over its target.');
        $this->assertFalse($view->followLink($text, 0));
    }

    public function testFollowLinkFollowsNothingRemovedAndClosesThePopoversLikeAClick()
    {
        $terminal = new VirtualTerminal(50, 8);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p id="text">Read <a href="https://example.com/docs">the docs</a></p><div id="pop" popover>Menu</div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $followed = [];
        $tui->addListener(static function (LinkEvent $event) use (&$followed): void {
            $followed[] = $event->getHref();
        });
        [$text, $index] = $view->getLinkAt(0, 6);
        $pop = $view->getById('pop');
        $view->showPopover($pop);
        $this->assertTrue($pop->hasState('popover-open'));

        $this->assertTrue($view->followLink($text, $index));
        $this->assertFalse($pop->hasState('popover-open'), 'Closed, like by a click on the link outside it.');

        $text->getParent()->remove($text);
        $tui->requestRender();
        $tui->processRender();
        $before = $tui->getFocus();
        $this->assertFalse($view->followLink($text, $index), 'Gone from the view.');
        $this->assertSame(['https://example.com/docs'], $followed);
        $this->assertSame($before, $tui->getFocus(), 'The focus does not go to it.');
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
        $this->assertSame([$view], HtmlView::getMountedViews($tui));
        $page = (new HtmlBuilder())->setDefaultStyles(false)->build('<p>Page</p>')->mount($tui);
        $this->assertSame([$page, $view], HtmlView::getMountedViews($tui), 'The page of the whole screen first.');
        $view->unmount();
        $this->assertSame([$page], HtmlView::getMountedViews($tui));
        $this->assertSame([], HtmlView::getMountedViews(new Tui(null, $terminal)));
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

    public function testTheControlsInAnOpenDetailsTakeThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p>top</p><details id="outer" open style="border: 1px solid; padding: 0 2ch"><summary>Sum</summary><p id="p">line <a href="#end">jump</a></p><details id="inner" open><summary>In</summary><button id="b">Press</button></details><input id="i"></details><p id="end">end</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $outer = $view->getById('outer');
        $inner = $view->getById('inner');
        $this->assertInstanceOf(CollapsibleWidget::class, $outer);
        $this->assertInstanceOf(CollapsibleWidget::class, $inner);
        $rect = static fn (string $id): ?array => null === ($rect = $tui->getWidgetRect($view->getById($id))) ? null : [$rect->row, $rect->col, $rect->columns, $rect->rows];
        // Inside the border and the padding of the outer one, under the summaries
        $this->assertSame([3, 3, 34, 1], $rect('p'));
        $this->assertSame([4, 3, 34, 2], $rect('inner'));
        $this->assertSame([5, 3, 34, 1], $rect('b'));
        $this->assertSame([6, 3, 34, 1], $rect('i'));
        $this->assertSame('b', $view->widgetAt(5, 4)?->getId());
        $this->assertSame('i', $view->widgetAt(6, 4)?->getId());
        $this->assertSame('inner', $view->widgetAt(4, 4)?->getId(), 'The summary of the inner one is its own.');
        $this->assertSame('outer', $view->widgetAt(2, 4)?->getId());
        $pressed = 0;
        $view->getById('b')->on(PressEvent::class, static function () use (&$pressed) {
            ++$pressed;
        });
        $this->assertTrue($view->click(5, 4));
        $this->assertSame(1, $pressed, 'The button is pressed.');
        $this->assertSame([true, true], [$outer->isExpanded(), $inner->isExpanded()], 'Not the details around it toggled.');
        $this->assertTrue($view->click(6, 4));
        $this->assertSame($view->getById('i'), $tui->getFocus());
        $this->assertTrue($outer->isExpanded());
        $tui->processRender();
        $this->assertTrue($view->click(3, 8), 'The link is followed.');
        $this->assertTrue($view->getById('end')->hasState('target'));
        $this->assertTrue($outer->isExpanded());
        // The summary still toggles
        $this->assertTrue($view->click(4, 4));
        $this->assertFalse($inner->isExpanded());
        $this->assertTrue($outer->isExpanded());
        $tui->processRender();
        $this->assertSame([5, 3, 34, 1], $rect('i'));
        $this->assertSame('i', $view->widgetAt(5, 4)?->getId(), 'Closed, its content takes no pointer: what follows moved up.');
    }

    public function testAClickOnTheTextOfAnOpenDetailsTogglesNothing()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details id="d" open><summary>Sum</summary><p>line <a href="#x">jump</a></p></details><p id="x">x</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $this->assertFalse($view->click(1, 1), 'Its content is not its summary, like browsers.');
        $this->assertTrue($details->isExpanded());
        $this->assertTrue($view->click(0, 1), 'Its summary toggles it.');
        $this->assertFalse($details->isExpanded());
    }

    public function testAClosedDetailsForgetsWhereItsContentWas()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details id="d" open><summary>Sum</summary><input id="i"></details>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $input = $view->getById('i');
        $this->assertNotNull($tui->getWidgetRect($input));
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $details->collapse();
        $tui->requestRender();
        $tui->processRender();
        $this->assertNull($tui->getWidgetRect($input), 'Not drawn, it has no rect to scroll to nor to anchor at.');
    }

    public function testTheContentAnOpenDetailsCutsTakesNoPointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details open style="height: 3em; overflow: hidden"><summary>S</summary><p>1</p><p>2</p><p>3</p><button id="b">Press</button></details><p>after</p><button id="c">Other</button>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertNull($tui->getWidgetRect($view->getById('b')), 'Cut by the height of the details: not drawn.');
        $this->assertSame('c', $view->widgetAt($tui->getWidgetRect($view->getById('c'))->row ?? -1, 1)?->getId(), 'What is drawn there is what follows.');
    }

    public function testAClickInADetailsInALabelActivatesNothing()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<label for="c"><details open><summary>S</summary><p>text</p></details></label><input type="checkbox" id="c" aria-label="c">')->mount($tui);
        $tui->start();
        $tui->processRender();
        $box = $view->getById('c');
        $this->assertInstanceOf(CheckboxWidget::class, $box);
        $this->assertFalse($view->click(1, 1), 'A <details> is interactive content: what is clicked in it is not the label.');
        $this->assertFalse($box->isChecked());
        $this->assertTrue($view->click(0, 1), 'Its summary is still that of the details.');
        $this->assertFalse($box->isChecked());
    }

    public function testTheContentOfAnOpenDetailsInAnotherColumnTakesThePointer()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 14));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="columns: 2; column-gap: 2ch"><details open><summary>S</summary>'.implode('', array_map(static fn ($i) => "<p>line $i</p>", range(1, 6))).'<button id="b">Press</button></details></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $rect = $tui->getWidgetRect($view->getById('b'));
        $this->assertNotNull($rect);
        $this->assertSame('b', $view->widgetAt($rect->row, $rect->col + 1)?->getId());
    }

    public function testOnlyTheSummaryOfAnOpenDetailsTogglesIt()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details id="d" open style="border: 1px solid; padding-bottom: 1"><summary>S</summary><p>a</p><p>b</p></details>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $box = $tui->getWidgetRect($details);
        $this->assertNotNull($box);
        $bottom = $box->row + $box->rows - 1;
        $this->assertFalse($view->click($bottom, 1), 'Its bottom border is not its summary.');
        $this->assertFalse($view->click($bottom - 1, 1), 'Nor its bottom padding.');
        $this->assertTrue($details->isExpanded());
        $this->assertTrue($view->click($box->row + 1, 2), 'Its summary toggles it.');
        $this->assertFalse($details->isExpanded());
    }

    public function testTheBorderOfAnOpenDetailsWhoseContentIsAllCutTogglesNothing()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details id="d" open style="border: 1px solid; height: 3em; overflow: hidden"><summary>S</summary><p>a</p></details>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $box = $tui->getWidgetRect($details);
        $this->assertNotNull($box);
        $this->assertFalse($view->click($box->row + $box->rows - 1, 1), 'Its bottom border is not its summary.');
        $this->assertTrue($details->isExpanded());
    }

    public function testTheContentOfAnOpenDetailsIsUnderTheLinesOfItsSummary()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<details id="d" open><summary>Sum</summary><button id="b">Press</button></details><details id="h" open><summary style="display: none">Hidden</summary><button id="c">Other</button></details>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame(1, $tui->getWidgetRect($view->getById('b'))?->row);
        $this->assertSame(2, $tui->getWidgetRect($view->getById('c'))?->row, 'No line for a summary not drawn.');
        $this->assertSame('c', $view->widgetAt(2, 1)?->getId());
        $details = $view->getById('d');
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        // A description too long for the line of the summary is a line of its own
        $details->setDescription(str_repeat('a long description ', 3));
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(2, $tui->getWidgetRect($view->getById('b'))?->row);
        $this->assertSame('b', $view->widgetAt(2, 1)?->getId());
        $this->assertSame(3, $tui->getWidgetRect($view->getById('c'))?->row);
        $this->assertTrue($view->click(2, 1));
        $this->assertTrue($details->isExpanded());
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

    public function testAClickFollowsALinkOfATextWhenNothingElseDoes()
    {
        $tui = new Tui(null, new VirtualTerminal(40, 6));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<p id="p">Read <a href="https://example.com">the docs</a> or <a href="/blog">the blog</a>.</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $text = $view->getById('p');
        $followed = [];
        $text->on(LinkEvent::class, static function (LinkEvent $event) use (&$followed): void {
            $followed[] = $event->getHref();
        });
        $this->assertFalse($view->click(0, 18), 'The terminal follows the link clicked.');
        $this->assertSame([], $followed);
        $this->assertTrue($view->click(0, 18, followLinks: true), 'In a window, nothing else follows it.');
        $this->assertSame(['/blog'], $followed);
        $this->assertSame([$text, '/blog'], [$tui->getFocus(), $text->getCurrentHref()], 'It takes the focus, on that link.');
        $this->assertFalse($view->click(0, 1, followLinks: true), 'Not on a link.');
        $this->assertSame(['/blog'], $followed);
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

    public function testWhatMovesUpInABoxThatClipsTakesThePointerWhereItIsShownNow()
    {
        // The last line cut by the box, then shown in it once the paragraph before is a line shorter
        $html = static fn (string $first): string => '<div style="overflow: hidden; height: 4em"><p id="a">'.$first.'</p><div><p id="t"><a href="#x">x</a><br><a href="#y">y</a><br><a href="#z">z</a></p></div></div><p id="after">after</p>';
        $mount = static function (string $html): array {
            $tui = new Tui(null, new VirtualTerminal(30, 10));
            $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
            $tui->start();
            $tui->processRender();

            return [$tui, $view];
        };
        [$tui, $view] = $mount($html('A<br>A2'));
        $this->assertSame('after', $view->widgetAt(4, 0)?->getId());
        $view->getById('a')->setText('A');
        $tui->requestRender();
        $tui->processRender();
        [$fresh, $built] = $mount($html('A'));

        $this->assertSame('t', $view->widgetAt(3, 0)?->getId(), 'Its last line, shown now.');
        $this->assertEquals($fresh->getWidgetRect($built->getById('t')), $tui->getWidgetRect($view->getById('t')));
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
