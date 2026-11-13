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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;

final class PositionedInlineTest extends TestCase
{
    public function testAPositionedSpanIsPlaced()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 8));
        $view = (new HtmlBuilder())->build('<style>.card { position: relative; height: 3; border: 1px solid } .badge { position: absolute; top: 0; right: 0 } </style>
            <div class="card"><p id="p">A title <span class="badge" id="badge">new</span> and more text</p></div>
            <p id="plain">Plain <span>inline</span> text</p>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $badge = $tui->getWidgetRect($view->getById('badge'));
        $this->assertSame([1, 36, 3], [$badge->row, $badge->col, $badge->columns], 'At the top right of the card, inside its border: columns 36 to 38.');
        $this->assertInstanceOf(ContainerWidget::class, $view->getById('p'), 'Its paragraph holds it and its text.');
        $this->assertSame('A title and more text', implode(' ', array_map(static fn (RichTextWidget $text) => trim($text->getPlainText()), array_filter($view->getById('p')->all(), static fn ($child) => $child instanceof RichTextWidget && 'badge' !== $child->getId()))));
        $this->assertInstanceOf(RichTextWidget::class, $view->getById('plain'), 'Without positioned inline elements, a paragraph stays one text.');
    }
}
