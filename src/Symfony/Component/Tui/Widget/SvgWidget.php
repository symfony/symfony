<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\VectorImage;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Draws the shapes of an inline <svg> with half blocks, like an image.
 *
 * The shapes are drawn again for the color of the widget, which currentColor is.
 *
 * @experimental
 */
final class SvgWidget extends AbstractWidget implements ReplacedElementInterface
{
    /** The pixels drawn per column, the image being then fitted into its cells, which show two by two */
    private const RESOLUTION = 4;

    /** @var array<string, ImageWidget|null> The image drawn for each currentColor */
    private array $images = [];
    /** @var array{?int, ?int, ?string, ?array{float, float, 2?: int, 3?: int}} */
    private array $box = [null, null, null, null];

    /** @var list<array{index: int, text: string, x: float, y: float, size: float, anchor: string, color: array{int, int, int}|null}>|null The texts of the image that can be drawn as characters, see VectorImage::labels() */
    private ?array $labels = null;

    /** The media the shapes were read again in, see $reader */
    private ?string $media = null;

    /**
     * @param string                          $alternative The text shown when it cannot be drawn, like the <title> of the <svg>
     * @param (\Closure(): ?VectorImage)|null $reader      Reads the shapes again, in the media the widget is drawn in, like
     *                                                     another color scheme that the style sheets of the page style them for
     */
    public function __construct(
        private VectorImage $image,
        private readonly string $alternative = '',
        private readonly ?\Closure $reader = null,
    ) {
        if ('' !== $alternative) {
            $this->setLabel($alternative);
        }
    }

    public function setBox(?int $columns, ?int $rows, ?string $fit, ?array $position = null): void
    {
        $this->box = [$columns, $rows, $fit, $position];
    }

    /**
     * The columns it is drawn in, in a room, found without drawing it, like ImageWidget::drawnColumns(): a large room,
     * like the one its content is measured in, would make it long to draw.
     */
    public function drawnColumns(int $room): int
    {
        $room = max(1, $room);
        if (null !== $this->box[0]) {
            return max(1, min($room, $this->box[0]));
        }
        if (null !== $this->box[1]) {
            return max(1, min($room, 1000, (int) round($this->box[1] * 2 * $this->image->width / max(1, $this->image->height))));
        }

        return min($room, max(1, (int) round($this->image->width / 8)));
    }

    public function render(RenderContext $context): array
    {
        if (null !== $this->reader && null !== ($media = $this->getContext()?->getMediaContext()->getKey()) && $media !== $this->media) {
            $this->media = $media;
            $this->image = ($this->reader)() ?? $this->image;
            $this->images = [];
            $this->labels = null;
        }
        $color = $context->getStyle()->getColor()?->toRgb();
        $color = null !== $color ? [$color['r'], $color['g'], $color['b']] : (ColorScheme::Light === $this->getContext()?->getMediaContext()->colorScheme ? [0, 0, 0] : [229, 229, 229]);
        // Its own size: 8 CSS pixels per column, 16 per row; drawn as wide as it is shown, which the box or the room bound
        $columns = max(1, (int) round($this->image->width / 8));
        $rows = max(1, (int) round($this->image->height / 16));
        $shown = max(1, min(1000, $this->box[0] ?? min($columns, max(1, $context->getColumns()))));
        // Its texts as characters where they would be about as large, like the labels of a diagram: half a row to two
        // and a half rows high, in the rows it is drawn in, not in its pixels, which turn letters into specks
        $labels = [];
        if (null === $this->box[0] || null === $this->box[1]) {
            $drawnRows = $this->box[1] ?? max(1, (int) round($shown * $this->image->height / $this->image->width / 2));
            $labels = array_values(array_filter($this->labels ??= $this->image->labels(), static fn (array $label): bool => $label['size'] * $drawnRows >= 0.5 && $label['size'] * $drawnRows <= 2.5));
        }
        // The texts left out of the pixels as part of the key: a box of another height draws others as characters
        $key = implode(',', $color).'@'.$shown.':'.implode(',', array_column($labels, 'index'));
        if (!\array_key_exists($key, $this->images)) {
            if (\count($this->images) >= 8) {
                $this->images = [];
            }
            $width = $shown * self::RESOLUTION;
            // The lines of an image as large as a diagram a pixel of its cells wide at least, else they fade out where a
            // browser draws them thin: not those of an icon, which would turn into a blot
            $diagram = $shown >= 16 && $shown * $this->image->height / $this->image->width / 2 >= 4;
            $png = $this->image->toPng($width, max(1, (int) round($width * $this->image->height / $this->image->width)), $color, array_column($labels, 'index'), $diagram ? 1.2 * self::RESOLUTION : 0.0);
            $this->images[$key] = null !== $png ? new ImageWidget($png, $this->alternative, $columns, $rows) : null;
        }
        if (null === $image = $this->images[$key]) {
            return '' === $this->alternative ? [] : [mb_strimwidth('['.$this->alternative.']', 0, max(1, $context->getColumns()))];
        }
        $image->setBox(...$this->box);
        $lines = $image->render($context);

        // Not over the text alternative of an image that could not be drawn
        return $labels && $image->isDrawable() ? self::label($lines, $labels, $color) : $lines;
    }

    /**
     * Draws texts over the cells of the image, each in its color, over the color of the cells under it where both their
     * halves have one.
     *
     * @param list<string>                                                                                                             $lines
     * @param list<array{index: int, text: string, x: float, y: float, size: float, anchor: string, color: array{int, int, int}|null}> $labels
     * @param array{int, int, int}                                                                                                     $currentColor
     *
     * @return list<string>
     */
    private static function label(array $lines, array $labels, array $currentColor): array
    {
        $rows = \count($lines);
        // The cells of each row, each with the codes before it, as ImageWidget draws them
        $cells = [];
        foreach ($lines as $row => $line) {
            // The codes ending the line, after its last cell, not taken for cells
            preg_match_all('/((?:\e\[[0-9;:]*m)*)([^\e])/su', (string) preg_replace('/(?:\e\[[0-9;:]*m)+$/', '', $line), $matches, \PREG_SET_ORDER);
            $cells[$row] = array_map(static fn (array $match): array => [$match[1], $match[2]], $matches);
        }
        $columns = \count($cells[0] ?? []);
        // The cells a label took: the first one keeps them, a wide character whole
        $taken = [];
        foreach ($labels as $label) {
            $row = (int) floor($label['y'] * $rows);
            // No control character of the document reaches the terminal, a C1 one like U+009B neither, nor a tab or a newline
            $text = str_replace(["\t", "\n"], '', StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($label['text'])));
            $width = AnsiUtils::visibleWidth($text);
            if ($row < 0 || $row >= $rows || 0 === $width) {
                continue;
            }
            $column = (int) round($label['x'] * $columns - match ($label['anchor']) {
                'middle' => $width / 2,
                'end' => $width,
                default => 0,
            });
            [$r, $g, $b] = $label['color'] ?? $currentColor;
            foreach (grapheme_str_split($text) ?: [] as $grapheme) {
                $graphemeWidth = max(1, AnsiUtils::visibleWidth($grapheme));
                if ($column >= 0 && $column + $graphemeWidth <= $columns && !isset($taken[$row][$column]) && !isset($taken[$row][$column + $graphemeWidth - 1])) {
                    // Over the color of the cell when both its halves have one, like the face of a badge
                    $under = preg_match('/^\e\[38;2;(\d+);(\d+);(\d+);48;2;(\d+);(\d+);(\d+)m$/', $cells[$row][$column][0], $m)
                        ? ';48;2;'.intdiv((int) $m[1] + (int) $m[4], 2).';'.intdiv((int) $m[2] + (int) $m[5], 2).';'.intdiv((int) $m[3] + (int) $m[6], 2)
                        : '';
                    $cells[$row][$column] = ["\e[0m\e[38;2;{$r};{$g};{$b}{$under}m", $grapheme];
                    $taken[$row][$column] = true;
                    // A wide character over two cells
                    for ($i = 1; $i < $graphemeWidth; ++$i) {
                        $cells[$row][$column + $i] = ['', ''];
                        $taken[$row][$column + $i] = true;
                    }
                }
                $column += $graphemeWidth;
            }
        }
        foreach ($cells as $row => $rowCells) {
            $lines[$row] = implode('', array_map(static fn (array $cell): string => $cell[0].$cell[1], $rowCells))."\e[0m";
        }

        return $lines;
    }
}
