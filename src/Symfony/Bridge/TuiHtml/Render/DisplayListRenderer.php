<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Render;

use Symfony\Bridge\TuiHtml\Css\ColorFunction;
use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Internal\BorderJunctions;
use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Internal\TextSize;
use Symfony\Bridge\TuiHtml\Style\Border;
use Symfony\Bridge\TuiHtml\Style\BorderPattern;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\CursorShape;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Style\RasterImage;
use Symfony\Bridge\TuiHtml\Style\Style;
use Symfony\Bridge\TuiHtml\Style\UnderlineStyle;
use Symfony\Bridge\TuiHtml\Style\VectorImage;
use Symfony\Bridge\TuiHtml\Widget\ImageWidget;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;
use Symfony\Component\HtmlRenderer\Paint\Filter;
use Symfony\Component\HtmlRenderer\Paint\GradientGeometry;
use Symfony\Component\HtmlRenderer\Vector\Geometry;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\Bidi;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class DisplayListRenderer
{
    private readonly \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache $backgroundImages;
    private readonly ?ColorDepth $colorDepth;

    public function __construct(private readonly TerminalTextMeasurer $metrics = new TerminalTextMeasurer(), private readonly MediaContext $media = new MediaContext(), ?\Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache $backgroundImages = null)
    {
        $this->backgroundImages = $backgroundImages ?? new \Symfony\Bridge\TuiHtml\Internal\BackgroundImageCache();
        $this->colorDepth = $media->colors < MediaContext::TRUE_COLORS ? new ColorDepth($media->colors) : null;
    }

    public function render(DisplayList $list, int $columns, int $rows): array
    {
        if ($columns < 1 || $rows < 1) {
            return [];
        }
        $buffer = new CellBuffer($columns, $rows, true);
        $clips = [[0, 0, $columns, $rows]];
        $media = $this->media;
        $colorDepth = $this->colorDepth;
        $transforms = [[1.0, 0.0, 0.0, 1.0, 0.0, 0.0]];
        $cursor = null;
        $opacities = [1.0];
        $filterLayers = [];
        foreach ($list as $operation) {
            if ('push-filter' === $operation['type']) {
                $filterLayers[] = [$buffer, new Filter($operation['functions'])];
                $buffer = new CellBuffer($columns, $rows, true, true);
                continue;
            }
            if ('pop-filter' === $operation['type']) {
                if ($filterLayers) {
                    [$parent, $filter] = array_pop($filterLayers);
                    $this->compositeFilter($parent, $buffer, $filter, $media);
                    $buffer = $parent;
                }
                continue;
            }
            if ('push-opacity' === $operation['type']) {
                $opacities[] = $opacities[array_key_last($opacities)] * max(0.0, min(1.0, $operation['opacity']));
                continue;
            }
            if ('pop-opacity' === $operation['type']) {
                if (\count($opacities) > 1) {
                    array_pop($opacities);
                }
                continue;
            }
            if ('push-transform' === $operation['type']) {
                $matrix = $operation['matrix'];
                $current = $transforms[array_key_last($transforms)];
                $translation = abs($matrix[0] - 1) < 1e-9 && abs($matrix[1]) < 1e-9 && abs($matrix[2]) < 1e-9 && abs($matrix[3] - 1) < 1e-9;
                $transforms[] = $translation ? Geometry::multiply($current, $matrix) : $current;
                continue;
            }
            if ('pop-transform' === $operation['type']) {
                if (\count($transforms) > 1) {
                    array_pop($transforms);
                }
                continue;
            }
            $clip = $clips[array_key_last($clips)];
            [$left, $top, $right, $bottom] = $clip;
            if ('pop-clip' === $operation['type']) {
                if (\count($clips) > 1) {
                    array_pop($clips);
                }
                continue;
            }
            if ('scrollbar' === $operation['type']) {
                if ($left < $right && $top < $bottom && $opacities[array_key_last($opacities)] > 0) {
                    $this->scrollbar($buffer, $operation, $transforms[array_key_last($transforms)], $clip, $opacities[array_key_last($opacities)]);
                }

                continue;
            }
            if ('line' === $operation['type']) {
                if ($left < $right && $top < $bottom && $opacities[array_key_last($opacities)] > 0) {
                    $this->line($buffer, $operation, $transforms[array_key_last($transforms)], $clip, $opacities[array_key_last($opacities)]);
                }

                continue;
            }
            if (!isset($operation['x'], $operation['y'])) {
                continue;
            }
            $transform = $transforms[array_key_last($transforms)];
            $operation['x'] += $transform[4];
            $operation['y'] += $transform[5];
            if (isset($operation['source-bounds'])) {
                $operation['source-bounds']['x'] += $transform[4];
                $operation['source-bounds']['y'] += $transform[5];
            }
            $x = (int) floor($operation['x'] / $this->metrics->cellWidth);
            $y = (int) floor($operation['y'] / $this->metrics->cellHeight);
            $endX = (int) ceil(($operation['x'] + ($operation['width'] ?? 0)) / $this->metrics->cellWidth);
            $endY = (int) ceil(($operation['y'] + ($operation['height'] ?? 0)) / $this->metrics->cellHeight);
            if ('push-clip' === $operation['type']) {
                $clipX = $operation['clip-x'] ?? true;
                $clipY = $operation['clip-y'] ?? true;
                $clips[] = [$clipX ? max($left, $x) : $left, $clipY ? max($top, $y) : $top, $clipX ? min($right, $endX) : $right, $clipY ? min($bottom, $endY) : $bottom];
                continue;
            }
            if ($left >= $right || $top >= $bottom || $opacities[array_key_last($opacities)] <= 0) {
                continue;
            }
            $color = Value::color($operation['color'] ?? 'black', $media);
            if (!$color && 'border' === $operation['type'] && isset($operation['sides'])) {
                $color = Color::named('default');
            }
            if (!$color) {
                continue;
            }
            if ($media->forcedColors) {
                $color = Color::named('default');
                if (\in_array($operation['type'], ['image', 'svg', 'rectangle', 'gradient', 'background-image', 'background-group'], true)) {
                    continue;
                }
            }
            if ('background-group' === $operation['type']) {
                $this->backgroundGroup($buffer, $operation, $transform, $clip, $opacities[array_key_last($opacities)]);
                continue;
            }
            if (isset($operation['text-mask'])) {
                $this->textMask($buffer, $operation, $transform, $clip, $opacities[array_key_last($opacities)]);
                continue;
            }
            if ('caret' === $operation['type']) {
                if ($x >= $left && $x < $right && $y >= $top && $y < $bottom) {
                    $cursor = [$x, $y, $operation['shape'] ?? null, $operation['blink'] ?? true, $color];
                }
            } elseif ('gradient' === $operation['type']) {
                $backdrop = Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000');
                for ($row = max($top, $y); $row < min($bottom, $endY); ++$row) {
                    for ($column = max($left, $x); $column < min($right, $endX); ++$column) {
                        $point = [($column + 0.5) * $this->metrics->cellWidth - $operation['x'], ($row + 0.5) * $this->metrics->cellHeight - $operation['y']];
                        if (isset($operation['pattern'])) {
                            $point = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::sample($operation['pattern'], ...$point);
                            if (null === $point) {
                                continue;
                            }
                        }
                        [$rgba, $alpha] = GradientGeometry::sample($operation['gradient'], $point[0], $point[1]);
                        if ($alpha <= 0) {
                            continue;
                        }
                        $paint = ColorFunction::toColor($rgba);
                        if ($alpha < 1) {
                            $under = $buffer->getBackgroundColor($row, $column) ?? $backdrop;
                            $paint = $under->mix($paint, (int) round($alpha * 100));
                            $buffer->tint($row, $column, 1, substr($paint->toBackgroundCode(), 2, -1));
                        } else {
                            $buffer->writeAnsiLines([$paint->toBackgroundCode()." \x1b[0m"], $row, $column);
                        }
                    }
                }
            } elseif ('background-image' === $operation['type']) {
                $this->backgroundImage($buffer, $operation, $clip);
            } elseif (\in_array($operation['type'], ['image', 'svg'], true)) {
                if (($operation['width'] ?? 0) <= 0 || ($operation['height'] ?? 0) <= 0) {
                    continue;
                }
                $imageColumns = max(1, min(1000, (int) ceil($operation['width'] / $this->metrics->cellWidth)));
                $imageRows = max(1, min(1000, (int) ceil($operation['height'] / $this->metrics->cellHeight)));
                $data = $operation['data'];
                if ('svg' === $operation['type']) {
                    $rgb = $color->toRgb();
                    $data = VectorImage::fromString($data)?->toPng((int) ceil($imageColumns * $this->metrics->cellWidth), (int) ceil($imageRows * $this->metrics->cellHeight), [$rgb['r'], $rgb['g'], $rgb['b']]);
                }
                if (null === $data) {
                    continue;
                }
                $image = new ImageWidget($data, '', $imageColumns, $imageRows);
                $image->setBox($imageColumns, $imageRows, 'fill');
                $this->writeLines($buffer, $image->render(new RenderContext($imageColumns, $imageRows)), $x, $y, $clip);
            } elseif ('text' === $operation['type']) {
                $this->text($buffer, $operation, $clip, $color, $opacities[array_key_last($opacities)]);
            } elseif ('rectangle' === $operation['type']) {
                $endX = $x + (int) ceil(($operation['width'] ?? 0) / $this->metrics->cellWidth);
                $endY = $y + (int) ceil(($operation['height'] ?? 0) / $this->metrics->cellHeight);
                $line = $color->toBackgroundCode().str_repeat(' ', max(0, min($right, $endX) - max($left, $x)))."\x1b[0m";
                for ($row = max($top, $y); $row < min($bottom, $endY); ++$row) {
                    $buffer->writeAnsiLines([$line], $row, max($left, $x));
                }
            } elseif ('border' === $operation['type']) {
                $this->writeLines($buffer, $this->borderLines($operation, $endX - $x, $endY - $y, $color, $media), $x, $y, $clip, true);
            }
        }

        $buffer->joinStackedBorders();
        $lines = $buffer->toLines();
        if (!$media->forcedColors && null !== $colorDepth) {
            $lines = array_map($colorDepth->reduce(...), $lines);
        }

        if (null !== $cursor) {
            [$x, $y, $shape, $blink, $color] = $cursor;
            [$before, $after] = AnsiUtils::splitAround($lines[$y], $x, 0);
            $cursorShape = match ($shape) {
                'bar' => CursorShape::Bar, 'underline' => CursorShape::Underline, default => CursorShape::Block,
            };
            $marker = null === $shape ? AnsiUtils::cursorMarker() : TextMarkers::cursor($blink ? $cursorShape : $cursorShape->steady(), $color);
            $lines[$y] = $before.$marker.$after;
        }

        return $lines;
    }

    private function text(CellBuffer $buffer, array $operation, array $clip, Color $color, float $opacity, ?\Closure $sample = null): void
    {
        $media = $this->media;
        $colorDepth = $this->colorDepth;
        [$left, $top, $right, $bottom] = $clip;
        $x = (int) floor($operation['x'] / $this->metrics->cellWidth);
        $y = (int) floor($operation['y'] / $this->metrics->cellHeight);
        $text = StringUtils::stripControlBytes(AnsiUtils::stripAnsiCodes(StringUtils::sanitizeUtf8($operation['text'])));
        if (\in_array($operation['vertical-align'] ?? '', ['super', 'sub'], true)) {
            $text = \Symfony\Bridge\TuiHtml\Internal\ScriptText::convert($text, $operation['vertical-align']);
        }
        $letters = \Symfony\Bridge\TuiHtml\Internal\TerminalSpacing::cells((float) ($operation['letter-spacing'] ?? 0), $this->metrics->cellWidth);
        $words = \Symfony\Bridge\TuiHtml\Internal\TerminalSpacing::cells((float) ($operation['word-spacing'] ?? 0), $this->metrics->cellWidth);
        if ($letters > 0 || $words > 0) {
            $text = \Symfony\Bridge\TuiHtml\Internal\TerminalSpacing::expand($text, $letters, $words)['text'];
        }
        $direction = $operation['direction'] ?? 'ltr';
        $bidi = Bidi::isAvailable();
        $level = 'rtl' === $direction ? 1 : ('auto' === $direction && $bidi ? Bidi::direction($text) ?? 0 : 0);
        if ($bidi && Bidi::needsReordering($text, $level)) {
            $text = Bidi::reorderLine($text, $level);
        }
        $decorations = preg_split('/\s+/', $operation['text-decoration-line'] ?? 'none');
        $style = (new Style())->withColor($color);
        if (($operation['font-weight'] ?? 400) >= 600) {
            $style = $style->withBold();
        }
        if ($opacity < 1 || ($operation['font-weight'] ?? 400) >= 1 && ($operation['font-weight'] ?? 400) <= 300) {
            $style = $style->withDim();
        }
        if (\in_array($operation['font-style'] ?? 'normal', ['italic', 'oblique'], true)) {
            $style = $style->withItalic();
        }
        if (\in_array('underline', $decorations, true)) {
            $underline = match ($operation['text-decoration-style'] ?? 'solid') {
                'double' => UnderlineStyle::Double,
                'wavy' => UnderlineStyle::Curly,
                'dotted' => UnderlineStyle::Dotted,
                'dashed' => UnderlineStyle::Dashed,
                default => UnderlineStyle::Single,
            };
            $underlineColor = $media->forcedColors ? Color::named('default') : Value::color($operation['text-decoration-color'] ?? $operation['color'] ?? 'default', $media);
            if ($underlineColor instanceof Color && $underlineColor->toForegroundCode() === $color->toForegroundCode()) {
                $underlineColor = null;
            }
            $style = $style->withUnderline()->withUnderlineStyle($underline, $underlineColor ?: null);
        }
        if ($operation['reverse'] ?? false) {
            $style = $style->withReverse();
        }
        if (\in_array('line-through', $decorations, true)) {
            $style = $style->withStrikethrough();
        }
        if (\in_array('overline', $decorations, true)) {
            $style = $style->withOverline();
        }
        $prefix = \in_array('blink', $decorations, true) ? "\x1b[5m" : '';
        $href = $operation['href'] ?? null;
        if (\is_string($href) && !preg_match('/[\x00-\x20\x7f]/', $href) && preg_match('/^(?:https?|mailto|file|ftp|sftp|ssh):/i', $href)) {
            $prefix .= "\x1b]8;;".$href."\x07";
        } else {
            $href = null;
        }
        $suffix = null !== $href ? "\x1b]8;;\x07" : '';
        $scale = $media->textSizing ? TextSize::scale($operation['font-size'] ?? 16) : 1;
        if ($scale > 1 || null !== $sample) {
            $column = $x;
            foreach (grapheme_str_split($text) ?: [] as $grapheme) {
                $columns = AnsiUtils::visibleWidth($grapheme) * $scale;
                if ($columns > 0 && $column >= $left && $column + $columns <= $right && $y >= $top && $y + $scale <= $bottom && (' ' !== $grapheme || array_intersect($decorations, ['underline', 'line-through', 'overline']))) {
                    $glyphStyle = $style;
                    if (null !== $sample) {
                        $paint = $sample(($column + $columns / 2) * $this->metrics->cellWidth, ($y + $scale / 2) * $this->metrics->cellHeight);
                        if (null === $paint) {
                            $column += $columns;
                            continue;
                        }
                        $glyphStyle = $glyphStyle->withColor($paint);
                    }
                    $line = $prefix.$glyphStyle->apply($grapheme).$suffix;
                    if ($scale > 1) {
                        $buffer->writeScaledText($colorDepth?->reduce($line) ?? $line, $scale, $y, $column);
                    } else {
                        $this->writeLines($buffer, [$line], $column, $y, $clip);
                    }
                }
                $column += $columns;
            }
        } else {
            $this->writeLines($buffer, [$prefix.$style->apply($text).$suffix], $x, $y, $clip);
        }
    }

    private function backgroundGroup(CellBuffer $buffer, array $operation, array $transform, array $clip, float $opacity): void
    {
        $layers = $operation['layers'];
        if (\count($layers) > 128) {
            throw new \LengthException('A background group exceeds its drawing operation budget.');
        }
        $resources = [];
        $reserved = new \SplObjectStorage();
        $bytes = $pixels = $cells = 0;
        $mask = null;
        $hasImage = false;
        foreach ($layers as $index => $layer) {
            $mask ??= $layer['text-mask'] ?? null;
            if ('background-image' !== $layer['type']) {
                continue;
            }
            $hasImage = true;
            $pattern = $layer['pattern'];
            $columns = max(1, min(1000, (int) ceil($pattern['x']['size'] / $this->metrics->cellWidth)));
            $rows = max(1, min(1000, (int) ceil($pattern['y']['size'] / $this->metrics->cellHeight)));
            $resources[$index] = [$this->backgroundImages->get($layer['data'], $layer['mime-type'], 'none' !== ($layer['image-orientation'] ?? 'from-image'), \in_array($layer['image-rendering'] ?? 'auto', ['pixelated', 'crisp-edges'], true), $columns, $rows), $columns, $rows];
            $resource = $resources[$index][0];
            if (null !== $resource && !isset($reserved[$resource])) {
                $reserved[$resource] = true;
                [$imageWidth, $imageHeight] = $resource->peekSize();
                $sampled = $columns * $rows;
                $bytes += \strlen($resource->data);
                $pixels += $imageWidth * $imageHeight + ($sampled > 65536 ? 2 * $sampled : 0);
                $cells += $sampled <= 65536 ? $sampled : 0;
                // Reserve before cellAt() decodes or allocates any resampled GD image or PHP cells.
                if ($bytes > 67108864 || $pixels > 16777216 || $cells > 65536) {
                    throw new \LengthException('A background group exceeds its image sampling budget.');
                }
            }
        }
        $image = static function (array $layer, array $point, int $index) use ($resources): array {
            [$image, $columns, $rows] = $resources[$index];
            if (null === $image) {
                return [[0.0, 0.0, 0.0], 0.0];
            }
            $column = min($columns - 1, (int) floor($point[0] / $point[2] * $columns));
            $v = $point[1] / $point[3] * $rows;
            $pixel = $image->cellAt($columns, $rows, $column, min($rows - 1, (int) floor($v)))[($v - floor($v)) < 0.5 ? 0 : 1];

            return null === $pixel ? [[0.0, 0.0, 0.0], 0.0] : [array_map(static fn (int $v): float => $v / 255, $pixel), 1.0];
        };
        $scheme = $this->media->colorScheme->value;
        $color = static fn (string $value) => \Symfony\Component\HtmlRenderer\Css\ColorValue::parse($value, $scheme, static fn (string $name) => TerminalColorPalette::lookupStandardName($name, $scheme));
        $sample = static fn (float $x, float $y, bool $text = false): array => \Symfony\Component\HtmlRenderer\Paint\BackgroundCompositor::sample($layers, $x - $transform[4], $y - $transform[5], $image, $text, $color);
        $x = (int) floor($operation['x'] / $this->metrics->cellWidth);
        $y = (int) floor($operation['y'] / $this->metrics->cellHeight);
        $right = min($clip[2], $x + (int) ceil($operation['width'] / $this->metrics->cellWidth));
        $bottom = min($clip[3], $y + (int) ceil($operation['height'] / $this->metrics->cellHeight));
        $backdrop = Color::hex(ColorScheme::Light === $this->media->colorScheme ? '#ffffff' : '#000000');
        for ($row = max($y, $clip[1]); $row < $bottom; ++$row) {
            for ($column = max($x, $clip[0]); $column < $right; ++$column) {
                $pixels = [];
                [$underTop, $underBottom] = $buffer->getPaintedPixels($row, $column);
                foreach ([0.25, 0.75] as $half => $offset) {
                    [$rgb, $alpha] = $sample(($column + 0.5) * $this->metrics->cellWidth, ($row + ($hasImage ? $offset : 0.5)) * $this->metrics->cellHeight);
                    if ($alpha <= 0) {
                        $pixels[] = null;
                        continue;
                    }
                    $paint = ColorFunction::toColor($rgb);
                    if ($alpha < 1) {
                        $under = 0 === $half ? $underTop : $underBottom;
                        $paint = (null === $under ? $backdrop : Color::rgb(...$under))->mix($paint, (int) round($alpha * 100));
                    }
                    $pixels[] = $paint;
                }
                [$top, $bottomPixel] = $pixels;
                if (null === $top && null === $bottomPixel) {
                    continue;
                }
                if (!$hasImage) {
                    $this->writeLines($buffer, [$top->toBackgroundCode()." \x1b[0m"], $column, $row, $clip);
                } else {
                    if (null === $top && $underTop !== $underBottom && null !== $underTop) {
                        $top = Color::rgb(...$underTop);
                    }
                    if (null === $bottomPixel && $underTop !== $underBottom && null !== $underBottom) {
                        $bottomPixel = Color::rgb(...$underBottom);
                    }
                    $line = null === $top ? $bottomPixel->toForegroundCode().'▄' : $top->toForegroundCode().(null === $bottomPixel ? '' : $bottomPixel->toBackgroundCode()).'▀';
                    $this->writeLines($buffer, [$line."\x1b[0m"], $column, $row, $clip);
                }
            }
        }
        if (null !== $mask) {
            $glyph = function (float $x, float $y) use ($sample, $buffer, $backdrop): ?Color {
                [$rgb, $alpha] = $sample($x, $y, true);
                if ($alpha <= 0) {
                    return null;
                }
                $paint = ColorFunction::toColor($rgb);
                if ($alpha < 1) {
                    $under = $buffer->getBackgroundColor((int) floor($y / $this->metrics->cellHeight), (int) floor($x / $this->metrics->cellWidth)) ?? $backdrop;
                    $paint = $under->mix($paint, (int) round($alpha * 100));
                }

                return $paint;
            };
            $this->textMask($buffer, ['text-mask' => $mask, 'type' => 'background-group'], $transform, $clip, $opacity, $glyph);
        }
    }

    private function textMask(CellBuffer $buffer, array $operation, array $transform, array $clip, float $opacity, ?\Closure $paint = null): void
    {
        $image = null;
        if ('background-image' === $operation['type']) {
            $pattern = $operation['pattern'];
            $columns = max(1, min(1000, (int) ceil($pattern['x']['size'] / $this->metrics->cellWidth)));
            $rows = max(1, min(1000, (int) ceil($pattern['y']['size'] / $this->metrics->cellHeight)));
            $image = $this->backgroundImages->get($operation['data'], $operation['mime-type'], 'none' !== ($operation['image-orientation'] ?? 'from-image'), \in_array($operation['image-rendering'] ?? 'auto', ['pixelated', 'crisp-edges'], true), $columns, $rows);
        }
        $sample = $paint ?? function (float $x, float $y) use ($operation, $image, $buffer): ?Color {
            if ('rectangle' === $operation['type']) {
                $color = Value::color($operation['color'], $this->media);

                return $color instanceof Color ? $color : null;
            }
            if ('gradient' === $operation['type']) {
                $point = [$x - $operation['x'], $y - $operation['y']];
                if (isset($operation['pattern'])) {
                    $point = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::sample($operation['pattern'], ...$point);
                    if (null === $point) {
                        return null;
                    }
                }
                [$rgba, $alpha] = GradientGeometry::sample($operation['gradient'], $point[0], $point[1]);
                if ($alpha <= 0) {
                    return null;
                }
                $color = ColorFunction::toColor($rgba);
                if ($alpha < 1) {
                    $under = $buffer->getBackgroundColor((int) floor($y / $this->metrics->cellHeight), (int) floor($x / $this->metrics->cellWidth)) ?? Color::hex(ColorScheme::Light === $this->media->colorScheme ? '#ffffff' : '#000000');
                    $color = $under->mix($color, (int) round($alpha * 100));
                }

                return $color;
            }
            if (null === $image) {
                return null;
            }
            $pattern = $operation['pattern'];
            $columns = max(1, min(1000, (int) ceil($pattern['x']['size'] / $this->metrics->cellWidth)));
            $rows = max(1, min(1000, (int) ceil($pattern['y']['size'] / $this->metrics->cellHeight)));
            $pixels = [];
            foreach ([-0.25, 0.25] as $half) {
                $point = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::sample($pattern, $x - $operation['x'], $y + $half * $this->metrics->cellHeight - $operation['y']);
                if (null === $point) {
                    continue;
                }
                $column = min($columns - 1, (int) floor($point[0] / $point[2] * $columns));
                $v = $point[1] / $point[3] * $rows;
                $pixel = $image->cellAt($columns, $rows, $column, min($rows - 1, (int) floor($v)))[($v - floor($v)) < 0.5 ? 0 : 1];
                if (null !== $pixel) {
                    $pixels[] = $pixel;
                }
            }
            if (!$pixels) {
                return null;
            }
            $color = $pixels[0];
            if (isset($pixels[1])) {
                $color = [intdiv($color[0] + $pixels[1][0], 2), intdiv($color[1] + $pixels[1][1], 2), intdiv($color[2] + $pixels[1][2], 2)];
            }

            return Color::rgb(...$color);
        };
        foreach ($operation['text-mask'] as $run) {
            $matrix = $run['matrix'];
            $translation = [1.0, 0.0, 0.0, 1.0] === \array_slice($matrix, 0, 4) ? [$matrix[4], $matrix[5]] : [0.0, 0.0];
            $run['x'] += $transform[4] + $translation[0];
            $run['y'] += $transform[5] + $translation[1];
            $inside = $clip;
            foreach ($run['clips'] as $region) {
                $matrix = $region['matrix'];
                $translation = [1.0, 0.0, 0.0, 1.0] === \array_slice($matrix, 0, 4) ? [$matrix[4], $matrix[5]] : [0.0, 0.0];
                $x = (int) floor(($region['x'] + $transform[4] + $translation[0]) / $this->metrics->cellWidth);
                $y = (int) floor(($region['y'] + $transform[5] + $translation[1]) / $this->metrics->cellHeight);
                if ($region['clip-x']) {
                    $inside[0] = max($inside[0], $x);
                    $inside[2] = min($inside[2], $x + (int) ceil($region['width'] / $this->metrics->cellWidth));
                }
                if ($region['clip-y']) {
                    $inside[1] = max($inside[1], $y);
                    $inside[3] = min($inside[3], $y + (int) ceil($region['height'] / $this->metrics->cellHeight));
                }
            }
            $this->text($buffer, $run, $inside, Color::named('default'), $opacity, $sample);
        }
    }

    private function backgroundImage(CellBuffer $buffer, array $operation, array $clip): void
    {
        $pattern = $operation['pattern'];
        $tileWidth = $pattern['x']['size'];
        $tileHeight = $pattern['y']['size'];
        if ($tileWidth <= 0 || $tileHeight <= 0) {
            return;
        }
        $columns = max(1, min(1000, (int) ceil($tileWidth / $this->metrics->cellWidth)));
        $rows = max(1, min(1000, (int) ceil($tileHeight / $this->metrics->cellHeight)));
        $image = $this->backgroundImages->get($operation['data'], $operation['mime-type'], 'none' !== ($operation['image-orientation'] ?? 'from-image'), \in_array($operation['image-rendering'] ?? 'auto', ['pixelated', 'crisp-edges'], true), $columns, $rows);
        if (null === $image) {
            return;
        }
        $x = (int) floor($operation['x'] / $this->metrics->cellWidth);
        $y = (int) floor($operation['y'] / $this->metrics->cellHeight);
        $right = min($clip[2], $x + (int) ceil($operation['width'] / $this->metrics->cellWidth));
        $bottom = min($clip[3], $y + (int) ceil($operation['height'] / $this->metrics->cellHeight));
        for ($row = max($y, $clip[1]); $row < $bottom; ++$row) {
            for ($column = max($x, $clip[0]); $column < $right; ++$column) {
                $pixels = [];
                foreach ([0.25, 0.75] as $half) {
                    $sample = \Symfony\Component\HtmlRenderer\Paint\BackgroundGeometry::sample($pattern, ($column - $x + 0.5) * $this->metrics->cellWidth, ($row - $y + $half) * $this->metrics->cellHeight);
                    if (null === $sample) {
                        $pixels[] = null;
                        continue;
                    }
                    $u = min($columns - 1, (int) floor($sample[0] / $sample[2] * $columns));
                    $v = $sample[1] / $sample[3] * $rows;
                    $pixelRow = min($rows - 1, (int) floor($v));
                    $pixels[] = $image->cellAt($columns, $rows, $u, $pixelRow)[($v - floor($v)) < 0.5 ? 0 : 1];
                }
                [$topPixel, $bottomPixel] = $pixels;
                if (null === $topPixel && null === $bottomPixel) {
                    continue;
                }
                if (null === $topPixel || null === $bottomPixel) {
                    [$underTop, $underBottom] = $buffer->getPaintedPixels($row, $column);
                    if ($underTop !== $underBottom) {
                        $topPixel ??= $underTop;
                        $bottomPixel ??= $underBottom;
                    }
                }
                if (null === $topPixel) {
                    $line = Color::rgb(...$bottomPixel)->toForegroundCode().'▄';
                } else {
                    $line = Color::rgb(...$topPixel)->toForegroundCode().(null === $bottomPixel ? '' : Color::rgb(...$bottomPixel)->toBackgroundCode()).'▀';
                }
                $this->writeLines($buffer, [$line."\x1b[0m"], $column, $row, $clip);
            }
        }
    }

    private function line(CellBuffer $buffer, array $operation, array $transform, array $clip, float $opacity): void
    {
        if ($operation['width'] <= 0 || \in_array($operation['style'], ['none', 'hidden'], true) || $operation['color'][1] <= 0) {
            return;
        }
        $horizontal = $operation['y1'] === $operation['y2'];
        if (!$horizontal && $operation['x1'] !== $operation['x2']) {
            // Diagonal strokes have no terminal cell approximation.
            return;
        }
        $x1 = (int) floor(($operation['x1'] + $transform[4]) / $this->metrics->cellWidth);
        $x2 = (int) floor(($operation['x2'] + $transform[4]) / $this->metrics->cellWidth);
        $y1 = (int) floor(($operation['y1'] + $transform[5]) / $this->metrics->cellHeight);
        $y2 = (int) floor(($operation['y2'] + $transform[5]) / $this->metrics->cellHeight);
        $chars = BorderPattern::fromName(match ($operation['style']) {
            'double' => BorderPattern::DOUBLE, 'dashed' => BorderPattern::DASHED, 'dotted' => BorderPattern::DOTTED, default => BorderPattern::NORMAL,
        })->getChars();
        $junctions = BorderJunctions::fromCorner($chars[0][0]);
        $stroke = $horizontal ? $chars[0][1] : $chars[1][0];
        $start = $operation['start'] ?? ($horizontal ? [false, true, false, true] : [true, false, true, false]);
        $end = $operation['end'] ?? $start;
        $glyph = static function (array $arms) use ($junctions, $stroke): string {
            $key = implode('', array_map(static fn (bool $arm): string => $arm ? '1' : '0', [$arms[0], $arms[2], $arms[3], $arms[1]]));

            return \in_array($key, ['0011', '1100', '1000', '0100', '0010', '0001'], true) ? $stroke : ($junctions[$key] ?? $stroke);
        };
        $first = $horizontal ? $x1 : $y1;
        $last = $horizontal ? $x2 : $y2;
        $borderSpan = [min($y1, $y2), max($y1, $y2) + 1];
        $visibleFirst = max($first, $horizontal ? $clip[0] : $clip[1]);
        $visibleLast = min($last, ($horizontal ? $clip[2] : $clip[3]) - 1);
        for ($at = $visibleFirst; $at <= $visibleLast; ++$at) {
            $column = $horizontal ? $at : $x1;
            $row = $horizontal ? $y1 : $at;
            if ($column < $clip[0] || $column >= $clip[2] || $row < $clip[1] || $row >= $clip[3]) {
                continue;
            }
            $color = $this->media->forcedColors ? Color::named('default') : ColorFunction::toColor($operation['color'][0]);
            if (!$this->media->forcedColors && $operation['color'][1] < 1) {
                $background = $buffer->getBackgroundColor($row, $column) ?? Color::hex(ColorScheme::Light === $this->media->colorScheme ? '#ffffff' : '#000000');
                $color = $background->mix($color, (int) round($operation['color'][1] * 100));
            }
            $style = (new Style())->withColor($color);
            if ($opacity < 1) {
                $style = $style->withDim();
            }
            $buffer->writeAnsiLines([$style->apply($at === $first ? $glyph($start) : ($at === $last ? $glyph($end) : $stroke))], $row, $column, true, null, $borderSpan);
        }
    }

    private function scrollbar(CellBuffer $buffer, array $operation, array $transform, array $clip, float $opacity): void
    {
        $vertical = 'y' === $operation['axis'];
        $thin = $operation['thin'];
        $glyphs = $vertical ? [$thin ? '┊' : '│', $thin ? '│' : '┃'] : [$thin ? '┈' : '─', $thin ? '─' : '━'];
        foreach (['track', 'thumb'] as $index => $part) {
            $rect = $operation[$part];
            $rgba = $this->media->forcedColors ? null : $operation[$part.'-color'];
            if (null !== $rgba && $rgba[1] <= 0) {
                continue;
            }
            $x = (int) floor(($rect['x'] + $transform[4]) / $this->metrics->cellWidth);
            $y = (int) floor(($rect['y'] + $transform[5]) / $this->metrics->cellHeight);
            $endX = (int) ceil(($rect['x'] + $rect['width'] + $transform[4]) / $this->metrics->cellWidth);
            $endY = (int) ceil(($rect['y'] + $rect['height'] + $transform[5]) / $this->metrics->cellHeight);
            for ($row = max($clip[1], $y); $row < min($clip[3], $endY); ++$row) {
                for ($column = max($clip[0], $x); $column < min($clip[2], $endX); ++$column) {
                    $color = null === $rgba ? Color::named('default') : ColorFunction::toColor($rgba[0]);
                    if (null !== $rgba && $rgba[1] < 1) {
                        $background = $buffer->getBackgroundColor($row, $column) ?? Color::hex(ColorScheme::Light === $this->media->colorScheme ? '#ffffff' : '#000000');
                        $color = $background->mix($color, (int) round($rgba[1] * 100));
                    }
                    $style = (new Style())->withColor($color);
                    if ($opacity < 1 || 0 === $index && null === $rgba) {
                        $style = $style->withDim();
                    }
                    $buffer->writeAnsiLines([$style->apply($glyphs[$index])], $row, $column, true);
                }
            }
        }
    }

    private function writeLines(CellBuffer $buffer, array $lines, int $x, int $y, array $clip, bool $border = false): void
    {
        [$left, $top, $right, $bottom] = $clip;
        $borderSpan = $border ? [$y, $y + \count($lines)] : null;
        foreach ($lines as $row => $line) {
            if ($row + $y < $top || $row + $y >= $bottom) {
                continue;
            }
            $start = max(0, $left - $x);
            if ($start > 0) {
                foreach (AnsiUtils::walkCells($line) as $cell) {
                    if ($cell['width'] > 0 && $cell['col'] >= $start) {
                        $start = $cell['col'];
                        break;
                    }
                }
            }
            $line = AnsiUtils::sliceByColumn($line, $start, max(0, $right - $x - $start), true);
            $buffer->writeAnsiLines([$line], $row + $y, $x + $start, true, null, $borderSpan);
        }
    }

    private function compositeFilter(CellBuffer $parent, CellBuffer $layer, Filter $filter, MediaContext $media): void
    {
        // Preserve untouched cells and the background under transparent text. Reuse the existing
        // ANSI color rewriter; the filter matrices themselves belong to HtmlRenderer.
        $spans = [];
        foreach ($layer->getCoveredCells() as $cell) {
            $spans[$cell['row']][] = [$cell['column'], $cell['column'] + $cell['width']];
        }
        $lines = $layer->toLines();
        $reverse = [['invert', 1.0]] === $filter->functions;
        $rewriter = $reverse ? null : new ColorRewriter($filter->apply(...), $media->colorScheme);
        $style = $reverse ? (new Style())->withReverse() : null;
        foreach ($spans as $row => $ranges) {
            sort($ranges);
            $merged = [];
            foreach ($ranges as [$start, $end]) {
                $last = array_key_last($merged);
                if (null !== $last && ($reverse || $start <= $merged[$last][1])) {
                    $merged[$last][1] = max($end, $merged[$last][1]);
                } else {
                    $merged[] = [$start, $end];
                }
            }
            foreach ($merged as [$start, $end]) {
                $line = AnsiUtils::sliceByColumn($lines[$row], $start, $end - $start, true);
                $parent->writeAnsiLines([$reverse ? $style->apply($line) : $rewriter->line($line)], $row, $start, true);
                $parent->copyBorderCells($layer, $row, $start, $end);
            }
        }
        foreach ($lines as $row => $line) {
            foreach (TextMarkers::scaledPayloads($line) as [$column, $payload, $scale]) {
                $text = TextMarkers::unscaledPayload($payload);
                $parent->writeScaledText($reverse ? $style->apply($text) : $rewriter->line($text), $scale, $row, $column);
            }
        }
    }

    private function borderLines(array $operation, int $columns, int $rows, Color $color, MediaContext $media): array
    {
        $columns = max(0, min(10000, $columns));
        $rows = max(0, min(10000, $rows));
        $widths = $colors = $patterns = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $edge = $operation['sides'][$side] ?? $operation;
            $pattern = $edge['style'] ?? 'solid';
            $edgeColor = $media->forcedColors ? Color::named('default') : Value::color($edge['color'] ?? $operation['color'] ?? 'black', $media);
            $widths[] = ($edge['thickness'] ?? 0) > 0 && !\in_array($pattern, ['none', 'hidden'], true) ? 1 : 0;
            $colors[] = $edgeColor ?: null;
            $patterns[] = !$edgeColor ? BorderPattern::NONE : match ($pattern) {
                'dashed' => BorderPattern::DASHED,
                'dotted' => BorderPattern::DOTTED,
                'double' => BorderPattern::DOUBLE,
                'solid' => 'thick' === ($operation['width-keyword'] ?? null) ? BorderPattern::HEAVY : BorderPattern::NORMAL,
                default => BorderPattern::NORMAL,
            };
        }
        $widths[0] = min($rows, $widths[0]);
        $widths[2] = min(max(0, $rows - $widths[0]), $widths[2]);
        $widths[3] = min($columns, $widths[3]);
        $widths[1] = min(max(0, $columns - $widths[3]), $widths[1]);
        $corners = array_map(static fn (array $radius): ?string => $radius[0] > 0 && $radius[1] > 0 ? 'round' : null, $operation['radii'] ?? array_fill(0, 4, [0, 0]));
        $border = new Border($widths[0], $widths[1], $widths[2], $widths[3], BorderPattern::NORMAL, $color, $colors, $patterns, $corners);
        $innerColumns = max(0, $columns - $widths[1] - $widths[3]);
        $innerLines = array_fill(0, max(0, $rows - $widths[0] - $widths[2]), str_repeat(' ', $innerColumns));

        $lines = $border->wrapLines($innerLines, $innerColumns, new Style());
        if ($media->forcedColors || !isset($operation['source']) || $columns <= 0 || $rows <= 0) {
            return $lines;
        }
        $source = $operation['source'];
        $bounds = $operation['source-bounds'] ?? $operation;
        $imageColumns = max(1, (int) ceil($bounds['width'] / $this->metrics->cellWidth));
        $imageRows = max(1, (int) ceil($bounds['height'] / $this->metrics->cellHeight));
        $offsetX = (int) floor(($operation['x'] - $bounds['x']) / $this->metrics->cellWidth);
        $offsetY = (int) floor(($operation['y'] - $bounds['y']) / $this->metrics->cellHeight);
        $sample = static fn (int $x, int $y): ?array => $x < $widths[3] || $x >= $columns - $widths[1] || $y < $widths[0] || $y >= $rows - $widths[2] ? [$x + $offsetX, $y + $offsetY, $imageColumns, $imageRows] : null;
        if ('gradient' === $source['type']) {
            $model = $source['gradient'];
            $backdrop = Color::hex(ColorScheme::Light === $media->colorScheme ? '#ffffff' : '#000000');
            $paint = function (int $x, int $y) use ($model, $backdrop): ?Color {
                [$rgb, $alpha] = GradientGeometry::sample($model, ($x + 0.5) * $this->metrics->cellWidth, ($y + 0.5) * $this->metrics->cellHeight);
                if ($alpha <= 0) {
                    return null;
                }
                $color = ColorFunction::toColor($rgb);

                return $alpha < 1 ? $backdrop->mix($color, (int) round($alpha * 100)) : $color;
            };
        } else {
            $data = $source['data'];
            if ('svg' === $source['type']) {
                $currentColor = Value::color($source['color'] ?? $operation['color'] ?? 'black', $media) ?: $color;
                $rgb = $currentColor->toRgb();
                $data = VectorImage::fromString($data)?->toPng((int) ceil($bounds['width']), (int) ceil($bounds['height']), [$rgb['r'], $rgb['g'], $rgb['b']]);
            }
            if (null === $data) {
                return $lines;
            }
            $paint = new RasterImage($data);
        }

        return GradientPainter::paint($lines, $paint, null, 0, 0, $columns, $rows, $sample, true);
    }
}
