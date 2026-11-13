<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class WrapTest extends TestCase
{
    public function testChildrenThatDoNotFitGoToTheNextLine()
    {
        [$screen, $positions] = $this->render(12, ['aaaa', 'bbbb', 'cccc', 'dd'], gap: 1, rowGap: 1);

        $this->assertSame(['aaaa bbbb', '', 'cccc dd'], $screen);
        $this->assertSame([[0, 0], [0, 5], [2, 0], [2, 5]], $positions);
    }

    public function testEverythingOnOneLineIsARow()
    {
        [$screen] = $this->render(20, ['aaaa', 'bbbb'], gap: 1, rowGap: 3);

        $this->assertSame(['aaaa bbbb'], $screen);
    }

    public function testAChildWiderThanTheRowHasALineOfItsOwn()
    {
        [$screen, $positions] = $this->render(6, ['a', 'bbbbbbbbbb', 'c'], gap: 1, rowGap: 0);

        $this->assertSame(['a', 'bbbbbb', 'bbbb', 'c'], $screen);
        $this->assertSame([[0, 0], [1, 0], [3, 0]], $positions);
    }

    public function testNoWrapByDefault()
    {
        [$screen] = $this->render(12, ['aaaa', 'bbbb', 'cccc'], gap: 1, rowGap: null);

        $this->assertSame(['aaa bbb cccc', 'a   b'], $screen, 'The children shrink instead.');
    }

    /**
     * @param list<string> $texts
     *
     * @return array{list<string>, list<array{int, int}>}
     */
    private function render(int $columns, array $texts, int $gap, ?int $rowGap): array
    {
        $terminal = new VirtualTerminal($columns, 8);
        $tui = new Tui(terminal: $terminal);
        $style = new Style(direction: Direction::Horizontal, gap: $gap);
        $row = (new ContainerWidget())->setStyle(null !== $rowGap ? $style->withWrap(true, $rowGap) : $style);
        $cells = [];
        foreach ($texts as $text) {
            $row->add($cells[] = (new TextWidget($text))->setStyle(new Style(flex: 0)));
        }
        $tui->add($row);
        $tui->start();
        $tui->processRender();

        $screen = new ScreenBuffer($columns, 8);
        $screen->write($terminal->getOutput());
        $lines = array_map('rtrim', explode("\n", $screen->getScreen()));
        while ($lines && '' === end($lines)) {
            array_pop($lines);
        }

        return [$lines, array_map(static fn (TextWidget $cell) => [$tui->getWidgetRect($cell)->row, $tui->getWidgetRect($cell)->col], $cells)];
    }
}
