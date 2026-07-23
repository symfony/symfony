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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;

class BorderPatternTest extends TestCase
{
    #[DataProvider('allPatternsProvider')]
    public function testFromNameReturnsExpectedCharsAndIsNone(string $name, ?string $method, bool $isNone)
    {
        $fromName = BorderPattern::fromName($name);

        if (null !== $method) {
            $fromMethod = BorderPattern::$method();
            $this->assertSame($fromMethod->getChars(), $fromName->getChars());
            $this->assertSame($fromMethod->getStrategies(), $fromName->getStrategies());
        }

        $this->assertSame($isNone, $fromName->isNone());
    }

    /**
     * @return iterable<string, array{string, ?string, bool}>
     */
    public static function allPatternsProvider(): iterable
    {
        yield 'none' => [BorderPattern::NONE, null, true];
        yield 'normal' => [BorderPattern::NORMAL, 'normal', false];
        yield 'rounded' => [BorderPattern::ROUNDED, 'rounded', false];
        yield 'double' => [BorderPattern::DOUBLE, 'double', false];
        yield 'tall' => [BorderPattern::TALL, 'tall', false];
        yield 'wide' => [BorderPattern::WIDE, 'wide', false];
        yield 'tall-medium' => [BorderPattern::TALL_MEDIUM, 'tallMedium', false];
        yield 'wide-medium' => [BorderPattern::WIDE_MEDIUM, 'wideMedium', false];
        yield 'tall-large' => [BorderPattern::TALL_LARGE, 'tallLarge', false];
        yield 'wide-large' => [BorderPattern::WIDE_LARGE, 'wideLarge', false];
    }

    public function testFromNameThrowsOnUnknown()
    {
        $this->expectException(InvalidArgumentException::class);
        BorderPattern::fromName('unknown');
    }

    #[DataProvider('reverseVideoStrategiesProvider')]
    public function testReverseVideoStrategiesEmitReverseVideoAnsi(int $strategy)
    {
        $pattern = new BorderPattern();
        $outerStyle = new Style();
        $innerStyle = (new Style())->withBackground('blue');

        $result = $pattern->applyBorderSegment('▊', $strategy, $outerStyle, $innerStyle, Color::from('white'));

        $this->assertStringContainsString("\e[7m", $result);
        $this->assertStringContainsString("\e[27m", $result);
    }

    #[DataProvider('nonReverseVideoStrategiesProvider')]
    public function testNonReverseVideoStrategiesDoNotEmitReverseVideoAnsi(int $strategy)
    {
        $pattern = new BorderPattern();
        $outerStyle = new Style();
        $innerStyle = (new Style())->withBackground('blue');

        $result = $pattern->applyBorderSegment('│', $strategy, $outerStyle, $innerStyle, Color::from('white'));

        $this->assertStringNotContainsString("\e[7m", $result);
    }

    #[DataProvider('reverseVideoStrategiesProvider')]
    public function testNullBorderColorInStrategy2And3GoesToForegroundSlot(int $strategy)
    {
        $pattern = new BorderPattern();
        $outerStyle = new Style();
        $innerStyle = (new Style())->withBackground('blue');

        $result = $pattern->applyBorderSegment('▊', $strategy, $outerStyle, $innerStyle, null);

        $this->assertStringContainsString(Color::resetForeground(), $result);
    }

    public function testNullOuterBackgroundInStrategy2GoesToBackgroundSlot()
    {
        $pattern = new BorderPattern();
        $outerStyle = new Style();
        $innerStyle = new Style();

        $result = $pattern->applyBorderSegment('▊', 2, $outerStyle, $innerStyle, Color::from('white'));

        $this->assertStringContainsString(Color::resetBackground(), $result);
    }

    public function testStrategy2WithExplicitColorsProducesCorrectAnsiCodes()
    {
        $pattern = new BorderPattern();
        $outerStyle = (new Style())->withBackground('black');
        $innerStyle = new Style();
        $borderColor = Color::from('white');

        $result = $pattern->applyBorderSegment('▊', 2, $outerStyle, $innerStyle, $borderColor);

        $this->assertStringContainsString($borderColor->toForegroundCode(), $result);
        $this->assertStringContainsString(Color::from('black')->toBackgroundCode(), $result);
        $this->assertStringContainsString("\e[7m", $result);
        $this->assertStringContainsString("\e[27m", $result);
    }

    public function testStrategy3WithExplicitColorsProducesCorrectAnsiCodes()
    {
        $pattern = new BorderPattern();
        $outerStyle = new Style();
        $innerStyle = (new Style())->withBackground('blue');
        $borderColor = Color::from('white');

        $result = $pattern->applyBorderSegment('▊', 3, $outerStyle, $innerStyle, $borderColor);

        $this->assertStringContainsString($borderColor->toForegroundCode(), $result);
        $this->assertStringContainsString(Color::from('blue')->toBackgroundCode(), $result);
        $this->assertStringContainsString("\e[7m", $result);
        $this->assertStringContainsString("\e[27m", $result);
    }

    public static function reverseVideoStrategiesProvider(): iterable
    {
        yield 'strategy 2' => [2];
        yield 'strategy 3' => [3];
    }

    public static function nonReverseVideoStrategiesProvider(): iterable
    {
        yield 'strategy 0 (default)' => [0];
        yield 'strategy 1' => [1];
    }
}
