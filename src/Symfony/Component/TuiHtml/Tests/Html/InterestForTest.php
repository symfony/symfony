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
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class InterestForTest extends TestCase
{
    private const HTML = '<style>#tip { position-area: bottom span-right; margin: 0 }</style><p><button id="save" interestfor="tip">Save</button></p>
        <p>Some text</p><input id="field" aria-label="Field">
        <div id="tip" popover="hint">Saves the file</div>
        <div id="menu" popover>A menu</div>';

    public function testPointingAtItShowsTheHint()
    {
        [$tui, $view] = $this->mount();
        $tip = $view->getById('tip');
        $rect = $tui->getWidgetRect($view->getById('save'));
        $view->hover($rect->row, $rect->col + 1);
        $this->assertTrue($tip->hasState('popover-open'));
        $tui->processRender();
        $tipRect = $tui->getWidgetRect($tip);
        $this->assertSame([$rect->row + 1, $rect->col], [$tipRect->row, $tipRect->col], 'Anchored to it: under it with position-area.');
        $view->hover(1, 30);
        $this->assertFalse($tip->hasState('popover-open'), 'Pointing elsewhere hides it.');
    }

    public function testFocusingItShowsTheHint()
    {
        [$tui, $view] = $this->mount();
        $tui->setFocus($view->getById('save'));
        $this->assertTrue($view->getById('tip')->hasState('popover-open'));
        $tui->setFocus($view->getById('field'));
        $this->assertFalse($view->getById('tip')->hasState('popover-open'));
    }

    public function testAHintLeavesAutoPopoversOpen()
    {
        [$tui, $view] = $this->mount();
        $view->showPopover($view->getById('menu'));
        $tui->setFocus($view->getById('save'));
        $this->assertTrue($view->getById('menu')->hasState('popover-open'), 'A hint does not close an auto popover.');
        $this->assertTrue($view->getById('tip')->hasState('popover-open'));
        $tui->handleInput("\x1b");
        $this->assertFalse($view->getById('tip')->hasState('popover-open'), 'Escape closes the hint first.');
        $this->assertTrue($view->getById('menu')->hasState('popover-open'));
    }

    private function mount(): array
    {
        $tui = new Tui(null, new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->build(self::HTML)->mount($tui);
        $tui->start();
        $tui->setFocus(null);
        $tui->processRender();

        return [$tui, $view];
    }
}
