<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class UnicodeLineBreakingTest extends TestCase
{
    public function testIdeographsWrapWithoutSpacesAndAutomaticJustificationUsesCharacterGaps()
    {
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics((preg_match_all('/\X/u', $text) ?: 0) * 10.0, 8, 2);
            }
        };
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px', 'text-align' => 'justify'], [new Box(null, 'inline', [], [], '日本語文')]);
        $fragment = (new LayoutEngine($measurer))->layout($box, 25, 100);
        $runs = $fragment->children[0]->children;
        self::assertSame(['日', '本', '語文'], array_column($runs, 'text'));
        self::assertSame(15.0, $runs[1]->bounds->x);
        self::assertSame(12.0, $runs[2]->bounds->y);
    }

    public function testBreakOpportunitiesNeverSplitAGraphemeCluster()
    {
        $text = "日\u{FE00}本語";
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px'], [new Box(null, 'inline', [], [], $text)]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 10, 100);
        self::assertSame(["日\u{FE00}本", '語'], array_column($fragment->children[0]->children, 'text'));
    }

    public function testKeepAllRetainsAnUnbrokenIdeographicWord()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px', 'word-break' => 'keep-all'], [new Box(null, 'inline', [], [], '日本語文')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 10, 100);
        self::assertSame(['日本語文'], array_column($fragment->children[0]->children, 'text'));
    }

    public function testInterCharacterJustificationSpreadsLettersRatherThanSpaces()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px', 'text-align' => 'justify', 'text-justify' => 'inter-character'], [new Box(null, 'inline', [], [], 'abcd efgh')]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($box, 25, 100);
        $runs = $fragment->children[0]->children;
        self::assertSame(['a', 'b', 'c', 'd', 'efgh'], array_column($runs, 'text'));
        self::assertEqualsWithDelta(20 / 3, $runs[1]->bounds->x, .000001);
    }
}
