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

final class EmptySelectorTest extends TestCase
{
    public function testAnElementHoldingTextIsNotEmpty()
    {
        // Like the .badge:empty { display: none } of Bootstrap
        $lines = $this->render('<style>.x:empty { display: none } p { margin: 0 }</style><p>Text <span class="x">badge</span> <b class="x"></b>end</p><div class="x">block text</div><div class="x"></div><section class="x"><p>child</p></section><p>after</p>');
        $this->assertSame(['Text badge end', 'block text', 'child', 'after'], $lines, 'Inline or block, with text it is not empty; without, it is.');
    }

    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(30, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 8);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
