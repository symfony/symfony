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

final class WordBreakTest extends TestCase
{
    public function testKeepAllBreaksKoreanAtTheSpacesOnly()
    {
        $html = '<style>p { margin: 0; max-width: 12ch }</style><p%s>한국어 문장을 나누지 않습니다</p>';
        // Each syllable two columns wide
        $this->assertSame(['한국어 문장', '을 나누지 않', '습니다'], $this->render(\sprintf($html, '')), 'Normal: between any two syllables.');
        $this->assertSame(['한국어', '문장을', '나누지', '않습니다'], $this->render(\sprintf($html, ' style="word-break: keep-all"')), 'Keep-all: at the spaces.');
    }

    public function testKeepAllJoinsAWordAcrossInlineElements()
    {
        $this->assertSame(['日本語の', '文章を書きます'], $this->render('<p style="margin: 0; max-width: 14ch; word-break: keep-all">日本語の 文章<b>を書</b>きます</p>'));
    }

    public function testAWordLongerThanTheLineIsStillBroken()
    {
        $lines = $this->render('<p style="margin: 0; max-width: 6ch; word-break: keep-all">日本語の長い単語です</p>');
        $this->assertGreaterThan(1, \count($lines));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(6, mb_strwidth($line));
        }
        $this->assertSame('日本語の長い単語です', implode('', $lines), 'Every character kept, none drawn twice.');
    }

    private function render(string $html): array
    {
        $terminal = new VirtualTerminal(30, 6);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 6);
        $buffer->write($terminal->consumeOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }
}
