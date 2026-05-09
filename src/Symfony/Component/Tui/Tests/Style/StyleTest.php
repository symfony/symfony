<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VerticalAlign;

class StyleTest extends TestCase
{
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
        $this->assertSame(
            Color::from('red')->toForegroundCode(),
            $border->color?->toForegroundCode(),
        );
    }

    public function testWithBorderPatternAndColor()
    {
        $style = Style::border([1])
            ->withBorderPattern(BorderPattern::WIDE)
            ->withBorderColor('yellow');

        $this->assertSame(BorderPattern::wide()->getChars(), $style->getBorder()->pattern->getChars());
        $this->assertSame(
            Color::from('yellow')->toForegroundCode(),
            $style->getBorder()->color?->toForegroundCode(),
        );
    }

    public function testApplyWithNoStyles()
    {
        $style = new Style();
        $this->assertSame('hello', $style->apply('hello'));
    }

    /**
     * @return iterable<string, array{Style, string}>
     */
    public static function applyStyleProvider(): iterable
    {
        yield 'bold' => [new Style()->withBold(), "\x1b[1m"];
        yield 'color red' => [new Style()->withColor('red'), "\x1b[31m"];
        yield 'background blue' => [new Style()->withBackground('blue'), "\x1b[44m"];
    }

    #[DataProvider('applyStyleProvider')]
    public function testApplyEmitsExpectedAnsiCode(Style $style, string $expectedCode)
    {
        $result = $style->apply('hello');
        $this->assertStringContainsString($expectedCode, $result);
        $this->assertStringContainsString('hello', $result);
    }

    /**
     * @return iterable<string, array{Style, string}>
     */
    public static function explicitFalseResetCodeProvider(): iterable
    {
        yield 'bold false → SGR 22' => [new Style()->withBold(false), "\x1b[22m"];
        yield 'italic false → SGR 23' => [new Style()->withItalic(false), "\x1b[23m"];
        yield 'underline false → SGR 24' => [new Style()->withUnderline(false), "\x1b[24m"];
        yield 'strikethrough false → SGR 29' => [new Style()->withStrikethrough(false), "\x1b[29m"];
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
        $style = new Style()->withDim();
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
        $style = new Style()->withDim(false);
        $result = $style->apply('hello');

        $this->assertStringContainsString("\x1b[22m", $result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testBoldAndDimCanCoexist()
    {
        // Bold and dim are independent attributes; both can be enabled
        $style = new Style()->withBold()->withDim();
        $result = $style->apply('hello');

        // Should contain both SGR 1 (bold) and SGR 2 (dim)
        $this->assertStringContainsString("\x1b[1m", $result);
        $this->assertStringContainsString("\x1b[2m", $result);
        $this->assertTrue($style->getBold());
        $this->assertTrue($style->getDim());
    }

    /**
     * SGR 22 resets both bold and dim. When one is true and the other explicitly
     * false, the reset must come before the re-enable so the active attribute
     * is not cancelled.
     *
     * @return iterable<string, array{Style, non-empty-string}>
     */
    public static function boldDimInteractionProvider(): iterable
    {
        yield 'dim=true, bold=false → prefix: reset then dim' => [
            new Style(bold: false, dim: true),
            "\x1b[22m\x1b[2m",
        ];
        yield 'bold=true, dim=false → prefix: reset then bold' => [
            new Style(bold: true, dim: false),
            "\x1b[22m\x1b[1m",
        ];
    }

    /**
     * @param non-empty-string $expectedPrefix
     */
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
        $style = new Style(bold: false, dim: false);
        $result = $style->apply('hello');

        // Should contain exactly one SGR 22 reset
        $this->assertSame(1, substr_count($result, "\x1b[22m"));
    }

    public function testBoldAndDimBothTrueNoReset()
    {
        // When both are true, no SGR 22 reset should appear in the prefix
        $style = new Style(bold: true, dim: true);
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
        $style = new Style(bold: true, dim: false);
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
        $style = new Style()->withDim()->withColor('cyan');
        $result = $style->apply('hello');

        // Should contain both dim (SGR 2) and cyan foreground (SGR 36)
        $this->assertStringContainsString("\x1b[2m", $result);
        $this->assertStringContainsString("\x1b[36m", $result);
    }

    public function testWithoutLayoutPropertiesStripsAllLayoutProperties()
    {
        $style = new Style()
            ->withPadding([2, 4])
            ->withBorder([1])
            ->withGap(2)
            ->withDirection(Direction::Horizontal)
            ->withHidden()
            ->withCursorShape(CursorShape::Block)
            ->withTextAlign(TextAlign::Center)
            ->withAlign(Align::Center)
            ->withVerticalAlign(VerticalAlign::Center)
            ->withFlex(1)
            ->withFont('big')
            ->withColor('red')
            ->withBold();
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
        $style = new Style()->withPadding([2])->withGap(1)->withColor('red');
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
        $style = new Style(bold: true, color: 'red');
        $result = Style::mergeAll([$style]);

        $this->assertTrue($result->getBold());
        $this->assertNotNull($result->getColor());
    }

    public function testMergeAllLaterOverridesEarlier()
    {
        $base = new Style(bold: true, italic: true, color: 'red');
        $override = new Style(bold: false, color: 'blue');

        $result = Style::mergeAll([$base, $override]);

        $this->assertFalse($result->getBold());
        $this->assertTrue($result->getItalic());
        // Color overridden from red to blue
        $this->assertNotNull($result->getColor());
    }

    public function testMergeAllMergesDisjointProperties()
    {
        $a = new Style(
            padding: Padding::from([1, 2]),
            bold: true,
            color: 'red',
            direction: Direction::Horizontal,
            gap: 2,
            align: Align::Center,
        );
        $b = new Style(
            border: Border::from([1]),
            italic: true,
            background: 'blue',
            maxColumns: 80,
            verticalAlign: VerticalAlign::Bottom,
        );

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
        $a = new Style(bold: true, color: 'red');
        $b = new Style(italic: true, background: 'blue');
        $c = new Style(bold: false, underline: true, color: 'green');

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
        $style = new Style(
            padding: Padding::from([1]),
            border: Border::from([1]),
            background: '#ff0000',
            color: '#00ff00',
            bold: true,
            dim: true,
            italic: true,
            strikethrough: true,
            underline: true,
            reverse: true,
            direction: Direction::Horizontal,
            gap: 3,
            hidden: true,
            cursorShape: CursorShape::Block,
            textAlign: TextAlign::Center,
            font: 'big',
            maxColumns: 80,
            align: Align::Right,
            verticalAlign: VerticalAlign::Center,
            flex: 2,
        );

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
}
