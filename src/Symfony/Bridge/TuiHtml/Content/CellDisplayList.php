<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Content;

use Symfony\Bridge\TuiHtml\Internal\TextMarkers;
use Symfony\Bridge\TuiHtml\Render\CellBuffer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Component\HtmlRenderer\Paint\DisplayList;

/** @internal */
final readonly class CellDisplayList
{
    public function __construct(private TerminalTextMeasurer $metrics = new TerminalTextMeasurer())
    {
    }

    public function convert(array $lines, int $columns, int $rows, Color $foreground, Color $background): DisplayList
    {
        if ($columns < 1 || $rows < 1) {
            return new DisplayList();
        }
        foreach ($lines as $line) {
            if (preg_match('/\x1b[P^X]|\x1b_(?!pi:[cs])|\x1b\](?!8;)/', $line)) {
                throw new \InvalidArgumentException('Native visual protocols require a portable content representation.');
            }
        }
        $buffer = new CellBuffer($columns, $rows, true, true);
        $buffer->writeAnsiLines($lines);
        $operations = [];
        foreach ($buffer->getCoveredCells() as $cell) {
            $attributes = $cell['attributes'];
            $fg = self::color($cell['foreground'], true) ?? $foreground;
            $bg = self::color($cell['background'], false);
            $geometry = ['x' => $cell['column'] * $this->metrics->cellWidth, 'y' => $cell['row'] * $this->metrics->cellHeight, 'width' => $cell['width'] * $this->metrics->cellWidth, 'height' => $this->metrics->cellHeight];
            if (null !== $bg) {
                $operations[] = ['type' => 'rectangle', ...$geometry, 'color' => $bg->toHex()];
            }
            if ($attributes & CellBuffer::ATTR_HIDDEN) {
                continue;
            }
            $decorations = [];
            foreach ([CellBuffer::ATTR_UNDERLINE => 'underline', CellBuffer::ATTR_DOUBLE_UNDERLINE => 'underline', CellBuffer::ATTR_STRIKETHROUGH => 'line-through', CellBuffer::ATTR_OVERLINE => 'overline', CellBuffer::ATTR_BLINK => 'blink'] as $flag => $name) {
                if ($attributes & $flag) {
                    $decorations[] = $name;
                }
            }
            $text = ['type' => 'text', ...$geometry, 'text' => $cell['text'], 'color' => $fg->toHex(), 'font-size' => 16.0, 'font-weight' => $attributes & CellBuffer::ATTR_BOLD ? 700 : 400, 'font-style' => $attributes & CellBuffer::ATTR_ITALIC ? 'italic' : 'normal', 'text-decoration-line' => implode(' ', array_unique($decorations)) ?: 'none'];
            if ('' !== $cell['link']) {
                $text['href'] = explode(';', $cell['link'], 2)[1];
            }
            $underline = $cell['underline'];
            $text['text-decoration-style'] = match (true) {
                str_contains($underline, '4:2'), (bool) ($attributes & CellBuffer::ATTR_DOUBLE_UNDERLINE) => 'double',
                str_contains($underline, '4:3') => 'wavy',
                str_contains($underline, '4:4') => 'dotted',
                str_contains($underline, '4:5') => 'dashed',
                default => 'solid',
            };
            if (false !== $start = strpos($underline, '58;')) {
                $color = self::color('38;'.substr($underline, $start + 3), true);
                if (null !== $color) {
                    $text['text-decoration-color'] = $color->toHex();
                }
            }
            if ($attributes & CellBuffer::ATTR_REVERSE) {
                $text['reverse'] = true;
            }
            if ($attributes & CellBuffer::ATTR_DIM) {
                $operations[] = ['type' => 'push-opacity', 'opacity' => 0.5];
            }
            $operations[] = $text;
            if ($attributes & CellBuffer::ATTR_DIM) {
                $operations[] = ['type' => 'pop-opacity'];
            }
        }
        if (null !== $cursor = $buffer->getCursorPosition()) {
            $operations[] = ['type' => 'caret', 'x' => $cursor['col'] * $this->metrics->cellWidth, 'y' => $cursor['row'] * $this->metrics->cellHeight, 'width' => $this->metrics->cellWidth, 'height' => $this->metrics->cellHeight, 'color' => $buffer->getCursorAppearance()['color'] ?? $foreground->toHex(), 'shape' => $buffer->getCursorAppearance()['shape'], 'blink' => $buffer->getCursorAppearance()['blink']];
        }

        foreach ($lines as $row => $line) {
            foreach (TextMarkers::scaledPayloads($line) as [$column, $payload, $scale]) {
                $plain = TextMarkers::unscaledPayload($payload);
                $metrics = new TerminalTextMeasurer($this->metrics->cellWidth * $scale, $this->metrics->cellHeight * $scale);
                $display = new self($metrics)->convert([$plain], max(1, (int) ceil(($columns - $column) / $scale)), 1, $foreground, $background);
                foreach ($display as $operation) {
                    $operation['x'] += $column * $this->metrics->cellWidth;
                    $operation['y'] += $row * $this->metrics->cellHeight;
                    if ('text' === $operation['type']) {
                        $operation['font-size'] = 16.0 * $scale;
                    }
                    $operations[] = $operation;
                }
            }
        }

        return new DisplayList($operations);
    }

    private static function color(string $parameters, bool $foreground): ?Color
    {
        $parts = array_map('intval', explode(';', $parameters));
        if (\count($parts) >= 5 && 2 === $parts[1]) {
            return Color::rgb($parts[2], $parts[3], $parts[4]);
        }
        if (\count($parts) >= 3 && 5 === $parts[1]) {
            return Color::palette($parts[2]);
        }

        return $foreground ? Color::fromSgrForeground($parts[0]) : Color::fromSgrBackground($parts[0]);
    }
}
