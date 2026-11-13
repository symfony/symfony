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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\CellSize;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\MediaContext;

final class MediaContextTest extends TestCase
{
    public function testDefaults()
    {
        $media = new MediaContext();

        $this->assertSame(80, $media->columns);
        $this->assertSame(24, $media->rows);
        $this->assertSame(ColorScheme::Dark, $media->colorScheme);
        $this->assertSame(Density::Regular, $media->density);
        $this->assertFalse($media->highContrast);
        $this->assertFalse($media->forcedColors);
        $this->assertFalse($media->reducedMotion);
        $this->assertNull($media->cellSize);
        $this->assertEquals(CellSize::default(), $media->getCellSize());
        $this->assertSame(1.0, $media->dppx());
    }

    public function testFromEnvironment()
    {
        $this->assertTrue(MediaContext::fromEnvironment(['NO_COLOR' => '1'])->forcedColors);
        $this->assertFalse(MediaContext::fromEnvironment(['NO_COLOR' => '', 'COLORFGBG' => ''])->forcedColors);
        $this->assertSame(ColorScheme::Light, MediaContext::fromEnvironment(['COLORFGBG' => '0;15', 'NO_COLOR' => ''])->colorScheme);
        $this->assertSame(ColorScheme::Light, MediaContext::fromEnvironment(['COLORFGBG' => '0;default;7', 'NO_COLOR' => ''])->colorScheme);
        $this->assertSame(ColorScheme::Dark, MediaContext::fromEnvironment(['COLORFGBG' => '15;0', 'NO_COLOR' => ''])->colorScheme);
        $this->assertSame(ColorScheme::Dark, MediaContext::fromEnvironment(['COLORFGBG' => '', 'NO_COLOR' => ''])->colorScheme);

        $env = ['NO_COLOR' => '', 'COLORFGBG' => ''];
        $this->assertSame(MediaContext::TRUE_COLORS, MediaContext::fromEnvironment($env + ['COLORTERM' => 'truecolor', 'TERM' => 'xterm'])->colors);
        $this->assertSame(256, MediaContext::fromEnvironment($env + ['COLORTERM' => '', 'TERM' => 'xterm-256color'])->colors);
        $this->assertSame(16, MediaContext::fromEnvironment($env + ['COLORTERM' => '', 'TERM' => 'linux'])->colors);
        $this->assertSame(MediaContext::TRUE_COLORS, MediaContext::fromEnvironment($env + ['COLORTERM' => '', 'TERM' => 'xterm-kitty'])->colors, 'Unknown terminals are assumed modern.');
    }

    public function testWithers()
    {
        $media = new MediaContext();

        $this->assertSame($media, $media->withSize(80, 24), 'An unchanged size returns the same context.');
        $sized = $media->withSize(100, 30);
        $this->assertSame([100, 30], [$sized->columns, $sized->rows]);
        $this->assertSame([80, 24], [$media->columns, $media->rows]);

        $all = $media->withColorScheme(ColorScheme::Light)->withDensity(Density::Condensed)->withHighContrast()->withForcedColors()->withReducedMotion()->withCellSize(new CellSize(16, 32))->withSize(10, 5);
        $this->assertSame(ColorScheme::Light, $all->colorScheme);
        $this->assertSame(Density::Condensed, $all->density);
        $this->assertTrue($all->highContrast);
        $this->assertTrue($all->forcedColors);
        $this->assertTrue($all->reducedMotion);
        $this->assertSame(2.0, $all->dppx());
        $this->assertSame([10, 5], [$all->columns, $all->rows]);
        $this->assertNull($all->withCellSize(null)->cellSize);
        $this->assertFalse($all->withHighContrast(false)->highContrast);

        $pointer = $all->withPointer()->withColors(256);
        $this->assertTrue($pointer->pointer);
        $this->assertSame(256, $pointer->colors);
        $this->assertTrue($pointer->withCellSize(null)->pointer, 'Changing the cell size keeps the other properties.');
        $this->assertSame(256, $pointer->withCellSize(null)->colors);
    }

    public function testKeyChangesWithEveryProperty()
    {
        $media = new MediaContext();
        $keys = [
            $media->getKey(),
            $media->withSize(81, 24)->getKey(),
            $media->withSize(80, 25)->getKey(),
            $media->withColorScheme(ColorScheme::Light)->getKey(),
            $media->withDensity(Density::Condensed)->getKey(),
            $media->withHighContrast()->getKey(),
            $media->withForcedColors()->getKey(),
            $media->withReducedMotion()->getKey(),
            $media->withCellSize(new CellSize(8, 16))->getKey(),
            $media->withCellSize(new CellSize(9, 16))->getKey(),
            $media->withPointer()->getKey(),
            $media->withColors(256)->getKey(),
        ];

        $this->assertCount(\count($keys), array_unique($keys));
        $this->assertSame($media->getKey(), (new MediaContext())->getKey());
    }

    public function testCellSize()
    {
        $this->assertSame(2.0, (new CellSize(16, 32))->dppx());
        $this->assertSame(1.5, (new CellSize(12, 24))->dppx());

        $this->expectException(InvalidArgumentException::class);
        new CellSize(0, 16);
    }
}
