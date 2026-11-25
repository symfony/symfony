<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Css;

use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Compatibility\Support;
use Symfony\Component\HtmlRenderer\Css\MediaQuery as NativeMediaQuery;

/** @internal */
final class MediaQuery extends NativeMediaQuery
{
    public function isUnconditional(int $columns = 0): bool
    {
        return self::isQueryUnconditional($this, $columns);
    }

    public static function isQueryUnconditional(NativeMediaQuery $query, int $columns = 0): bool
    {
        return true === $query->certainty(static function (string $feature, string $operator, ?string $value) use ($columns): ?bool {
            if ('media-type' === $feature) {
                return \in_array($value, ['all', 'screen', 'tty'], true);
            }

            return $columns > 0 && \in_array($feature, ['width', 'inline-size'], true) && \in_array($operator, ['>=', '>'], true) && null !== $value && null !== ($cells = Length::toColumns($value, new MediaContext())) && ('>' === $operator ? $cells < $columns : $cells <= $columns) ? true : null;
        });
    }

    public function matches(MediaContext $media): bool
    {
        return self::matchesQuery($this, $media);
    }

    public static function matchesQuery(NativeMediaQuery $query, MediaContext $media): bool
    {
        return $query->evaluate(static fn (string $feature, string $operator, ?string $value): bool => self::matchFeature($feature, $operator, $value, $media));
    }

    public static function getSupport(): array
    {
        $prefix = 'css.at-rules.media.';

        return ['css.at-rules.media' => Support::supported('Media types all, screen and tty match; others never do.'), 'css.at-rules.media.range-syntax' => Support::supported(), $prefix.'width' => Support::supported('In columns; px are converted with the cell pixel size.'), $prefix.'height' => Support::supported('In rows; px are converted with the cell pixel size.'), $prefix.'aspect-ratio' => Support::supported('Uses the pixel size of the terminal.'), $prefix.'orientation' => Support::supported('Uses the pixel size of the terminal.'), $prefix.'prefers-color-scheme' => Support::supported('From MediaContext: set by the application, COLORFGBG by default.'), $prefix.'prefers-contrast' => Support::partial('"more" and "no-preference", from MediaContext.'), $prefix.'prefers-reduced-motion' => Support::supported('From MediaContext.'), $prefix.'forced-colors' => Support::supported('Active when NO_COLOR is set or colors are disabled.'), $prefix.'color' => Support::supported('Bits per component: 8 with true colors, 2 with 256 colors, 1 with 16, 0 when forced off; from MediaContext, COLORTERM and TERM by default.'), $prefix.'color-index' => Support::supported('256 or 16 with a palette, 0 with true colors.'), $prefix.'color-gamut' => Support::supported('srgb, unless colors are forced off.'), $prefix.'dynamic-range' => Support::supported('Always standard.'), $prefix.'video-dynamic-range' => Support::supported('Always standard.'), $prefix.'inverted-colors' => Support::supported('Always none.'), $prefix.'prefers-reduced-transparency' => Support::supported('Always no-preference: translucent colors are blended, none made opaque.'), $prefix.'prefers-reduced-data' => Support::supported('Always no-preference.'), $prefix.'update' => Support::supported('Always fast.'), $prefix.'monochrome' => Support::supported('Matches when colors are forced off.'), $prefix.'grid' => Support::supported('Always 1: a terminal is a grid device.'), $prefix.'resolution' => Support::supported('dppx, x, dpi and dpcm, from the cell pixel size.'), $prefix.'hover' => Support::supported('"hover" when the application tracks the mouse (MediaContext::withPointer()), "none" otherwise.'), $prefix.'any-hover' => Support::supported('Like hover.'), $prefix.'pointer' => Support::supported('"fine" when the application tracks the mouse, "none" otherwise; never "coarse".'), $prefix.'any-pointer' => Support::supported('Like pointer.'), $prefix.'tui-density' => Support::supported('Tui extension: "regular" or "condensed", from MediaContext.'), $prefix.'scripting' => Support::supported('Always none: no script runs.'), $prefix.'overflow-block' => Support::supported('Always scroll.'), $prefix.'overflow-inline' => Support::supported('Always scroll.'), $prefix.'device-width' => Support::supported('The terminal is the device: like width.'), $prefix.'device-height' => Support::supported('The terminal is the device: like height.'), $prefix.'device-aspect-ratio' => Support::supported('The terminal is the device: like aspect-ratio.'), $prefix.'horizontal-viewport-segments' => Support::supported('Always 1.'), $prefix.'vertical-viewport-segments' => Support::supported('Always 1.'), $prefix.'device-posture' => Support::supported('Always continuous.'), $prefix.'environment-blending' => Support::supported('Always opaque.')];
    }

    private static function matchFeature(string $feature, string $operator, ?string $value, MediaContext $media): bool
    {
        $cell = $media->getCellSize();

        return match ($feature) {
            'media-type' => \in_array($value, ['all', 'screen', 'tty'], true),
            // In a horizontal writing mode, the inline size is the width and the block size the height
            'width', 'inline-size', 'device-width' => self::compare($media->columns, $operator, null === $value ? null : Length::toCells($value, $media)),
            'height', 'block-size', 'device-height' => self::compare($media->rows, $operator, null === $value ? null : Length::toCells($value, $media, true)),
            'aspect-ratio', 'device-aspect-ratio' => self::compare($media->columns * $cell->width / max(1, $media->rows * $cell->height), $operator, null === $value ? null : self::ratio($value)),
            'orientation' => 'bool' === $operator || $value === ($media->rows * $cell->height > $media->columns * $cell->width ? 'portrait' : 'landscape'),
            'prefers-color-scheme' => 'bool' === $operator || $value === $media->colorScheme->value,
            'prefers-contrast' => 'bool' === $operator ? $media->highContrast : $value === ($media->highContrast ? 'more' : 'no-preference'),
            'prefers-reduced-motion' => 'bool' === $operator ? $media->reducedMotion : $value === ($media->reducedMotion ? 'reduce' : 'no-preference'),
            'forced-colors' => 'bool' === $operator ? $media->forcedColors : $value === ($media->forcedColors ? 'active' : 'none'),
            'color' => self::compare(self::bitsPerColor($media), $operator, null === $value ? null : (int) $value),
            'color-index' => self::compare($media->forcedColors || MediaContext::TRUE_COLORS === $media->colors ? 0 : $media->colors, $operator, null === $value ? null : (int) $value),
            'color-gamut' => !$media->forcedColors && ('bool' === $operator || 'srgb' === $value),
            'dynamic-range', 'video-dynamic-range' => 'bool' === $operator || 'standard' === $value,
            'inverted-colors' => 'bool' !== $operator && 'none' === $value,
            'prefers-reduced-transparency', 'prefers-reduced-data' => 'bool' !== $operator && 'no-preference' === $value,
            'update' => 'bool' === $operator || 'fast' === $value,
            'monochrome' => self::compare($media->forcedColors ? 1 : 0, $operator, null === $value ? null : (int) $value),
            'grid' => self::compare(1, $operator, null === $value ? null : (int) $value),
            'resolution' => self::compare($media->dppx(), $operator, null === $value ? null : self::resolution($value)),
            'hover', 'any-hover' => 'bool' === $operator ? $media->pointer : $value === ($media->pointer ? 'hover' : 'none'),
            'pointer', 'any-pointer' => 'bool' === $operator ? $media->pointer : $value === ($media->pointer ? 'fine' : 'none'),
            'scripting' => 'bool' !== $operator && 'none' === $value,
            'overflow-block', 'overflow-inline' => 'bool' === $operator || 'scroll' === $value,
            'horizontal-viewport-segments', 'vertical-viewport-segments' => self::compare(1, $operator, null === $value ? null : (int) $value),
            'device-posture' => 'bool' !== $operator && 'continuous' === $value,
            'environment-blending' => 'bool' !== $operator && 'opaque' === $value,
            'tui-density' => 'bool' === $operator ? Density::Condensed === $media->density : $value === $media->density->value,
            default => false,
        };
    }

    private static function bitsPerColor(MediaContext $media): int
    {
        return match (true) {
            $media->forcedColors => 0,
            $media->colors >= MediaContext::TRUE_COLORS => 8,
            $media->colors >= 256 => 2,
            default => 1,
        };
    }
}
