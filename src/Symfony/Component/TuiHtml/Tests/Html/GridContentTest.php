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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

/**
 * How the tracks of a grid share its room, like the CSS Box Alignment of justify-content and align-content.
 */
final class GridContentTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideCenteredGrids(): iterable
    {
        // Its auto row stretched to its height, like align-content: normal, the item centered in it
        yield 'place-items: center' => ['place-items: center; height: 5', ['┌────────┐', '│        │', '│   hi   │', '│        │', '└────────┘']];
        // Its auto column as wide as its content, placed in the middle
        yield 'place-content: center' => ['place-content: center; height: 5', ['┌────────┐', '│        │', '│   hi   │', '│        │', '└────────┘']];
        yield 'justify-content: center' => ['justify-content: center', ['┌────────┐', '│   hi   │', '└────────┘']];
        yield 'normal: stretched' => ['height: 4', ['┌────────┐', '│hi      │', '│        │', '└────────┘']];
    }

    #[DataProvider('provideCenteredGrids')]
    public function testTheAutoTracksStretchOnlyByDefault(string $css, array $expected)
    {
        $this->assertSame($expected, $this->screen('<div style="display: grid; width: 10ch; border: 1px solid; '.$css.'">hi</div>'));
    }

    public function testAutoColumnsAreAsWideAsTheirContentWhenPlaced()
    {
        $this->assertSame(['┌──────────┐', '│   hiyo   │', '└──────────┘'], $this->screen('<div style="display: grid; grid-template-columns: auto auto; justify-content: center; width: 12ch; border: 1px solid"><span>hi</span><span>yo</span></div>'));
        $this->assertSame(['┌──────────┐', '│hi   yo   │', '└──────────┘'], $this->screen('<div style="display: grid; grid-template-columns: auto auto; width: 12ch; border: 1px solid"><span>hi</span><span>yo</span></div>'), 'Stretched by default.');
    }

    /**
     * @return list<string>
     */
    private function screen(string $html): array
    {
        $terminal = new VirtualTerminal(20, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(20, 8);
        $screen->write($terminal->getOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $screen->getScreen())), static fn (string $line): bool => '' !== $line));
    }
}
