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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class BoxShadowTest extends TestCase
{
    public function testTheSpreadGrowsTheShadowOnTheRightAndBelow()
    {
        // Moved a column to the right, a spread of one: two columns on the right, a row below from the first column
        $this->assertSame(['ab ##', 'cd ##', '#####'], $this->render('<p style="width: 5ch; margin: 0; box-shadow: 1ch 0 0 1ch #ff0000">ab cd</p>'));
    }

    public function testASpreadAloneIsARingAroundTheBox()
    {
        // Not moved nor blurred, like a focus ring: a line hugging the box from outside, over what is around it
        $this->assertSame(['▗▄▄▄▖', '▐ab ▌', '▐cd ▌', '▝▀▀▀▘'], $this->render('<p style="width: 3ch; margin: 1 1ch; box-shadow: 0 0 0 1ch #ff0000">ab cd</p>'), 'Thick past 2px.');
        $this->assertSame(['▁▁▁▁▁', '▕ab ▏', '▕cd ▏', '▔▔▔▔▔'], $this->render('<p style="width: 3ch; margin: 1 1ch; box-shadow: 0 0 0 1px #ff0000">ab cd</p>'), 'Thin up to 2px.');
        $this->assertSame(['▗▄▄▄▖', '▐ab ▌', '▐cd ▌', '▝▀▀▀▘'], $this->render('<p style="width: 3ch; margin: 1 1ch; box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #3b82f6">ab cd</p>'), 'The widest of several, like the offset and the ring of a page.');
        $this->assertSame(['┌───┐', '│ab │', '│cd │', '└───┘'], $this->render('<p style="width: 3ch; margin: 1 1ch; outline: 1px solid #00ff00; box-shadow: 0 0 0 1ch #ff0000">ab cd</p>'), 'An outline drawn instead.');
    }

    public function testARingDoesNotHideWhatIsAroundIt()
    {
        $this->assertSame(['▗Email▖', '▐ab   ▌', '▝▀▀▀▀▀▘'], $this->render('<p style="margin: 0 1ch">Email</p><p style="width: 5ch; margin: 0 1ch; box-shadow: 0 0 0 1ch #ff0000">ab</p>'), 'Over the blank cells only: the text above it stays.');
        $this->assertSame(['▁▁▁▁▁▁▁', '▕ab   ▏', ' a b c▔'], $this->render('<p style="width: 5ch; margin: 1 1ch 0; box-shadow: 0 0 0 1px #ff0000">ab</p><p style="margin: 0 1ch">a b c</p>'), 'After the text of a line under it only, not between its words, like the feedback under a field.');
    }

    public function testTheRingOfTailwind()
    {
        // As Tailwind writes it: the ring, its offset and the shadow composed through custom properties
        $css = '<style>* { --tw-ring-inset: ; --tw-ring-offset-width: 0px; --tw-ring-offset-color: #fff; --tw-ring-color: rgb(59 130 246 / 0.5); --tw-ring-offset-shadow: 0 0 #0000; --tw-ring-shadow: 0 0 #0000; --tw-shadow: 0 0 #0000 } .ring-2 { --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color); --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color); box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000) } .ring-4 { --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(4px + var(--tw-ring-offset-width)) var(--tw-ring-color); box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000) }</style>';
        $this->assertSame(['▁▁▁▁▁', '▕ab ▏', '▕cd ▏', '▔▔▔▔▔'], $this->render($css.'<p class="ring-2" style="width: 3ch; margin: 1 1ch">ab cd</p>'));
        $this->assertSame(['▗▄▄▄▖', '▐ab ▌', '▐cd ▌', '▝▀▀▀▘'], $this->render($css.'<p class="ring-4" style="width: 3ch; margin: 1 1ch">ab cd</p>'));
        // ring-inset, like the ring-1 ring-inset ring-gray-300 of the fields and buttons of Tailwind UI: at the edge all the same
        $this->assertSame(['▁▁▁▁▁', '▕ab ▏', '▕cd ▏', '▔▔▔▔▔'], $this->render($css.'<p class="ring-2" style="--tw-ring-inset: inset; width: 3ch; margin: 1 1ch">ab cd</p>'));
    }

    public function testAnInsetSpreadOfAFewPixelsIsARingTooAndAWideOneAFill()
    {
        $this->assertSame(['▁▁▁▁▁', '▕ab ▏', '▕cd ▏', '▔▔▔▔▔'], $this->render('<p style="width: 3ch; margin: 1 1ch; box-shadow: inset 0 0 0 1px #ff0000">ab cd</p>'));
        $this->assertSame([' ab#', ' cd#'], $this->render('<p style="width: 3ch; margin: 1 1ch; box-shadow: inset 0 0 0 9999px #ff0000">ab cd</p>'), 'Like the stripes of a table of Bootstrap.');
    }

    public function testABlurAddsASofterCellAroundTheShadow()
    {
        $this->assertSame(['ab', 'cd#▌', ' ##▌', ' ▀▀▘'], $this->render('<p style="width: 4ch; margin: 0; box-shadow: 1ch 1 1ch #ff0000">ab cd</p>'));
    }

    public function testTheContentKeepsItsRoomBesideASpreadOrBlurredShadow()
    {
        // The box narrower by the shadow: its text wraps in it, none of it cut
        $text = implode(' ', array_map(static fn (string $line) => trim($line, ' #▌▀▘│┌┐└┘─'), $this->render('<div style="box-shadow: 8px 16px 0 16px #ff0000; border: 1px solid; width: 20ch">0123456789 abcdefgh ABCDEFGHIJKLMNOP</div>', 30, 10)));
        $this->assertStringContainsString('ABCDEFGHIJKLMNOP', str_replace(' ', '', $text));
    }

    private function render(string $html, int $columns = 10, int $rows = 5): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, $rows);
        $buffer->write($terminal->consumeOutput());
        $lines = [];
        foreach ($buffer->getCells() as $row) {
            $line = '';
            foreach ($row as $cell) {
                $line .= ' ' === $cell['char'] && str_contains($cell['style'], '48;2;255;0;0') ? '#' : $cell['char'];
            }
            $lines[] = rtrim($line);
        }

        return array_values(array_filter($lines, static fn (string $line) => '' !== $line));
    }
}
