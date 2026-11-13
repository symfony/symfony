<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Compatibility\Support;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\MediaContext;

/**
 * A media query list, as found in @media rules and media attributes.
 *
 * Queries are evaluated against a {@see MediaContext}. Following the CSS
 * specification, a query using an unknown feature never matches.
 *
 * @experimental
 */
final class MediaQuery
{
    private const FEATURES = [
        'width', 'height', 'aspect-ratio', 'orientation', 'inline-size', 'block-size',
        'prefers-color-scheme', 'prefers-contrast', 'prefers-reduced-motion', 'forced-colors',
        'color', 'color-index', 'color-gamut', 'monochrome', 'grid', 'resolution',
        'hover', 'any-hover', 'pointer', 'any-pointer', 'dynamic-range', 'video-dynamic-range',
        'inverted-colors', 'prefers-reduced-transparency', 'prefers-reduced-data', 'update', 'tui-density',
        'scripting', 'overflow-block', 'overflow-inline', 'device-width', 'device-height', 'device-aspect-ratio',
        'horizontal-viewport-segments', 'vertical-viewport-segments', 'device-posture', 'environment-blending',
    ];

    /**
     * @param list<list<array{string, string, string|self|null}>|false> $queries Each query is a list of [feature, operator, value] conditions, a custom media query being the value of a "--" feature; false never matches
     * @param list<bool>                                                $negated
     */
    private function __construct(
        public readonly string $source,
        private readonly array $queries,
        private readonly array $negated,
    ) {
    }

    /**
     * @param array<string, string> $customMedia The queries of the @custom-media rules, by name, which (--name) tests
     */
    public static function parse(string $query, array $customMedia = []): self
    {
        return self::parseList($query, $customMedia, []);
    }

    /**
     * @param array<string, string> $customMedia
     * @param list<string>          $resolving   The custom media being parsed, to stop cycles
     */
    private static function parseList(string $query, array $customMedia, array $resolving): self
    {
        $queries = [];
        $negated = [];

        foreach (Selector::splitTopLevel($query) as $single) {
            $single = strtolower(trim($single));
            $not = false;
            if (str_starts_with($single, 'not ')) {
                $not = true;
                $single = substr($single, 4);
            } elseif (str_starts_with($single, 'only ')) {
                $single = substr($single, 5);
            }

            $conditions = [];
            foreach (preg_split('/\s+and\s+/', trim($single)) ?: [] as $part) {
                $part = trim($part);
                if ('all' === $part || 'screen' === $part || 'tty' === $part || '' === $part) {
                    continue;
                }
                if (!str_starts_with($part, '(') || !str_ends_with($part, ')')) {
                    // Another media type (print, speech) or an invalid query
                    $conditions = false;
                    break;
                }
                $parsed = preg_match('/^\(\s*(--[\w-]+)\s*\)$/', $part, $m)
                    ? self::customCondition($m[1], $customMedia, $resolving)
                    : self::parseConditions(substr($part, 1, -1));
                if (null === $parsed) {
                    $conditions = false;
                    break;
                }
                array_push($conditions, ...$parsed);
            }

            $queries[] = $conditions;
            $negated[] = $not;
        }

        return new self($query, $queries, $negated);
    }

    /**
     * Whether the query tests the block axis (height, block-size, aspect-ratio, orientation), which an inline-size container cannot answer.
     */
    public function usesBlockAxis(): bool
    {
        foreach ($this->queries as $conditions) {
            foreach ($conditions ?: [] as [$feature, , $value]) {
                if (\in_array($feature, ['height', 'block-size', 'aspect-ratio', 'orientation'], true) || ($value instanceof self && $value->usesBlockAxis())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the query matches in every context, like screen or all: a media type the terminal always is; or, given a
     * number of columns, on every screen at least that wide, like (min-width: 601px) from 80 columns.
     */
    public function isUnconditional(int $columns = 0): bool
    {
        foreach ($this->queries as $i => $conditions) {
            // Or not another media type, like not print
            if (([] === $conditions && !$this->negated[$i]) || (false === $conditions && $this->negated[$i])) {
                return true;
            }
            if ($columns > 0 && false !== $conditions && !$this->negated[$i] && array_all($conditions, static fn (array $condition): bool => \in_array($condition[0], ['width', 'inline-size'], true)
                && \in_array($condition[1], ['>=', '>'], true) && \is_string($condition[2])
                && null !== ($cells = Length::toColumns($condition[2], new MediaContext())) && ('>' === $condition[1] ? $cells < $columns : $cells <= $columns))) {
                return true;
            }
        }

        return false;
    }

    public function matches(MediaContext $media): bool
    {
        foreach ($this->queries as $i => $conditions) {
            $matches = false !== $conditions;
            foreach ($conditions ?: [] as [$feature, $operator, $value]) {
                if (!($value instanceof self ? $value->matches($media) : self::evaluate($feature, $operator, $value, $media))) {
                    $matches = false;
                    break;
                }
            }

            if ($matches !== $this->negated[$i]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, Support>
     */
    public static function getSupport(): array
    {
        $prefix = 'css.at-rules.media.';

        return [
            'css.at-rules.media' => Support::supported('Media types all, screen and tty match; others never do.'),
            'css.at-rules.media.range-syntax' => Support::supported(),
            $prefix.'width' => Support::supported('In columns; px are converted with the cell pixel size.'),
            $prefix.'height' => Support::supported('In rows; px are converted with the cell pixel size.'),
            $prefix.'aspect-ratio' => Support::supported('Uses the pixel size of the terminal.'),
            $prefix.'orientation' => Support::supported('Uses the pixel size of the terminal.'),
            $prefix.'prefers-color-scheme' => Support::supported('From MediaContext: set by the application, COLORFGBG by default.'),
            $prefix.'prefers-contrast' => Support::partial('"more" and "no-preference", from MediaContext.'),
            $prefix.'prefers-reduced-motion' => Support::supported('From MediaContext.'),
            $prefix.'forced-colors' => Support::supported('Active when NO_COLOR is set or colors are disabled.'),
            $prefix.'color' => Support::supported('Bits per component: 8 with true colors, 2 with 256 colors, 1 with 16, 0 when forced off; from MediaContext, COLORTERM and TERM by default.'),
            $prefix.'color-index' => Support::supported('256 or 16 with a palette, 0 with true colors.'),
            $prefix.'color-gamut' => Support::supported('srgb, unless colors are forced off.'),
            $prefix.'dynamic-range' => Support::supported('Always standard.'),
            $prefix.'video-dynamic-range' => Support::supported('Always standard.'),
            $prefix.'inverted-colors' => Support::supported('Always none.'),
            $prefix.'prefers-reduced-transparency' => Support::supported('Always no-preference: translucent colors are blended, none made opaque.'),
            $prefix.'prefers-reduced-data' => Support::supported('Always no-preference.'),
            $prefix.'update' => Support::supported('Always fast.'),
            $prefix.'monochrome' => Support::supported('Matches when colors are forced off.'),
            $prefix.'grid' => Support::supported('Always 1: a terminal is a grid device.'),
            $prefix.'resolution' => Support::supported('dppx, x, dpi and dpcm, from the cell pixel size.'),
            $prefix.'hover' => Support::supported('"hover" when the application tracks the mouse (MediaContext::withPointer()), "none" otherwise.'),
            $prefix.'any-hover' => Support::supported('Like hover.'),
            $prefix.'pointer' => Support::supported('"fine" when the application tracks the mouse, "none" otherwise; never "coarse".'),
            $prefix.'any-pointer' => Support::supported('Like pointer.'),
            $prefix.'tui-density' => Support::supported('Tui extension: "regular" or "condensed", from MediaContext.'),
            $prefix.'scripting' => Support::supported('Always none: no script runs.'),
            $prefix.'overflow-block' => Support::supported('Always scroll.'),
            $prefix.'overflow-inline' => Support::supported('Always scroll.'),
            $prefix.'device-width' => Support::supported('The terminal is the device: like width.'),
            $prefix.'device-height' => Support::supported('The terminal is the device: like height.'),
            $prefix.'device-aspect-ratio' => Support::supported('The terminal is the device: like aspect-ratio.'),
            $prefix.'horizontal-viewport-segments' => Support::supported('Always 1.'),
            $prefix.'vertical-viewport-segments' => Support::supported('Always 1.'),
            $prefix.'device-posture' => Support::supported('Always continuous.'),
            $prefix.'environment-blending' => Support::supported('Always opaque.'),
        ];
    }

    /**
     * A custom media query, (--name), true where its query matches; an undefined one, or one defined by itself, is invalid.
     *
     * @param array<string, string> $customMedia
     * @param list<string>          $resolving
     *
     * @return list<array{string, string, self}>|null
     */
    private static function customCondition(string $name, array $customMedia, array $resolving): ?array
    {
        $query = $customMedia[$name] ?? null;
        if (null === $query || \in_array($name, $resolving, true)) {
            return null;
        }
        $query = match (strtolower(trim($query))) {
            'true' => 'all',
            'false' => 'not all',
            default => $query,
        };

        return [[$name, 'custom', self::parseList($query, $customMedia, [...$resolving, $name])]];
    }

    /**
     * @return list<array{string, string, ?string}>|null
     */
    private static function parseConditions(string $condition): ?array
    {
        // Double range syntax: (40 < width <= 120)
        if (preg_match('/^(.+?)\s*(<=|<|>=|>)\s*([a-z-]+)\s*(<=|<|>=|>)\s*(.+)$/', trim($condition), $m) && \in_array($m[3], self::FEATURES, true)) {
            $first = self::parseCondition($m[1].$m[2].$m[3]);
            $second = self::parseCondition($m[3].$m[4].$m[5]);

            return null === $first || null === $second ? null : [$first, $second];
        }

        $single = self::parseCondition($condition);

        return null === $single ? null : [$single];
    }

    /**
     * @return array{string, string, ?string}|null
     */
    private static function parseCondition(string $condition): ?array
    {
        $condition = trim($condition);

        // Range syntax: (width >= 80), (80 <= width)
        if (preg_match('/^([a-z-]+)\s*(<=|>=|<|>|=)\s*(.+)$/', $condition, $m) && \in_array($m[1], self::FEATURES, true)) {
            return [$m[1], $m[2], trim($m[3])];
        }
        if (preg_match('/^(.+?)\s*(<=|>=|<|>|=)\s*([a-z-]+)$/', $condition, $m) && \in_array($m[3], self::FEATURES, true)) {
            $flipped = ['<' => '>', '<=' => '>=', '>' => '<', '>=' => '<=', '=' => '='];

            return [$m[3], $flipped[$m[2]], trim($m[1])];
        }

        if (preg_match('/^(min-|max-)?([a-z-]+)\s*(?::\s*(.+))?$/', $condition, $m)) {
            $feature = $m[2];
            if (!\in_array($feature, self::FEATURES, true)) {
                return null;
            }
            $value = isset($m[3]) ? trim($m[3]) : null;
            if ('' !== $m[1] && null === $value) {
                throw new InvalidArgumentException(\sprintf('The "%s%s" media feature needs a value.', $m[1], $feature));
            }

            return [$feature, match ($m[1]) {
                'min-' => '>=',
                'max-' => '<=',
                default => null === $value ? 'bool' : '=',
            }, $value];
        }

        return null;
    }

    private static function evaluate(string $feature, string $operator, ?string $value, MediaContext $media): bool
    {
        $cell = $media->getCellSize();

        return match ($feature) {
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

    /**
     * Bits per color component: 8 with true colors, fewer with a palette, 0 when colors are forced off.
     */
    private static function bitsPerColor(MediaContext $media): int
    {
        return match (true) {
            $media->forcedColors => 0,
            $media->colors >= MediaContext::TRUE_COLORS => 8,
            $media->colors >= 256 => 2,
            default => 1,
        };
    }

    private static function compare(int|float $actual, string $operator, int|float|null $expected): bool
    {
        if ('bool' === $operator) {
            return 0 != $actual;
        }
        if (null === $expected) {
            return false;
        }

        return match ($operator) {
            '=' => abs($actual - $expected) < 0.001,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected + 0.001,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected - 0.001,
            default => false,
        };
    }

    private static function ratio(string $value): ?float
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(?:\/\s*(\d+(?:\.\d+)?))?$/', $value, $m)) {
            return null;
        }

        return (float) $m[1] / (float) ($m[2] ?? 1);
    }

    /**
     * A resolution in dppx, like 2x or 192dpi; null when it is not one.
     *
     * @internal
     */
    public static function resolution(string $value): ?float
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)(dppx|x|dpi|dpcm)$/', strtolower(trim($value)), $m)) {
            return null;
        }

        return match ($m[2]) {
            'dppx', 'x' => (float) $m[1],
            'dpi' => (float) $m[1] / 96,
            'dpcm' => (float) $m[1] * 2.54 / 96,
        };
    }
}
