<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ComboboxWidget;

final class ComboboxWidgetTest extends TestCase
{
    public function testSuggestionsFollowTheValue()
    {
        [$tui, $view, $combobox] = $this->mount();
        $this->assertFalse($combobox->isOpen(), 'Nothing typed: closed.');

        $tui->handleInput('l');
        $this->assertTrue($combobox->isOpen());
        $this->assertTrue($combobox->getInput()->hasState('open'), 'The input matches :open.');
        $this->assertSame(['Lyon', 'Lille', 'Marseille'], $combobox->getMatches(), 'Those containing it, ignoring the case.');
        $this->assertSame($combobox->getInput(), $tui->getFocus(), 'Typing goes on in the input.');

        $tui->handleInput('y');
        $this->assertSame(['Lyon'], $combobox->getMatches());
        $tui->handleInput("\e");
        $this->assertFalse($combobox->isOpen(), 'Escape hides them.');
        $this->assertFalse($combobox->getInput()->hasState('open'));
        $this->assertSame('ly', $combobox->getInput()->getValue());

        $tui->handleInput("\e[B");
        $this->assertTrue($combobox->isOpen(), 'Down shows them again.');
        $tui->handleInput("\r");
        $this->assertSame('Lyon', $combobox->getInput()->getValue());
        $this->assertFalse($combobox->isOpen());
        $this->assertSame('Lyon', $view->getById('city')->getValue());
    }

    public function testANumberFieldStepsAndAltDownOpensItsList()
    {
        [$tui, $view, $combobox] = $this->mount('<input id="size" type="number" aria-label="Size" list="sizes" step="2" value="10"><datalist id="sizes"><option>12</option><option>16</option><option>24</option></datalist><button id="after">After</button>');

        $tui->handleInput("\e[A");
        $this->assertSame('12', $combobox->getInput()->getValue(), 'Up steps.');
        $this->assertFalse($combobox->isOpen(), 'Stepping does not show the suggestions.');
        $tui->handleInput("\e[B");
        $this->assertSame('10', $combobox->getInput()->getValue(), 'Down steps too.');
        $this->assertFalse($combobox->isOpen());

        $tui->handleInput("\e[1;3B");
        $this->assertTrue($combobox->isOpen(), 'Alt+Down shows them.');
        $tui->handleInput("\e[B");
        $this->assertSame('10', $combobox->getInput()->getValue(), 'Open, Down moves in the list.');
        $tui->handleInput("\r");
        $this->assertSame('16', $combobox->getInput()->getValue());

        $tui->handleInput("\x7f");
        $tui->handleInput("\x7f");
        $tui->handleInput('1');
        $this->assertTrue($combobox->isOpen(), 'Typing shows those containing it.');
        $this->assertSame(['12', '16'], $combobox->getMatches());
    }

    public function testTheFocusLeavingHidesThem()
    {
        [$tui, $view, $combobox] = $this->mount();
        $tui->handleInput('l');
        $tui->processRender();

        $tui->setFocus($view->getById('after'));
        $tui->processRender();
        $this->assertFalse($combobox->isOpen());
    }

    /**
     * @return array{Tui, HtmlView, ComboboxWidget}
     */
    private function mount(string $html = '<input id="city" aria-label="City" list="cities"><datalist id="cities"><option>Paris</option><option>Lyon</option><option>Lille</option><option value="Marseille"></datalist><button id="after">After</button>'): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(30, 12));
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        // The first Tab, like browsers: nothing has the focus before it
        $tui->handleInput("\e[17~");
        $tui->processRender();
        // In the line of the text, beside the button: the combobox around the input
        $combobox = $view->querySelector('input')?->getParent();
        $this->assertInstanceOf(ComboboxWidget::class, $combobox);
        $this->assertSame($combobox->getInput(), $view->querySelector('input'), 'The element is the input.');

        return [$tui, $view, $combobox];
    }
}
