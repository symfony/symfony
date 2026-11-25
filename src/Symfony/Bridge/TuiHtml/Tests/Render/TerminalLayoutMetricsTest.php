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
use Symfony\Bridge\TuiHtml\Css\Length;
use Symfony\Bridge\TuiHtml\Render\TerminalLayoutMetrics;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class TerminalLayoutMetricsTest extends TestCase
{
    public function testAbsoluteUnitsKeepExistingCellConversionAtDifferentDensities()
    {
        foreach ([[8, 16], [16, 32]] as [$width, $height]) {
            $measure = new TerminalTextMeasurer($width, $height);
            $engine = new LayoutEngine($measure, new TerminalLayoutMetrics($measure));
            $media = (new MediaContext())->withCellSize(new CellSize($width, $height));
            foreach (['.125in', '.375cm', '3.75mm', '15q', '4.5pt', '.375pc', '6px', 'calc(.25in + 2pt)'] as $length) {
                $fragment = $engine->layout(new Box(null, 'block', ['width' => $length, 'height' => $length]), 1000, 1000);
                self::assertSame((float) (Length::toCells($length, $media) * $width), $fragment->bounds->width, $length);
                self::assertSame((float) (Length::toCells($length, $media, true) * $height), $fragment->bounds->height, $length);
            }
        }
    }

    public function testScriptBaselinesUseTerminalGlyphApproximation()
    {
        $metrics = new TerminalLayoutMetrics();
        $this->assertSame(0.0, $metrics->constrainLength(8.0, 'vertical-align-super', true));
        $this->assertSame(0.0, $metrics->constrainLength(-4.0, 'vertical-align-sub', true));
        $this->assertSame(16.0, $metrics->constrainLength(16.0, 'height', true));
    }

    public function testInlineBaselinesMatchTerminalPaintAndPointerCoordinates()
    {
        $metrics = new TerminalLayoutMetrics();
        $this->assertSame(0.0, $metrics->constrainLength(12.8, 'inline-baseline-position', true));
        $this->assertSame(16.0, $metrics->constrainLength(28.8, 'inline-baseline-position', true));
        $this->assertSame(-16.0, $metrics->constrainLength(-3.2, 'inline-baseline-position', true));
    }

    public function testUnitlessLengthsUseCellsAndPhysicalDensityIsExplicit()
    {
        $metrics = new TerminalLayoutMetrics();
        $this->assertSame(16.0, $metrics->resolveUnit(2, '', false, 2));
        $this->assertSame(32.0, $metrics->resolveUnit(2, '', true, 2));
        $this->assertSame(2.0, $metrics->resolveUnit(2, 'px', false, 2));
        $dense = new TerminalLayoutMetrics(new TerminalTextMeasurer(16, 32));
        $this->assertSame(32.0, $dense->resolveUnit(16, 'px', false, 16));
        $this->assertSame(16.0, $dense->resolveUnit(1, 'ch', false, 16));
    }

    public function testUsesExistingTerminalSpacingThresholdsBeforeLayout()
    {
        $metrics = new TerminalLayoutMetrics();
        $this->assertSame(8.0, $metrics->constrainLength(1, 'border-left-width', false));
        $this->assertSame(16.0, $metrics->constrainLength(1, 'border-top-width', true));
        $this->assertSame(0.0, $metrics->constrainLength(8, 'padding-top', true));
        $this->assertSame(16.0, $metrics->constrainLength(12, 'padding-top', true));
        $this->assertSame(8.0, $metrics->constrainLength(1, 'padding-left', false));
        $this->assertSame(0.0, $metrics->constrainLength(0, 'height', true));
    }

    public function testDistributesWholeCellsWithoutLosingTheSharedExtent()
    {
        $metrics = new TerminalLayoutMetrics();
        $this->assertSame([32.0, 24.0, 24.0], $metrics->distribute([80 / 3, 80 / 3, 80 / 3], false));
        $this->assertSame([16.0, 16.0, 24.0], $metrics->distribute([12.8, 20.0, 23.2], false));
    }

    public function testBackgroundIntrinsicPixelsKeepTheTerminalImageCellPolicy()
    {
        foreach ([[8.0, 16.0], [16.0, 32.0], [8.0, 32.0]] as [$width, $height]) {
            $metrics = new TerminalLayoutMetrics(new TerminalTextMeasurer($width, $height));
            $naturalWidth = $metrics->constrainLength(17.0, 'background-intrinsic-width', false);
            self::assertSame(3 * $width, $naturalWidth);
            self::assertSame(3 * $height, $metrics->constrainLength($naturalWidth * 37 / 17, 'background-intrinsic-height', true));
            self::assertSame(0.0, $metrics->constrainLength(.1 * $width, 'background-position', false));
            self::assertSame(1000 * $width, $metrics->constrainLength(10000 * $width, 'background-size', false));
            self::assertSame(1000 * $width, $metrics->constrainLength(10000 * $width, 'image-object-size', false));
            self::assertSame(1000 * $height, $metrics->constrainLength(10000 * $height, 'image-object-size', true));
            self::assertSame(0.0, $metrics->constrainLength(0, 'image-object-size', true));
        }
    }
}
