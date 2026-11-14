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
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

class InterpolateSizeTest extends TestCase
{
    private float $now = 1000.0;

    public function testAHeightTransitionsToAuto()
    {
        $this->assertSame([0, 3, 6, 6], $this->heights('.box { interpolate-size: allow-keywords }', 'auto'));
    }

    public function testInterpolateSizeIsInherited()
    {
        $this->assertSame([0, 3, 6, 6], $this->heights('body, div { interpolate-size: allow-keywords }', 'auto'));
    }

    public function testWithoutInterpolateSizeTheChangeIsAtOnce()
    {
        $this->assertSame([0, 6, 6, 6], $this->heights('', 'auto'));
    }

    public function testCalcSizeTransitionsWithoutInterpolateSize()
    {
        $this->assertSame([0, 4, 8, 6], $this->heights('', 'calc-size(auto, size + 2)'), 'Its own end, auto once it is done.');
    }

    private function heights(string $css, string $open): array
    {
        $sheet = new CssStyleSheet();
        $sheet->setAnimationHandlers(null, fn (): float => $this->now);
        $view = (new HtmlBuilder($sheet))->setDefaultStyles(false)->build('<style>
            .box { height: 0; overflow: hidden; transition: height 1s linear }
            .box:hover { height: '.$open.' }
            '.$css.'
            </style><div class="box" id="box"><p>1</p><p>2</p><p>3</p><p>4</p><p>5</p><p>6</p></div><p>after</p>');
        $tui = new Tui(null, new VirtualTerminal(20, 20));
        $view->mount($tui);
        $tui->start();
        $box = $view->getById('box');
        $rows = static fn (): int => $tui->getWidgetRect($box)?->rows ?? 0;
        $render = static function () use ($tui) {
            $tui->requestRender();
            $tui->processRender();
        };
        $render();
        $heights = [$rows()];
        $box->setState('hover');
        $render();
        $this->now += 0.5;
        $render();
        $heights[] = $rows();
        $this->now += 0.49;
        $render();
        $heights[] = $rows();
        ++$this->now;
        $render();
        $heights[] = $rows();
        $tui->stop();

        return $heights;
    }
}
