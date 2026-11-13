<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class CustomElementTest extends TestCase
{
    public function testACustomElementIsInlineLikeAnyElement()
    {
        $this->assertSame(['A new item'], $this->render('<p>A <x-badge>new</x-badge> item</p>'));
        $this->assertSame(['A [new] item'], $this->render('<style>x-badge::before { content: "[" } x-badge::after { content: "]" }</style><p>A <x-badge>new</x-badge> item</p>'), 'Styled like an inline element.');
    }

    public function testACustomElementHoldingBlocksOrDisplayedAsOneIsABlock()
    {
        $this->assertSame(['before', 'a', 'b', 'after'], $this->render('<p>before</p><x-list><div>a</div><div>b</div></x-list><p>after</p>'));
        $this->assertSame(['A', 'new', 'item'], $this->render('<style>x-badge { display: block }</style><p>A <x-badge>new</x-badge> item</p>'));
        $this->assertSame(['one line of text'], $this->render('<x-text>one <b>line</b> of text</x-text>'), 'Inline content alone: its line.');
    }

    /**
     * @return list<string>
     */
    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(30, 8);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>p, div { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 8);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
