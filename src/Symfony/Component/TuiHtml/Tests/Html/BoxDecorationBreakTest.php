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
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\RenderContext;

final class BoxDecorationBreakTest extends TestCase
{
    public function testCloneDrawsTheDecorationsOnEachLine()
    {
        $lines = $this->render('box-decoration-break: clone');
        $this->assertSame(['Say │ one two three │', '│ four five six │', '│ seven │ now.'], $lines);
    }

    public function testSliceDrawsThemAtTheEnds()
    {
        $lines = $this->render('box-decoration-break: slice');
        $this->assertSame('Say │ one two three', $lines[0]);
        $this->assertStringEndsWith('seven │ now.', implode(' ', $lines));
        $this->assertSame(2, substr_count(implode('', $lines), '│'), 'One line on each side, at its ends.');
    }

    public function testClonedDecorationsWiderThanTheLineAreCutToIt()
    {
        // A padding wider than the line on each side: cut, a column of text left between them
        $view = (new HtmlBuilder())->build('<p id="p">Cloned: <span style="border: 1px solid; padding: 0 30ch; box-decoration-break: clone">a quotation</span> end</p>')->computeStyles();
        foreach ($view->getById('p')->render(new RenderContext(13, 10)) as $line) {
            $this->assertLessThanOrEqual(13, AnsiUtils::visibleWidth($line));
        }
    }

    private function render(string $declaration): array
    {
        $view = (new HtmlBuilder())->build('<style>span { border: 1px solid; padding: 0 1ch; '.$declaration.' }</style><p id="p">Say <span>one two three four five six seven</span> now.</p>')->computeStyles();

        return array_map(static fn (string $line) => rtrim(AnsiUtils::stripAnsiCodes($line)), $view->getById('p')->render(new RenderContext(21, 10)));
    }
}
