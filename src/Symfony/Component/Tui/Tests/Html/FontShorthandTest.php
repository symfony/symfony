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

final class FontShorthandTest extends TestCase
{
    public function testTheShorthandSetsTheFamily()
    {
        // A FIGlet font: rows of glyphs
        $this->assertGreaterThan(1, \count($this->render('<p style="font: bold 16px/1 small">Hi</p>')));
        $this->assertSame(['Hi'], $this->render('<p style="font: bold 16px small; font-family: none">Hi</p>'), 'A longhand after it wins.');
        $this->assertSame(['Hi'], $this->render('<p style="font: 700 menu">Hi</p>'), 'A system font: no size nor family.');
    }

    /**
     * @return list<string>
     */
    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(40, 8);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 8);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
