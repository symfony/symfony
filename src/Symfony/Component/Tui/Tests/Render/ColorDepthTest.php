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
use Symfony\Component\Tui\Render\ColorDepth;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;

final class ColorDepthTest extends TestCase
{
    public function testTrueColorsBecomeThePalette()
    {
        $depth = new ColorDepth(256);

        $this->assertSame("\e[1;38;5;196mred\e[0m", $depth->reduce("\e[1;38;2;255;0;0mred\e[0m"));
        $this->assertSame("\e[48;5;21m \e[0m", $depth->reduce("\e[48;2;0;0;250m \e[0m"));
        $this->assertSame("\e[38;5;244m", $depth->reduce("\e[38;2;128;128;128m"), 'Grays take the gray ramp.');
        $this->assertSame("\e[38;5;196;48;5;21m▀", $depth->reduce("\e[38;2;255;0;0;48;2;0;0;255m▀"), 'Both colors of a half block.');
        $this->assertSame("\e[4;58;5;46m", $depth->reduce("\e[4;58;2;0;255;0m"), 'The underline color too.');
        $this->assertSame("\e[38;5;100mkept", $depth->reduce("\e[38;5;100mkept"));
        $this->assertSame('plain', $depth->reduce('plain'));
    }

    public function testColorsBecomeTheBasicOnes()
    {
        $depth = new ColorDepth(16);

        $this->assertSame("\e[91m", $depth->reduce("\e[38;2;255;10;10m"));
        $this->assertSame("\e[44m", $depth->reduce("\e[48;2;0;0;200m"));
        $this->assertSame("\e[97m", $depth->reduce("\e[38;5;231m"), 'Palette colors too.');
        $this->assertSame("\e[38;5;9m", $depth->reduce("\e[38;5;9m"), 'Basic ones are kept.');
        $this->assertSame("\e[1;31m", $depth->reduce("\e[1;31m"));
        $this->assertSame("\e[31m", (new ColorDepth(8))->reduce("\e[38;2;255;0;0m"), 'Eight colors: no bright ones.');
    }

    public function testTheScreenGetsTheColorsOfTheTerminal()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(terminal: $terminal);
        $tui->setMediaContext(new MediaContext(colors: 256));
        $tui->add((new TextWidget('hi'))->setStyle(new Style(color: '#ff0000')));
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString("\e[38;5;196m", $terminal->getOutput());
        $this->assertStringNotContainsString('38;2;', $terminal->getOutput());

        $terminal->clearOutput();
        $tui->setMediaContext(new MediaContext());
        $tui->processRender();
        $this->assertStringContainsString("\e[38;2;255;0;0m", $terminal->getOutput(), 'Back to true colors, the screen is drawn again.');
        $tui->stop();
    }
}
