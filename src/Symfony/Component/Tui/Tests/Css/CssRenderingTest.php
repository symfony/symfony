<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class CssRenderingTest extends TestCase
{
    public function testStylesARenderedTree()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleProvider(CssStyleSheet::fromString('.card { border: 1px solid; border-radius: 1px; padding: 0 1ch } .row { display: flex }'));

        $card = (new ContainerWidget())->addStyleClass('card');
        $card->add(new TextWidget('Title'));
        $row = (new ContainerWidget())->addStyleClass('row');
        $row->add(new TextWidget('A'));
        $row->add(new TextWidget('B'));
        $tui->add($card);
        $tui->add($row);

        $lines = $this->render($tui, $terminal, 40, 10);

        $this->assertSame('╭──────────────────────────────────────╮', $lines[0]);
        $this->assertSame('│ Title                                │', $lines[1]);
        $this->assertSame('AB', $lines[3], 'display: flex lays out a row of items sized by their content.');
    }

    public function testColorScheme()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleProvider($css = CssStyleSheet::fromString('p { color: light-dark(red, cyan) }'));
        $tui->add($text = new TextWidget('Hi'));
        $css->describe($text, 'p');

        $tui->setMediaContext(new MediaContext(colorScheme: ColorScheme::Dark));
        $this->assertStringContainsString("\e[36mHi", $this->renderStyled($tui, $terminal, 40, 5));

        $tui->setMediaContext($tui->getMediaContext()->withColorScheme(ColorScheme::Light));
        $this->assertStringContainsString("\e[31mHi", $this->renderStyled($tui, $terminal, 40, 5));
    }

    public function testResponsiveLayout()
    {
        $terminal = new VirtualTerminal(140, 10);
        $tui = new Tui(terminal: $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build(<<<'HTML'
            <style>
                .panes { display: flex }
                @media (max-width: 80) { .panes { flex-direction: column } }
            </style>
            <div class="panes"><p>Left</p><p>Right</p></div>
            HTML);
        $view->mount($tui);

        $wide = $this->render($tui, $terminal, 140, 10);
        $this->assertStringContainsString('Left', $wide[0]);
        $this->assertStringContainsString('Right', $wide[0]);

        $terminal->simulateResize(60, 10);
        $narrow = $this->render($tui, $terminal, 60, 10);
        $this->assertSame('Left', $narrow[0]);
        $this->assertSame('Right', $narrow[1]);
    }

    public function testForcedColorsKeepTheFocusCue()
    {
        $terminal = new VirtualTerminal(40, 5);
        $tui = new Tui(terminal: $terminal);
        $view = (new HtmlBuilder())->build('<style>p { color: red } select::selected { color: green }</style><p>Hi</p><select autofocus aria-label="Pick"><option>A</option><option>B</option></select>');
        $view->mount($tui);
        $tui->setMediaContext(MediaContext::fromEnvironment(['NO_COLOR' => '1']));

        $screen = $this->renderStyled($tui, $terminal, 40, 5);

        $this->assertStringNotContainsString("\e[31m", $screen, 'Colors are dropped.');
        $this->assertStringNotContainsString("\e[32m", $screen);
        $this->assertMatchesRegularExpression('/\e\[[\d;]*7[\d;]*m\[ A ▾ \]/', $screen, 'The focused select is still in reverse video.');
    }

    public function testFocusWithinRestylesOnFocusChange()
    {
        $terminal = new VirtualTerminal(30, 6);
        $tui = new Tui(terminal: $terminal);
        $tui->addStyleProvider(CssStyleSheet::fromString('.box { border: 1px solid } .box:focus-within { border-style: double }'));
        $box = (new ContainerWidget())->addStyleClass('box');
        $box->add($input = new InputWidget());
        $tui->add($box);
        $tui->add($other = new InputWidget());

        $tui->setFocus($input);
        $this->assertStringStartsWith('╔', $this->render($tui, $terminal, 30, 6)[0]);

        $tui->setFocus($other);
        $this->assertStringStartsWith('┌', $this->render($tui, $terminal, 30, 6)[0]);
    }

    /**
     * @return list<string>
     */
    private function render(Tui $tui, VirtualTerminal $terminal, int $columns, int $rows): array
    {
        if (!$tui->isRunning()) {
            $tui->start();
        }
        $terminal->clearOutput();
        $tui->requestRender(true);
        $tui->processRender();
        $screen = new ScreenBuffer($columns, $rows);
        $screen->write($terminal->getOutput());

        return array_map('rtrim', explode("\n", $screen->getScreen()));
    }

    private function renderStyled(Tui $tui, VirtualTerminal $terminal, int $columns, int $rows): string
    {
        if (!$tui->isRunning()) {
            $tui->start();
        }
        $terminal->clearOutput();
        $tui->requestRender(true);
        $tui->processRender();
        $screen = new ScreenBuffer($columns, $rows);
        $screen->write($terminal->getOutput());

        return $screen->getStyledScreen();
    }
}
