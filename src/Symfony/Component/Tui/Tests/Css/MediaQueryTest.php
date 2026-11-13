<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\MediaQuery;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\CellSize;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\MediaContext;

final class MediaQueryTest extends TestCase
{
    #[DataProvider('provideQueries')]
    public function testMatches(string $query, bool $expected, ?MediaContext $media = null)
    {
        $this->assertSame($expected, MediaQuery::parse($query)->matches($media ?? new MediaContext(100, 30)));
    }

    public static function provideQueries(): iterable
    {
        yield 'empty' => ['', false];
        yield 'all' => ['all', true];
        yield 'screen' => ['screen', true];
        yield 'tty' => ['tty', true];
        yield 'calc' => ['(min-width: calc(70ch + 10))', true];
        yield 'calc false' => ['(min-width: calc(100ch + 1))', false];
        yield 'min in range' => ['(width >= min(200, 80ch))', true];
        yield 'percent never matches' => ['(min-width: calc(50% + 1ch))', false];
        yield 'print never matches' => ['print', false];
        yield 'not print' => ['not print', true];
        yield 'only screen and' => ['only screen and (min-width: 80)', true];
        yield 'min-width' => ['(min-width: 80)', true];
        yield 'min-width equal' => ['(min-width: 100)', true];
        yield 'min-width above' => ['(min-width: 101)', false];
        yield 'max-width' => ['(max-width: 60ch)', false];
        yield 'width' => ['(width: 100)', true];
        yield 'width as boolean' => ['(width)', true];
        yield 'min-height' => ['(min-height: 30lh)', true];
        yield 'max-height' => ['(max-height: 29)', false];
        yield 'px width' => ['(min-width: 800px)', true];
        yield 'px width too wide' => ['(min-width: 960px)', false];
        yield 'em height' => ['(min-height: 30em)', true];
        yield 'range' => ['(width >= 80)', true];
        yield 'range flipped' => ['(80 <= width)', true];
        yield 'range strict' => ['(width > 100)', false];
        yield 'range equal' => ['(width = 100)', true];
        yield 'double range' => ['(40 < width <= 120)', true];
        yield 'double range outside' => ['(40 < width < 100)', false];
        yield 'and' => ['(min-width: 80) and (max-width: 90)', false];
        yield 'comma is or' => ['(max-width: 10), (min-height: 30)', true];
        yield 'not' => ['not (min-width: 80)', false];
        yield 'not and' => ['not all and (max-width: 10)', true];
        yield 'unknown feature never matches' => ['(foo)', false];
        yield 'unknown feature in a list' => ['(foo), (min-width: 1)', true];
        yield 'not unknown feature' => ['not (foo)', true];
        yield 'garbage' => ['screen and min-width', false];
        yield 'dark' => ['(prefers-color-scheme: dark)', true];
        yield 'light' => ['(prefers-color-scheme: light)', false];
        yield 'light scheme' => ['(prefers-color-scheme: light)', true, new MediaContext(colorScheme: ColorScheme::Light)];
        yield 'prefers-color-scheme as boolean' => ['(prefers-color-scheme)', true];
        yield 'contrast no-preference' => ['(prefers-contrast: no-preference)', true];
        yield 'contrast more' => ['(prefers-contrast: more)', true, new MediaContext(highContrast: true)];
        yield 'contrast as boolean' => ['(prefers-contrast)', false];
        yield 'reduced motion' => ['(prefers-reduced-motion: reduce)', true, new MediaContext(reducedMotion: true)];
        yield 'no reduced motion' => ['(prefers-reduced-motion: reduce)', false];
        yield 'forced-colors none' => ['(forced-colors: none)', true];
        yield 'forced-colors active' => ['(forced-colors: active)', true, new MediaContext(forcedColors: true)];
        yield 'forced-colors as boolean' => ['(forced-colors)', false];
        yield 'color' => ['(color)', true];
        yield 'min-color' => ['(min-color: 8)', true];
        yield 'color forced off' => ['(color)', false, new MediaContext(forcedColors: true)];
        yield 'monochrome' => ['(monochrome)', true, new MediaContext(forcedColors: true)];
        yield 'not monochrome' => ['(monochrome)', false];
        yield 'grid' => ['(grid)', true];
        yield 'grid 1' => ['(grid: 1)', true];
        yield 'grid 0' => ['(grid: 0)', false];
        yield 'resolution 1x by default' => ['(resolution: 1dppx)', true];
        yield 'min-resolution 2x unknown cell' => ['(min-resolution: 2dppx)', false];
        yield 'min-resolution 2x' => ['(min-resolution: 2dppx)', true, (new MediaContext())->withCellSize(new CellSize(16, 32))];
        yield 'min-resolution x unit' => ['(min-resolution: 2x)', true, (new MediaContext())->withCellSize(new CellSize(16, 32))];
        yield 'max-resolution dpi' => ['(max-resolution: 96dpi)', true];
        yield 'min-resolution dpcm' => ['(min-resolution: 37dpcm)', true];
        yield 'invalid resolution' => ['(resolution: 2)', false];
        yield 'orientation landscape' => ['(orientation: landscape)', true];
        yield 'orientation portrait' => ['(orientation: portrait)', true, new MediaContext(10, 40)];
        yield 'aspect-ratio' => ['(min-aspect-ratio: 3/2)', true];
        yield 'aspect-ratio too wide' => ['(min-aspect-ratio: 3)', false];
        yield 'hover none' => ['(hover: none)', true];
        yield 'hover hover' => ['(hover: hover)', false];
        yield 'pointer none' => ['(pointer: none)', true];
        yield 'hover with a mouse' => ['(hover: hover)', true, new MediaContext(pointer: true)];
        yield 'any-hover with a mouse' => ['(any-hover: hover)', true, new MediaContext(pointer: true)];
        yield 'pointer fine with a mouse' => ['(pointer: fine)', true, new MediaContext(pointer: true)];
        yield 'pointer never coarse' => ['(pointer: coarse)', false, new MediaContext(pointer: true)];
        yield 'hover boolean' => ['(hover)', false];
        yield 'color with 256 colors' => ['(min-color: 3)', false, new MediaContext(colors: 256)];
        yield 'color with 16 colors' => ['(color: 1)', true, new MediaContext(colors: 16)];
        yield 'color-index with 256 colors' => ['(min-color-index: 256)', true, new MediaContext(colors: 256)];
        yield 'color-index with true colors' => ['(color-index)', false];
        yield 'color-gamut' => ['(color-gamut: srgb)', true];
        yield 'color-gamut p3' => ['(color-gamut: p3)', false];
        yield 'dynamic-range' => ['(dynamic-range: high)', false];
        yield 'inverted-colors' => ['(inverted-colors: none)', true];
        yield 'reduced transparency' => ['(prefers-reduced-transparency: reduce)', false];
        yield 'update' => ['(update: fast)', true];
        yield 'scripting none' => ['(scripting: none)', true];
        yield 'scripting enabled' => ['(scripting: enabled)', false];
        yield 'scripting boolean' => ['(scripting)', false];
        yield 'overflow-block' => ['(overflow-block: scroll)', true];
        yield 'overflow-inline boolean' => ['(overflow-inline)', true];
        yield 'overflow-inline none' => ['(overflow-inline: none)', false];
        yield 'device-width' => ['(device-width: 80ch)', true, new MediaContext(columns: 80)];
        yield 'max-device-width' => ['(max-device-width: 60ch)', false, new MediaContext(columns: 80)];
        yield 'device-height range' => ['(device-height >= 24)', true, new MediaContext(rows: 24)];
        yield 'device-aspect-ratio' => ['(min-device-aspect-ratio: 1)', true, new MediaContext(columns: 80, rows: 24)];
        yield 'viewport segments' => ['(horizontal-viewport-segments: 1) and (vertical-viewport-segments: 1)', true];
        yield 'two viewport segments' => ['(horizontal-viewport-segments: 2)', false];
        yield 'device-posture' => ['(device-posture: continuous)', true];
        yield 'device-posture folded' => ['(device-posture: folded)', false];
        yield 'environment-blending' => ['(environment-blending: opaque)', true];
        yield 'environment-blending boolean' => ['(environment-blending)', false];
        yield 'density regular' => ['(tui-density: regular)', true];
        yield 'density condensed' => ['(tui-density: condensed)', true, new MediaContext(density: Density::Condensed)];
        yield 'density as boolean' => ['(tui-density)', false];
    }

    public function testMinPrefixNeedsAValue()
    {
        $this->expectException(InvalidArgumentException::class);

        MediaQuery::parse('(min-width)');
    }

    public function testSource()
    {
        $this->assertSame('(min-width: 1)', MediaQuery::parse('(min-width: 1)')->source);
    }

    public function testPixelsUseTheCellSize()
    {
        $query = MediaQuery::parse('(min-width: 960px)');

        // 120 columns of 8px at 1x
        $this->assertTrue($query->matches(new MediaContext(120)));
        $this->assertFalse($query->matches(new MediaContext(119)));
        // Same physical size at 2x: cells are 16 device pixels wide
        $this->assertTrue($query->matches((new MediaContext(120))->withCellSize(new CellSize(16, 32))));
    }

    public function testSupport()
    {
        $this->assertArrayHasKey('css.at-rules.media.prefers-color-scheme', MediaQuery::getSupport());
    }

    public function testUsesBlockAxis()
    {
        $this->assertFalse(MediaQuery::parse('(min-width: 10)')->usesBlockAxis());
        $this->assertFalse(MediaQuery::parse('(inline-size > 10)')->usesBlockAxis());
        $this->assertTrue(MediaQuery::parse('(min-width: 10) and (min-height: 3)')->usesBlockAxis());
        $this->assertTrue(MediaQuery::parse('(block-size < 3)')->usesBlockAxis());
        $this->assertTrue(MediaQuery::parse('(orientation: portrait)')->usesBlockAxis());
    }

    public function testCustomMedia()
    {
        $custom = ['--narrow' => '(max-width: 40)', '--short' => '(height < 10)', '--small' => '(--narrow), (--short)', '--on' => 'true', '--off' => 'false', '--loop' => '(--loop)'];
        $narrow = new MediaContext(30, 24);
        $wide = new MediaContext(100, 24);
        $short = new MediaContext(100, 5);

        $this->assertTrue(MediaQuery::parse('(--narrow)', $custom)->matches($narrow));
        $this->assertFalse(MediaQuery::parse('(--narrow)', $custom)->matches($wide));
        $this->assertTrue(MediaQuery::parse('screen and (--narrow) and (prefers-color-scheme: dark)', $custom)->matches($narrow));
        $this->assertTrue(MediaQuery::parse('not (--narrow)', $custom)->matches($wide));
        $this->assertTrue(MediaQuery::parse('(--small)', $custom)->matches($short), 'A custom media query can be a list using others.');
        $this->assertFalse(MediaQuery::parse('(--small)', $custom)->matches($wide));
        $this->assertTrue(MediaQuery::parse('(--on)', $custom)->matches($wide));
        $this->assertFalse(MediaQuery::parse('(--off)', $custom)->matches($wide));
        $this->assertFalse(MediaQuery::parse('(--undefined)', $custom)->matches($wide));
        $this->assertFalse(MediaQuery::parse('(--loop)', $custom)->matches($wide));
        $this->assertTrue(MediaQuery::parse('(--short)', $custom)->usesBlockAxis());
        $this->assertFalse(MediaQuery::parse('(--narrow)', $custom)->usesBlockAxis());
    }

    public function testScreenAndNotAnotherMediaTypeAreUnconditional()
    {
        $this->assertTrue(MediaQuery::parse('screen')->isUnconditional());
        $this->assertTrue(MediaQuery::parse('not print')->isUnconditional());
        $this->assertFalse(MediaQuery::parse('print')->isUnconditional());
        $this->assertFalse(MediaQuery::parse('not screen')->isUnconditional());
        $this->assertFalse(MediaQuery::parse('(min-width: 40ch)')->isUnconditional());
        // From a number of columns: every screen at least that wide
        $this->assertTrue(MediaQuery::parse('(min-width: 40ch)')->isUnconditional(80));
        $this->assertTrue(MediaQuery::parse('screen and (width >= 80ch)')->isUnconditional(80));
        $this->assertFalse(MediaQuery::parse('(width > 80ch)')->isUnconditional(80));
        // In columns unrounded: 643px is more than the 640px of 80 columns
        $this->assertFalse(MediaQuery::parse('(min-width: 643px)')->isUnconditional(80));
        $this->assertFalse(MediaQuery::parse('(min-width: 40ch) and (max-width: 90ch)')->isUnconditional(80));
        $this->assertFalse(MediaQuery::parse('not all and (min-width: 40ch)')->isUnconditional(80));
    }
}
