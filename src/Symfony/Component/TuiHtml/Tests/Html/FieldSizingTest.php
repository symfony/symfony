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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class FieldSizingTest extends TestCase
{
    public function testRowsAndFieldSizing()
    {
        $text = "one\ntwo\nthree\nfour\nfive";
        $rects = $this->layout('.grow { field-sizing: content }', '<textarea id="fixed" rows="2" aria-label="a">'.$text.'</textarea><textarea id="grow" class="grow" rows="2" aria-label="b">'.$text.'</textarea><textarea id="free" aria-label="c">'.$text.'</textarea>');
        // Each between its two rules
        $this->assertSame(2 + 2, $rects['fixed'][1], 'rows: that many lines, the others scrolled.');
        $this->assertSame(5 + 2, $rects['grow'][1], 'field-sizing: content: as many as it has.');
        $this->assertSame(5 + 2, $rects['free'][1], 'Without rows: as many as it has.');
    }

    public function testSizeIsAWidthHint()
    {
        $view = (new HtmlBuilder())->build('<style>.wide { width: 30ch }</style><input id="short" size="10" aria-label="a"><input id="wide" class="wide" size="10" aria-label="b"><input id="box" type="checkbox" size="10">')->computeStyles();
        $width = static fn (string $id) => $view->getStyleSheet()->getComputedValue($view->getById($id), 'width');
        $this->assertSame('11ch', $width('short'), 'Its characters and the cursor.');
        $this->assertSame('30ch', $width('wide'), 'A rule wins.');
        $this->assertNull($width('box'), 'Only text fields.');
    }

    private function layout(string $css, string $html): array
    {
        $tui = new Tui(null, new VirtualTerminal(40, 30));
        $view = (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $rects = [];
        foreach (['fixed', 'grow', 'free'] as $id) {
            $rect = $tui->getWidgetRect($view->getById($id));
            $rects[$id] = [$rect->columns, $rect->rows];
        }

        return $rects;
    }
}
