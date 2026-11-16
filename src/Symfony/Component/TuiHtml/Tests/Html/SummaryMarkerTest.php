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

final class SummaryMarkerTest extends TestCase
{
    public function testTheSummaryIsStyledLikeAnElement()
    {
        $header = $this->header('<style>.faq summary { color: #ff0000 }</style><details class="faq" id="d"><summary>Question</summary><p>Answer</p></details>');
        $this->assertSame('▶ Question', AnsiUtils::stripAnsiCodes($header));
        $this->assertStringContainsString('38;2;255;0;0m', $header);
    }

    public function testListStyleNoneHidesTheMarker()
    {
        $this->assertSame('Question', AnsiUtils::stripAnsiCodes($this->header('<style>summary { list-style: none }</style><details id="d"><summary>Question</summary></details>')));
        $this->assertSame('Question', AnsiUtils::stripAnsiCodes($this->header('<style>summary::-webkit-details-marker { display: none }</style><details id="d"><summary>Question</summary></details>')));
    }

    public function testTheMarkerContent()
    {
        $css = '<style>summary::marker { content: "+ " } details[open] > summary::marker { content: "− " }</style>';
        $this->assertSame('+ Question', AnsiUtils::stripAnsiCodes($this->header($css.'<details id="d"><summary>Question</summary></details>')));
        $this->assertSame('− Question', AnsiUtils::stripAnsiCodes($this->header($css.'<details id="d" open><summary>Question</summary><p>a</p></details>')), 'Open: the open marker.');
    }

    private function header(string $html): string
    {
        $view = (new HtmlBuilder())->build($html)->computeStyles();

        return $view->getById('d')->render(new RenderContext(40, 5))[0];
    }
}
