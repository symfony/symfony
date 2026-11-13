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

final class TextJustifyTest extends TestCase
{
    public function testTheModeFillsTheLines()
    {
        $text = 'aa bb cc dd ee ff';
        $this->assertSame(['aa  bb  cc', 'dd ee ff'], $this->render('<p style="text-align: justify">'.$text.'</p>'), 'auto: between the words.');
        $this->assertSame(['aa  bb  cc', 'dd ee ff'], $this->render('<p style="text-align: justify; text-justify: inter-word">'.$text.'</p>'));
        $this->assertSame(['a a  bb cc', 'dd ee ff'], $this->render('<p style="text-align: justify; text-justify: inter-character">'.$text.'</p>'), 'Between every character, spaces too.');
        $this->assertSame(['aa bb cc', 'dd ee ff'], $this->render('<p style="text-align: justify; text-justify: none">'.$text.'</p>'), 'Not justified: at the start.');
        $this->assertSame(['  aa bb cc', '  dd ee ff'], $this->render('<p dir="rtl" style="text-align: justify; text-justify: none">'.$text.'</p>'), 'At the start, on the right.');
        $this->assertSame(['aa  bb  cc', 'dd ee ff'], $this->render('<div style="text-justify: inter-character"><p style="text-align: justify; text-justify: initial">'.$text.'</p></div>'));
    }

    public function testInterCharacterIsInherited()
    {
        $this->assertSame(['a a  bb cc', 'dd ee ff'], $this->render('<div style="text-justify: distribute"><p style="text-align: justify">aa bb cc dd ee ff</p></div>'));
    }

    public function testWideCharactersWithoutSpacesAreSpreadByAuto()
    {
        $this->assertSame(['日 本語の文', '章'], $this->render('<p style="text-align: justify">日本語の文章</p>', 11));
        $this->assertSame(['日本語の文', '章'], $this->render('<p style="text-align: justify; text-justify: inter-word">日本語の文章</p>', 11), 'inter-word: no spaces to stretch.');
    }

    private function render(string $html, int $width = 10): array
    {
        $terminal = new VirtualTerminal($width, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build('<style>p { margin: 0 }</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($width, 8);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
