<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\TuiHtml\Style\Align;
use Symfony\Component\TuiHtml\Style\Border;
use Symfony\Component\TuiHtml\Style\BorderPattern;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\CursorShape;
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Style\Padding;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\TextAlign;
use Symfony\Component\TuiHtml\Style\VerticalAlign;

class StyleTest extends TestCase
{
    public function testEquals()
    {
        $style = new Style(new Padding(1, 2, 1, 2), null, null, 'red');
        $this->assertTrue($style->equals(new Style(new Padding(1, 2, 1, 2), null, null, 'red')), 'Other objects of the same values.');
        $style->getAnsiRestore();
        $this->assertTrue($style->equals(new Style(new Padding(1, 2, 1, 2), null, null, 'red')), 'The codes computed from it apart.');
        $this->assertFalse($style->equals(new Style(new Padding(1, 2, 1, 2), null, null, 'blue')));
        $this->assertFalse((new Style())->withRows(0, 0)->equals((new Style())->withRows(null, null)), 'Strictly: 0 rows is not auto.');
        $this->assertFalse($style->equals(null));
    }

    public function testBorderWithSingleElementArray()
    {
        $style = Style::border([2], BorderPattern::DOUBLE, 'red');
        $border = $style->getBorder();
        $this->assertSame(2, $border->top);
        $this->assertSame(2, $border->right);
        $this->assertSame(2, $border->bottom);
        $this->assertSame(2, $border->left);
        $this->assertSame(BorderPattern::double()->getChars(), $border->pattern->getChars());
        $this->assertSame(BorderPattern::double()->getStrategies(), $border->pattern->getStrategies());
        $this->assertSame(Color::from('red')->toForegroundCode(), $border->color?->toForegroundCode());
    }

    public function testWithBorderPatternAndColor()
    {
        $style = Style::border([1])->withBorderPattern(BorderPattern::WIDE)->withBorderColor('yellow');
        $this->assertSame(BorderPattern::wide()->getChars(), $style->getBorder()->pattern->getChars());
        $this->assertSame(Color::from('yellow')->toForegroundCode(), $style->getBorder()->color?->toForegroundCode());
    }

    public function testApplyWithNoStyles()
    {
        $style = new Style();
        $this->assertSame('hello', $style->apply('hello'));
    }

    public static function applyStyleProvider(): iterable
    {
        yield 'bold' => [(new Style())->withBold(), "\x1b[1m"];
        yield 'color red' => [(new Style())->withColor('red'), "\x1b[31m"];
        yield 'background blue' => [(new Style())->withBackground('blue'), "\x1b[44m"];
    }

    #[DataProvider('applyStyleProvider')]
    public function testApplyEmitsExpectedAnsiCode(Style $style, string $expectedCode)
    {
        $result = $style->apply('hello');
        $this->assertStringContainsString($expectedCode, $result);
        $this->assertStringContainsString('hello', $result);
    }

    public static function explicitFalseResetCodeProvider(): iterable
    {
        yield 'bold false → SGR 22' => [(new Style())->withBold(false), "\x1b[22m"];
        yield 'italic false → SGR 23' => [(new Style())->withItalic(false), "\x1b[23m"];
        yield 'underline false → SGR 24' => [(new Style())->withUnderline(false), "\x1b[24m"];
        yield 'strikethrough false → SGR 29' => [(new Style())->withStrikethrough(false), "\x1b[29m"];
    }

    #[DataProvider('explicitFalseResetCodeProvider')]
    public function testApplyWithExplicitFalseEmitsResetCode(Style $style, string $expectedCode)
    {
        $result = $style->apply('hello');
        $this->assertStringContainsString($expectedCode, $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testApplyWithBoldNullDoesNotEmitBoldCodes()
    {
        // When bold is null (not set), apply() should not emit any bold codes
        $style = new Style();
        $result = $style->apply('hello');
        // Should not contain bold-on or bold-off codes
        $this->assertStringNotContainsString("\x1b[1m", $result);
        $this->assertStringNotContainsString("\x1b[22m", $result);
        $this->assertSame('hello', $result);
    }

    public function testApplyWithDim()
    {
        $style = (new Style())->withDim();
        $result = $style->apply('hello');
        // Should contain the dim-on code (SGR 2)
        $this->assertStringContainsString("\x1b[2m", $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testApplyWithDimNullDoesNotEmitDimCodes()
    {
        $style = new Style();
        $result = $style->apply('hello');
        // Should not contain dim-on code
        $this->assertStringNotContainsString("\x1b[2m", $result);
        $this->assertSame('hello', $result);
    }

    public function testApplyWithDimFalseEmitsResetCode()
    {
        // When dim is explicitly false, apply() should emit the
        // bold/dim-off code (SGR 22) to cancel any inherited dim
        $style = (new Style())->withDim(false);
        $result = $style->apply('hello');
        $this->assertStringContainsString("\x1b[22m", $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testBoldAndDimCanCoexist()
    {
        // Bold and dim are independent attributes; both can be enabled
        $style = (new Style())->withBold()->withDim();
        $result = $style->apply('hello');
        // Should contain both SGR 1 (bold) and SGR 2 (dim)
        $this->assertStringContainsString("\x1b[1m", $result);
        $this->assertStringContainsString("\x1b[2m", $result);
        $this->assertTrue($style->getBold());
        $this->assertTrue($style->getDim());
    }

    public static function boldDimInteractionProvider(): iterable
    {
        yield 'dim=true, bold=false → prefix: reset then dim' => [new Style(null, null, null, null, false, true), "\x1b[22m\x1b[2m"];
        yield 'bold=true, dim=false → prefix: reset then bold' => [new Style(null, null, null, null, true, false), "\x1b[22m\x1b[1m"];
    }

    #[DataProvider('boldDimInteractionProvider')]
    public function testBoldDimResetOrderInPrefix(Style $style, string $expectedPrefix)
    {
        $result = $style->apply('hello');
        $this->assertStringStartsWith($expectedPrefix, $result);
        $this->assertStringEndsWith("\x1b[22m", $result);
    }

    public function testBoldFalseAndDimFalseEmitsSingleReset()
    {
        // When both bold and dim are explicitly false, only one SGR 22 is needed
        $style = new Style(null, null, null, null, false, false);
        $result = $style->apply('hello');
        // Should contain exactly one SGR 22 reset
        $this->assertSame(1, substr_count($result, "\x1b[22m"));
    }

    public function testBoldAndDimBothTrueNoReset()
    {
        // When both are true, no SGR 22 reset should appear in the prefix
        $style = new Style(null, null, null, null, true, true);
        $result = $style->apply('hello');
        // Prefix should have SGR 1 and SGR 2, suffix should have SGR 22
        $this->assertStringStartsWith("\x1b[1m\x1b[2m", $result);
        $this->assertStringEndsWith("\x1b[22m", $result);
        // Only one SGR 22 (in the suffix), not in the prefix
        $this->assertSame(1, substr_count($result, "\x1b[22m"));
    }

    public function testRestoreWithBoldTrueAndDimFalse()
    {
        // getAnsiRestore() should re-enable bold after a child's reset
        $style = new Style(null, null, null, null, true, false);
        $restore = $style->getAnsiRestore();
        // Restore should contain reset then bold-on
        $this->assertStringContainsString("\x1b[22m", $restore);
        $this->assertStringContainsString("\x1b[1m", $restore);
        // Reset must come before bold enable
        $pos22 = strpos($restore, "\x1b[22m");
        $pos1 = strpos($restore, "\x1b[1m");
        $this->assertLessThan($pos1, $pos22);
    }

    public function testDimAndColorCombination()
    {
        $style = (new Style())->withDim()->withColor('cyan');
        $result = $style->apply('hello');
        // Should contain both dim (SGR 2) and cyan foreground (SGR 36)
        $this->assertStringContainsString("\x1b[2m", $result);
        $this->assertStringContainsString("\x1b[36m", $result);
    }

    public static function applyAroundStyledTextProvider(): iterable
    {
        yield 'color after a colored span' => [new Style(null, null, null, 'red'), "a\x1b[34mb\x1b[39mc", "\x1b[31ma\x1b[34mb\x1b[39m\x1b[31mc\x1b[39m"];
        yield 'dim after a bold span' => [new Style(null, null, null, null, null, true), "a\x1b[1mb\x1b[22mc", "\x1b[2ma\x1b[1mb\x1b[22m\x1b[2mc\x1b[22m"];
        yield 'italic after an italic span' => [new Style(null, null, null, null, null, null, true), "a\x1b[3mb\x1b[23mc", "\x1b[3ma\x1b[3mb\x1b[23m\x1b[3mc\x1b[23m"];
        yield 'background after a background span' => [new Style(null, null, 'blue'), "a\x1b[42mb\x1b[49mc", "\x1b[44ma\x1b[42mb\x1b[49m\x1b[44mc\x1b[49m"];
        yield 'only the attributes reset by the text' => [new Style(null, null, null, 'red', true), "a\x1b[1;34mb\x1b[22mc\x1b[39md", "\x1b[31m\x1b[1ma\x1b[1;34mb\x1b[22m\x1b[1mc\x1b[39m\x1b[31md\x1b[22m\x1b[39m"];
        yield 'everything after a full reset' => [new Style(null, null, null, 'red', true), "\x1b[32mOK\x1b[0m rest", "\x1b[31m\x1b[1m\x1b[32mOK\x1b[0m\x1b[31m\x1b[1m rest\x1b[22m\x1b[39m"];
        yield 'not after a code set after the reset' => [new Style(null, null, 'blue'), "a\x1b[0;42mb", "\x1b[44ma\x1b[0;42mb\x1b[49m"];
        yield 'an explicit false after a span' => [new Style(null, null, null, null, false), "a\x1b[1mb\x1b[22mc", "\x1b[22ma\x1b[1mb\x1b[22m\x1b[22mc"];
        yield 'not after a reset that cancels the attribute' => [new Style(null, null, null, null, true), "\x1b[22ma", "\x1b[1m\x1b[22ma\x1b[22m"];
        yield 'not after a reset that cancels the color' => [new Style(null, null, null, 'red'), "\x1b[39ma", "\x1b[31m\x1b[39ma\x1b[39m"];
        yield 'after a basic color followed by an attribute' => [new Style(null, null, null, null, true), "a\x1b[34;2mb\x1b[22mc", "\x1b[1ma\x1b[34;2mb\x1b[22m\x1b[1mc\x1b[22m"];
        yield 'not after a true color with a zero component' => [new Style(null, null, 'blue'), "\x1b[48;2;255;0;0ma", "\x1b[44m\x1b[48;2;255;0;0ma\x1b[49m"];
    }

    #[DataProvider('applyAroundStyledTextProvider')]
    public function testApplyRestoresItsAttributesAfterTheTextResetsThem(Style $style, string $text, string $expected)
    {
        $this->assertSame($expected, $style->apply($text));
    }

    public function testComputedCssResetsAreKeptWhenStylesAreMerged()
    {
        $style = Style::mergeAll([(new Style())->withCssCascade(), new Style(null, null, 'blue', 'red')]);
        $screen = new ScreenBuffer(4, 1);
        $screen->write($style->apply("\x1b[39mtext"));
        $this->assertSame("\x1b[44m", $screen->getCells()[0][0]['style']);
    }

    public function testWithoutLayoutPropertiesStripsAllLayoutProperties()
    {
        $style = (new Style())->withPadding([2, 4])->withBorder([1])->withGap(2)->withDirection(Direction::Horizontal)->withHidden()->withCursorShape(CursorShape::Block)->withTextAlign(TextAlign::Center)->withAlign(Align::Center)->withVerticalAlign(VerticalAlign::Center)->withFlex(1)->withFont('big')->withColor('red')->withBold();
        $stripped = $style->withoutLayoutProperties();
        // Layout properties stripped
        $this->assertNull($stripped->getPadding());
        $this->assertNull($stripped->getBorder());
        $this->assertNull($stripped->getGap());
        $this->assertNull($stripped->getDirection());
        $this->assertNull($stripped->getHidden());
        $this->assertNull($stripped->getCursorShape());
        $this->assertNull($stripped->getTextAlign());
        $this->assertNull($stripped->getAlign());
        $this->assertNull($stripped->getVerticalAlign());
        $this->assertNull($stripped->getFlex());
        // Content and visual properties preserved
        $this->assertSame(Color::named('red')->toForegroundCode(), $stripped->getColor()->toForegroundCode());
        $this->assertTrue($stripped->getBold());
        $this->assertSame('big', $stripped->getFont());
    }

    public function testWithoutLayoutPropertiesPreservesApplyBehavior()
    {
        $style = (new Style())->withPadding([2])->withGap(1)->withColor('red');
        $stripped = $style->withoutLayoutProperties();
        $original = $style->apply('hello');
        $strippedResult = $stripped->apply('hello');
        // Both should produce the same ANSI formatting (color codes)
        $this->assertSame($original, $strippedResult);
    }

    public function testMergeAllEmpty()
    {
        $result = Style::mergeAll([]);
        $this->assertNull($result->getPadding());
        $this->assertNull($result->getBold());
        $this->assertNull($result->getColor());
    }

    public function testMergeAllSingleStyle()
    {
        $style = new Style(null, null, null, 'red', true);
        $result = Style::mergeAll([$style]);
        $this->assertTrue($result->getBold());
        $this->assertNotNull($result->getColor());
    }

    public function testMergeAllLaterOverridesEarlier()
    {
        $base = new Style(null, null, null, 'red', true, null, true);
        $override = new Style(null, null, null, 'blue', false);
        $result = Style::mergeAll([$base, $override]);
        $this->assertFalse($result->getBold());
        $this->assertTrue($result->getItalic());
        // Color overridden from red to blue
        $this->assertNotNull($result->getColor());
    }

    public function testMergeAllMergesDisjointProperties()
    {
        $a = new Style(Padding::from([1, 2]), null, null, 'red', true, null, null, null, null, null, Direction::Horizontal, 2, null, null, null, null, null, Align::Center);
        $b = new Style(null, Border::from([1]), 'blue', null, null, null, true, null, null, null, null, null, null, null, null, null, 80, null, VerticalAlign::Bottom);
        $result = Style::mergeAll([$a, $b]);
        // Properties from $a
        $this->assertNotNull($result->getPadding());
        $this->assertTrue($result->getBold());
        $this->assertNotNull($result->getColor());
        $this->assertSame(Direction::Horizontal, $result->getDirection());
        $this->assertSame(2, $result->getGap());
        $this->assertSame(Align::Center, $result->getAlign());
        // Properties from $b
        $this->assertNotNull($result->getBorder());
        $this->assertTrue($result->getItalic());
        $this->assertNotNull($result->getBackground());
        $this->assertSame(80, $result->getMaxColumns());
        $this->assertSame(VerticalAlign::Bottom, $result->getVerticalAlign());
    }

    public function testMergeAllThreeStylesCascade()
    {
        $a = new Style(null, null, null, 'red', true);
        $b = new Style(null, null, 'blue', null, null, null, true);
        $c = new Style(null, null, null, 'green', false, null, null, null, true);
        $result = Style::mergeAll([$a, $b, $c]);
        // bold: true (a) → overridden to false (c)
        $this->assertFalse($result->getBold());
        // italic: from b, untouched
        $this->assertTrue($result->getItalic());
        // underline: from c
        $this->assertTrue($result->getUnderline());
        // color: red (a) → overridden to green (c)
        $this->assertNotNull($result->getColor());
        // background: from b
        $this->assertNotNull($result->getBackground());
    }

    public function testMergeAllPreservesAllProperties()
    {
        $style = new Style(Padding::from([1]), Border::from([1]), '#ff0000', '#00ff00', true, true, true, true, true, true, Direction::Horizontal, 3, true, CursorShape::Block, TextAlign::Center, 'big', 80, Align::Right, VerticalAlign::Center, 2);
        // Merge with an empty style; all properties should survive
        $result = Style::mergeAll([new Style(), $style]);
        $this->assertNotNull($result->getPadding());
        $this->assertNotNull($result->getBorder());
        $this->assertNotNull($result->getBackground());
        $this->assertNotNull($result->getColor());
        $this->assertTrue($result->getBold());
        $this->assertTrue($result->getDim());
        $this->assertTrue($result->getItalic());
        $this->assertTrue($result->getStrikethrough());
        $this->assertTrue($result->getUnderline());
        $this->assertTrue($result->getReverse());
        $this->assertSame(Direction::Horizontal, $result->getDirection());
        $this->assertSame(3, $result->getGap());
        $this->assertTrue($result->getHidden());
        $this->assertNotNull($result->getCursorShape());
        $this->assertSame(TextAlign::Center, $result->getTextAlign());
        $this->assertSame('big', $result->getFont());
        $this->assertSame(80, $result->getMaxColumns());
        $this->assertSame(Align::Right, $result->getAlign());
        $this->assertSame(VerticalAlign::Center, $result->getVerticalAlign());
        $this->assertSame(2, $result->getFlex());
    }

    public function testDefaultsSetLaterWinWhenMerged()
    {
        $set = (new Style())->withWritingMode('vertical-rl')->withFontScale(2)->withCaretColor(Color::hex('#ff0000'));
        $back = (new Style())->withWritingMode(null)->withFontScale(null)->withCaretColor(null);
        $merged = Style::mergeAll([$set, $back]);
        $this->assertNull($merged->getWritingMode());
        $this->assertNull($merged->getFontScale());
        $this->assertNull($merged->getCaretColor());
        // Not set: the earlier ones stay
        $merged = Style::mergeAll([$set, new Style()]);
        $this->assertSame('vertical-rl', $merged->getWritingMode());
        $this->assertSame(2, $merged->getFontScale());
        $this->assertSame('#ff0000', $merged->getCaretColor()?->toHex());
    }

    public function testAConcealedStyleDrawsItsTextAsSpacesOnItsBackground()
    {
        $style = (new Style(null, null, Color::hex('#0000ff')))->withConcealed();
        $this->assertSame("\x1b[48;2;0;0;255m  \x1b[1m  \x1b[22m \x1b[49m", $style->apply("ab\x1b[1m一\x1b[22mc"));
        $this->assertTrue(Style::mergeAll([$style, new Style()])->getConcealed());
        $this->assertFalse(Style::mergeAll([$style, (new Style())->withConcealed(false)])->getConcealed());
        $this->assertSame('ab', Style::mergeAll([$style->withBackground(null), (new Style())->withConcealed(false)])->apply('ab'));
    }

    public function testTheRowsAddedToPercentageHeightsAreMergedWithThem()
    {
        $style = (new Style())->withRowsPercent(100, 50, -2, 1);
        $this->assertSame([-2, 1], Style::mergeAll([$style, new Style()])->getRowsPercentOffsets());
        $this->assertSame([0, 1], Style::mergeAll([$style, (new Style())->withRowsPercent(80, null)])->getRowsPercentOffsets(), 'Another least height, without rows.');
        $this->assertSame([0, 0], $style->withoutLayoutProperties()->getRowsPercentOffsets());
    }
}
