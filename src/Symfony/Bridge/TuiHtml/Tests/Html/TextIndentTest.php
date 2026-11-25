<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Bridge\TuiHtml\Widget\Text\InlineFormat;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

final class TextIndentTest extends TestCase
{
    private const TEXT = 'one two three four five six';

    public function testALength()
    {
        $this->assertSame(['    one', 'two three', 'four five', 'six'], $this->render('text-indent: 4ch'));
    }

    public function testANegativeLengthGoesOverThePadding()
    {
        // Like a hanging list of terms: the first line where the padding starts
        $this->assertSame(['one two', '  three', '  four', '  five six'], $this->render('padding-left: 2ch; text-indent: -2ch'));
    }

    public function testANegativeLengthWithoutPaddingPushesTheOtherLinesIn()
    {
        $this->assertSame(['one two', '   three', '   four', '   five', '   six'], $this->render('text-indent: -3ch'));
    }

    public function testHanging()
    {
        $this->assertSame(['one two', '  three', '  four', '  five six'], $this->render('text-indent: 2ch hanging'));
    }

    public function testTheFirstLineOfAHangingIndentIsAsWideAsTheBlock()
    {
        // Like CSS: only the lines after it are in, and narrower
        $this->assertSame(['one two', '  three', '  four'], $this->render('text-indent: 2ch hanging', 'one two three four'));
        $this->assertSame(['abc de fgh', '  ij kl'], $this->render('text-indent: 2ch hanging', 'abc de fgh ij kl'));
        $this->assertSame(['abc de fgh', '  ij kl'], $this->render('padding-left: 2ch; text-indent: -2ch', 'abc de fgh ij kl'), 'Pulled over the padding too.');
        $this->assertSame(['abc  de fg', '  hi jk'], $this->render('text-indent: 2ch hanging; text-align: justify', 'abc de fg hi jk'), 'Its last line not justified.');
    }

    public function testAPercentageOfTheWidth()
    {
        $this->assertSame(['    one', 'two three', 'four five', 'six'], $this->render('text-indent: 40%'));
    }

    public function testEachLine()
    {
        $this->assertSame(['  one two', '  three'], $this->render('text-indent: 2ch each-line', 'one two<br>three'));
        $this->assertSame(['  one two', 'three'], $this->render('text-indent: 2ch', 'one two<br>three'));
    }

    public function testInvalidValuesAreIgnored()
    {
        $this->assertSame(['one two', 'three four', 'five six'], $this->render('text-indent: 2ch 3ch'));
        $this->assertSame(['one two', 'three four', 'five six'], $this->render('text-indent: hanging'));
    }

    public function testIndentsAreBounded()
    {
        $format = new InlineFormat(null, false, null, null, null, 'normal', null, -50000, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, \INF);
        $this->assertSame([0, 10000], $format->indents(80));
        // At most the width; hanging and negative, the first line is the one further in
        $this->assertSame([5, 0], (new InlineFormat(null, false, null, null, null, 'normal', null, 0, 0, 0, null, null, 0, false, true, false, 8, [0 => 0, 1 => 0, 2 => 0, 3 => 0], null, null, 0, null, 'auto', null, -500.0, true))->indents(5));
    }

    private function render(string $declarations, string $text = self::TEXT): array
    {
        $terminal = new VirtualTerminal(10, 8);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { '.$declarations.' }</style><p>'.$text.'</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(10, 8);
        $buffer->write($terminal->consumeOutput());
        $rows = array_map('rtrim', explode("\n", $buffer->getScreen()));
        while ($rows && '' === end($rows)) {
            array_pop($rows);
        }

        return $rows;
    }
}
