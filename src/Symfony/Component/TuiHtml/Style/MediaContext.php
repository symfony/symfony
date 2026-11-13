<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

/**
 * Describes the terminal and the user preferences that media queries test.
 *
 * The terminal size is updated by the renderer on each frame; everything
 * else is set by the application, for instance from its settings.
 *
 * @experimental
 */
final class MediaContext
{
    /**
     * @param CellSize|null $cellSize   The pixel size of a cell, null when the terminal did not tell
     * @param bool          $textSizing Whether the terminal draws text larger, with the text sizing protocol of kitty (OSC 66), for font-size
     */
    /** 24-bit colors, the default */
    public const TRUE_COLORS = 16777216;

    public function __construct(public readonly int $columns = 80, public readonly int $rows = 24, public readonly ColorScheme $colorScheme = ColorScheme::Dark, public readonly Density $density = Density::Regular, public readonly bool $highContrast = false, public readonly bool $forcedColors = false, public readonly bool $reducedMotion = false, public readonly ?CellSize $cellSize = null, public readonly bool $pointer = false, public readonly int $colors = self::TRUE_COLORS, public readonly bool $textSizing = false)
    {
    }

    /**
     * Creates a context from the conventional environment variables.
     *
     * NO_COLOR (https://no-color.org) forces colors off, and COLORFGBG, set
     * by several terminals, tells whether the background is light.
     *
     * @param array<string, string|false> $env
     */
    public static function fromEnvironment(?array $env = null): self
    {
        $get = static fn (string $name): string => (string) ($env[$name] ?? getenv($name));
        $scheme = ColorScheme::Dark;
        if ('' !== $colorFgBg = $get('COLORFGBG')) {
            $background = (int) substr($colorFgBg, (int) strrpos($colorFgBg, ';') + 1);
            if (7 === $background || 15 === $background) {
                $scheme = ColorScheme::Light;
            }
        }
        // COLORTERM says true colors; TERM says 256 colors; a terminal saying neither may still have them
        $colors = self::TRUE_COLORS;
        if (!preg_match('/^(?:truecolor|24bit)$/i', $get('COLORTERM'))) {
            $term = $get('TERM');
            if (str_contains($term, '256color')) {
                $colors = 256;
            } elseif (\in_array($term, ['linux', 'xterm', 'xterm-color', 'vt100', 'vt220', 'screen', 'ansi', 'cygwin'], true)) {
                $colors = 16;
            }
        }

        return (new self())->withColorScheme($scheme)->withForcedColors('' !== $get('NO_COLOR'))->withColors($colors);
    }

    public function withSize(int $columns, int $rows): self
    {
        if ($columns === $this->columns && $rows === $this->rows) {
            return $this;
        }

        return $this->with($columns, $rows);
    }

    public function withColorScheme(ColorScheme $colorScheme): self
    {
        return $this->with(null, null, $colorScheme);
    }

    public function withDensity(Density $density): self
    {
        return $this->with(null, null, null, $density);
    }

    public function withHighContrast(bool $highContrast = true): self
    {
        return $this->with(null, null, null, null, $highContrast);
    }

    public function withForcedColors(bool $forcedColors = true): self
    {
        return $this->with(null, null, null, null, null, $forcedColors);
    }

    public function withReducedMotion(bool $reducedMotion = true): self
    {
        return $this->with(null, null, null, null, null, null, $reducedMotion);
    }

    public function withCellSize(?CellSize $cellSize): self
    {
        return new self($this->columns, $this->rows, $this->colorScheme, $this->density, $this->highContrast, $this->forcedColors, $this->reducedMotion, $cellSize, $this->pointer, $this->colors, $this->textSizing);
    }

    /**
     * Tells that the terminal draws text larger with the text sizing protocol of kitty (OSC 66), which font-size
     * then uses; the application knows it, like when it runs in kitty 0.40 or later: no terminal is asked.
     */
    public function withTextSizing(bool $textSizing = true): self
    {
        return $this->with(null, null, null, null, null, null, null, null, null, $textSizing);
    }

    /**
     * Tells whether the application tracks the mouse: then (hover: hover) and (pointer: fine) match.
     */
    public function withPointer(bool $pointer = true): self
    {
        return $this->with(null, null, null, null, null, null, null, $pointer);
    }

    /**
     * Sets the number of colors of the terminal: 16, 256, or MediaContext::TRUE_COLORS.
     */
    public function withColors(int $colors): self
    {
        return $this->with(null, null, null, null, null, null, null, null, max(2, $colors));
    }

    /**
     * Returns the number of device pixels per CSS pixel.
     */
    public function dppx(): float
    {
        return $this->getCellSize()->dppx();
    }

    /**
     * Returns the cell size, or a typical 8x16 cell when it is unknown.
     */
    public function getCellSize(): CellSize
    {
        return $this->cellSize ?? CellSize::default();
    }

    /**
     * Returns a key that changes whenever a media query could match differently.
     */
    public function getKey(): string
    {
        return implode(':', [$this->columns, $this->rows, $this->colorScheme->value, $this->density->value, (int) $this->highContrast, (int) $this->forcedColors, (int) $this->reducedMotion, $this->cellSize?->width, $this->cellSize?->height, (int) $this->pointer, $this->colors, (int) $this->textSizing]);
    }

    private function with(?int $columns = null, ?int $rows = null, ?ColorScheme $colorScheme = null, ?Density $density = null, ?bool $highContrast = null, ?bool $forcedColors = null, ?bool $reducedMotion = null, ?bool $pointer = null, ?int $colors = null, ?bool $textSizing = null): self
    {
        return new self($columns ?? $this->columns, $rows ?? $this->rows, $colorScheme ?? $this->colorScheme, $density ?? $this->density, $highContrast ?? $this->highContrast, $forcedColors ?? $this->forcedColors, $reducedMotion ?? $this->reducedMotion, $this->cellSize, $pointer ?? $this->pointer, $colors ?? $this->colors, $textSizing ?? $this->textSizing);
    }
}
