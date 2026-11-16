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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Render\Renderer;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;

final class RectsDrawnOverTest extends TestCase
{
    private Tui $tui;
    private HtmlView $view;

    public function testAnAbsoluteBoxOverAField()
    {
        $this->render('<input id="field"><div id="box" style="position: absolute; top: 0; left: 0; width: 6ch; background: red">box</div><div id="far" style="position: absolute; top: 5; left: 0">far</div>');

        $this->assertSame([$this->rect('box'), $this->rect('far')], $this->over('field'), 'Each one drawn over the flow, whether it covers it or not');
        $this->assertSame([$this->rect('far')], $this->over('box'), 'Not itself; one after it in the document, of the same z-index');
    }

    public function testWhatIsAroundOrUnderAWidgetIsNotOverIt()
    {
        $this->render('<div id="box" style="position: absolute; top: 0; left: 0"><input id="inside"><div id="badge" style="position: absolute; top: 0; left: 0">!</div></div>
            <div id="under" style="position: absolute; z-index: -1; top: 0; left: 0">under</div>
            <div id="low" style="position: absolute; z-index: 1; top: 0; left: 0"><input id="low-field"></div>
            <div id="high" style="position: absolute; z-index: 2; top: 0; left: 0">high</div>');

        $this->assertSame([$this->rect('badge'), $this->rect('low'), $this->rect('high')], $this->over('inside'), 'Not the box it is in, nor what is under the flow; what is inside its box too, or stacked over it');
        $this->assertSame([$this->rect('high')], $this->over('low-field'), 'Only what is stacked over its box');
    }

    public function testAModalDialogIsOverTheForm()
    {
        $this->render('<input id="field"><dialog id="dialog"><input id="name"></dialog>');
        $this->view->showModal($this->view->getById('dialog'));
        $this->tui->requestRender();
        $this->tui->processRender();

        $this->assertSame([$this->rect('dialog')], $this->over('field'));
        $this->assertSame([], $this->over('name'));
    }

    public function testTheOpenListOfADropdown()
    {
        $this->render('<div><select id="color"><option>Red</option><option>Green</option></select></div><div><input id="below"></div>');
        $this->assertSame([], $this->over('below'), 'Closed');
        $select = $this->view->getById('color');
        $this->assertInstanceOf(DropdownWidget::class, $select);
        $select->setOpen(true);
        $this->tui->requestRender();
        $this->tui->processRender();

        $list = $this->renderer()->getWidgetRect($select->getList());
        $this->assertNotNull($list);
        $this->assertSame([$list], $this->over('below'));
        $this->assertTrue($list->contains($this->rect('below')?->row ?? -1, $this->rect('below')?->col ?? -1), 'It covers the field');
    }

    public function testAStickyHeaderOverWhatIsScrolledUnderIt()
    {
        $lines = implode('', array_map(static fn (int $i): string => '<p style="margin: 0">Line '.$i.'</p>', range(1, 8)));
        $this->render('<div id="box" style="overflow: auto; max-height: 4"><p id="head" style="position: sticky; top: 0; margin: 0">Header</p><div><input id="field"></div>'.$lines.'</div>');
        $this->assertSame([], $this->over('field'), 'Not stuck, in the flow');

        $this->view->getById('box')->scrollTo(1);
        $this->tui->requestRender();
        $this->tui->processRender();
        $head = $this->rect('head');
        $this->assertNotNull($head);
        $this->assertSame([$head], $this->over('field'), 'Stuck, drawn over what is scrolled under it');
        $this->assertSame($head->row, $this->rect('field')?->row, 'It covers the field');
    }

    private function render(string $html): void
    {
        $this->tui = new Tui(null, new VirtualTerminal(40, 12));
        $this->view = (new HtmlBuilder())->build($html)->mount($this->tui);
        $this->tui->start();
        $this->tui->processRender();
    }

    private function renderer(): Renderer
    {
        $renderer = $this->tui->getRenderer();
        $this->assertInstanceOf(Renderer::class, $renderer);

        return $renderer;
    }

    private function rect(string $id): ?WidgetRect
    {
        return $this->renderer()->getWidgetRect($this->view->getById($id));
    }

    /**
     * @return list<WidgetRect>
     */
    private function over(string $id): array
    {
        return $this->renderer()->getRectsDrawnOver($this->view->getById($id));
    }
}
