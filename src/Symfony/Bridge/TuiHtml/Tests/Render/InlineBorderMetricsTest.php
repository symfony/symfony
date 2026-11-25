<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class InlineBorderMetricsTest extends TestCase
{
    public function testInlineBorderPurposesKeepSideCellsWithoutAddingVerticalCellRows()
    {
        $metrics = new TerminalLayoutMetrics();
        self::assertSame(0.0, $metrics->constrainLength(1, 'inline-border-top-width', true));
        self::assertSame(0.0, $metrics->constrainLength(1, 'inline-border-bottom-width', true));
        self::assertSame(8.0, $metrics->constrainLength(32, 'inline-border-left-width', false));
        self::assertSame(16.0, $metrics->constrainLength(1, 'border-top-width', true));
    }

    public function testTerminalInlineBordersStayInTheSurroundingTextLine()
    {
        $html = '<style>p{margin:0}kbd{border:1px solid;padding:0 1ch;filter:none}</style><p>Press <kbd id="key">Ctrl</kbd>+<kbd>C</kbd></p>';
        $session = (new HtmlRenderer())->createSession($html, new RenderingContext(320, 80));
        $lines = (new DocumentWidget($session))->render(new RenderContext(40, 5));
        self::assertSame('Press │ Ctrl │+│ C │', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        self::assertSame(16.0, $session->render()->getBounds($session->getDocument()->getElementById('key'))[0]->height);
    }

    public function testStandaloneInlineBordersRetainFractionalUsedGeometry()
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:16px;line-height:16px">A<span id="s" style="border:.25px solid">B</span></p>', new RenderingContext(200, 100));
        self::assertSame(16.5, $result->getBounds($result->getDocument()->getElementById('s'))[0]->height);
        $borders = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type']));
        self::assertSame(0.25, $borders[0]['thickness']);
    }

    public function testAtomicAndFlexItemBordersKeepTheirVerticalRows()
    {
        $session = (new HtmlRenderer())->createSession('<div><span id="a" style="display:inline-block;border:1px solid">X</span></div><div style="display:flex"><span id="b" style="border:1px solid">Y</span></div>', new RenderingContext(320, 160));
        (new DocumentWidget($session))->render(new RenderContext(40, 10));
        $result = $session->render();
        self::assertSame(48.0, $result->getBounds($session->getDocument()->getElementById('a'))[0]->height);
        self::assertSame(48.0, $result->getBounds($session->getDocument()->getElementById('b'))[0]->height);
    }

    public function testReplacedFloatedPositionedAndGridItemsKeepAtomicBorders()
    {
        $src = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="8" height="16"/>');
        foreach ([
            '<input id="s" type="button" value="Go" style="display:inline;border:1px solid">',
            '<img id="s" src="'.$src.'" style="display:inline;border:1px solid">',
            '<span id="s" style="float:left;border:1px solid">X</span>',
            '<span id="s" style="position:absolute;left:0;top:0;border:1px solid">X</span>',
            '<div style="display:grid"><span id="s" style="border:1px solid">X</span></div>',
        ] as $html) {
            $session = (new HtmlRenderer())->createSession($html, new RenderingContext(320, 320));
            (new DocumentWidget($session))->render(new RenderContext(40, 20));
            $result = $session->render();
            $bounds = $result->getBounds($session->getDocument()->getElementById('s'))[0];
            $borders = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'border' === $op['type'] && $op['x'] === $bounds->x && $op['y'] === $bounds->y && $op['width'] === $bounds->width && $op['height'] === $bounds->height));
            self::assertNotEmpty($borders, $html);
            self::assertSame(16.0, $borders[0]['sides']['top']['thickness'] ?? $borders[0]['thickness'], $html);
        }
    }
}
