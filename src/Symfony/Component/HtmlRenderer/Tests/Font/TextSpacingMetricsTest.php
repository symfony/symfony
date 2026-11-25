<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Font;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;

class TextSpacingMetricsTest extends TestCase
{
    public function testDefaultSpacingPreservesExistingMetrics()
    {
        $font = new FontStyle();
        self::assertSame(0.0, $font->letterSpacing);
        self::assertSame(0.0, $font->wordSpacing);
        $metrics = new MonospaceTextMeasurer()->measure('a b', $font);
        self::assertSame(24.0, $metrics->width);
        self::assertSame(12.8, $metrics->ascent);
        self::assertEqualsWithDelta(3.2, $metrics->descent, 0.000000000001);
        self::assertNull($metrics->caretPositions);
    }

    public function testGraphemeTrackingAndAsciiWordSpacingAreIndependent()
    {
        $measurer = new MonospaceTextMeasurer();
        $font = new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', 2, 3);
        self::assertSame(30.0, $measurer->measure('abc', $font)->width);
        self::assertSame(33.0, $measurer->measure('a b', $font)->width);
        self::assertSame(20.0, $measurer->measure("e\u{0301}x", $font)->width);
        self::assertSame(30.0, $measurer->measure("a\u{00A0}b", $font)->width);
        self::assertSame(0.0, $measurer->measure('', $font)->width);
    }

    public function testSignedSpacingCanOverlapWithoutNegativeUsedWidth()
    {
        $font = new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', -2.5, -1.25);
        self::assertSame(15.25, new MonospaceTextMeasurer()->measure('a b', $font)->width);
        self::assertSame(0.0, new MonospaceTextMeasurer()->measure('abc', new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', -20))->width);
    }

    public function testArabicSpacingIsAnExplicitUnshapedApproximation()
    {
        $measurer = new MonospaceTextMeasurer();
        $font = new FontStyle('sans-serif', 16, 19.2, 400, 'rtl', 'normal', 2, 3);
        self::assertSame(32.0, $measurer->measure('سلام', $font)->width);
        self::assertSame(24.0, $measurer->measure('١٢٣', $font)->width);
        self::assertSame(52.0, $measurer->measure('aسلامb', $font)->width);
        self::assertSame(30.0, $measurer->measure('אבג', $font)->width);
    }

    public function testProvidersReceiveSpacingTogetherWithTheirShapingRequest()
    {
        $provider = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(4.25 + $font->letterSpacing + $font->wordSpacing, 7.5, 2.5, [0.0, 4.25 + $font->letterSpacing + $font->wordSpacing]);
            }
        };
        $metrics = $provider->measure('W', new FontStyle('serif', 10, 12, 400, 'ltr', 'normal', .25, .5));
        self::assertSame(5.0, $metrics->width);
        self::assertSame([0.0, 5.0], $metrics->caretPositions);
    }

    public function testNonFiniteSpacingIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', \INF);
    }

    public function testNonFiniteWordSpacingIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new FontStyle('sans-serif', 16, 19.2, 400, 'ltr', 'normal', 0, \NAN);
    }
}
