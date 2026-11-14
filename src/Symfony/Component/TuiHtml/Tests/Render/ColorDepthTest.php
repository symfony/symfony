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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Render\ColorDepth;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\TextWidget;

final class ColorDepthTest extends TestCase
{
    public function testTrueColorsBecomeThePalette()
    {
        $depth = new ColorDepth(256);
        $this->assertSame("\x1b[1;38;5;196mred\x1b[0m", $depth->reduce("\x1b[1;38;2;255;0;0mred\x1b[0m"));
        $this->assertSame("\x1b[48;5;21m \x1b[0m", $depth->reduce("\x1b[48;2;0;0;250m \x1b[0m"));
        $this->assertSame("\x1b[38;5;244m", $depth->reduce("\x1b[38;2;128;128;128m"), 'Grays take the gray ramp.');
        $this->assertSame("\x1b[38;5;196;48;5;21m▀", $depth->reduce("\x1b[38;2;255;0;0;48;2;0;0;255m▀"), 'Both colors of a half block.');
        $this->assertSame("\x1b[4;58;5;46m", $depth->reduce("\x1b[4;58;2;0;255;0m"), 'The underline color too.');
        $this->assertSame("\x1b[38;5;100mkept", $depth->reduce("\x1b[38;5;100mkept"));
        $this->assertSame('plain', $depth->reduce('plain'));
    }

    public function testColorsBecomeTheBasicOnes()
    {
        $depth = new ColorDepth(16);
        $this->assertSame("\x1b[91m", $depth->reduce("\x1b[38;2;255;10;10m"));
        $this->assertSame("\x1b[44m", $depth->reduce("\x1b[48;2;0;0;200m"));
        $this->assertSame("\x1b[97m", $depth->reduce("\x1b[38;5;231m"), 'Palette colors too.');
        $this->assertSame("\x1b[38;5;9m", $depth->reduce("\x1b[38;5;9m"), 'Basic ones are kept.');
        $this->assertSame("\x1b[1;31m", $depth->reduce("\x1b[1;31m"));
        $this->assertSame("\x1b[31m", (new ColorDepth(8))->reduce("\x1b[38;2;255;0;0m"), 'Eight colors: no bright ones.');
    }

    public function testTheScreenGetsTheColorsOfTheTerminal()
    {
        $terminal = new VirtualTerminal(20, 3);
        $tui = new Tui(null, $terminal);
        $tui->setMediaContext(new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, \Symfony\Component\TuiHtml\Style\Density::Regular, false, false, false, null, false, 256));
        $tui->add(StyleBridge::set(new TextWidget('hi'), new Style(null, null, null, '#ff0000')));
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString("\x1b[38;5;196m", $terminal->getOutput());
        $this->assertStringNotContainsString('38;2;', $terminal->getOutput());
        $terminal->clearOutput();
        $tui->setMediaContext(new MediaContext());
        $tui->processRender();
        $this->assertStringContainsString("\x1b[38;2;255;0;0m", $terminal->getOutput(), 'Back to true colors, the screen is drawn again.');
        $tui->stop();
    }
}
