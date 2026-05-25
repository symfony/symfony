<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Ansi;

use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/**
 * Renders a ScreenBuffer to HTML with inline CSS styles.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ScreenBufferHtmlRenderer
{
    private Color $defaultForeground;
    private Color $defaultBackground;

    public function __construct(
        ?Color $defaultForeground = null,
        ?Color $defaultBackground = null,
    ) {
        $this->defaultForeground = $defaultForeground ?? Color::hex('#d4d4d4');
        $this->defaultBackground = $defaultBackground ?? Color::hex('#1e1e1e');
    }

    /**
     * Convert a ScreenBuffer to HTML with inline styles.
     */
    public function convert(ScreenBuffer $screen): string
    {
        $cells = $screen->getCells();
        $height = $screen->getHeight();
        $result = '';
        $lastNonEmptyEnd = 0;

        for ($row = 0; $row < $height; ++$row) {
            if ($row > 0) {
                $result .= "\n";
            }
            $result .= $this->convertLine($cells[$row] ?? []);
            if ('' !== rtrim($this->getLineText($cells[$row] ?? []))) {
                $lastNonEmptyEnd = \strlen($result);
            }
        }

        return substr($result, 0, $lastNonEmptyEnd);
    }

    /**
     * Convert a single line of cells to HTML.
     *
     * @param array<int, array{char: string, style: string}> $cells
     */
    private function convertLine(array $cells): string
    {
        if (!$cells) {
            return '';
        }

        $maxCol = max(array_keys($cells));

        $html = '';
        $lastStyle = '';
        $inSpan = false;

        for ($col = 0; $col <= $maxCol; ++$col) {
            $cell = $cells[$col] ?? ['char' => ' ', 'style' => ''];
            $char = $cell['char'];

            // Skip wide character continuation cells (empty string placeholders)
            if ('' === $char) {
                continue;
            }

            $style = $cell['style'];

            if ($style !== $lastStyle) {
                if ($inSpan) {
                    $html .= '</span>';
                    $inSpan = false;
                }
                if ('' !== $style && '' !== $css = $this->ansiToCss($style)) {
                    $html .= '<span style="'.$css.'">';
                    $inSpan = true;
                }
                $lastStyle = $style;
            }

            $html .= htmlspecialchars($char, \ENT_QUOTES | \ENT_HTML5);
        }

        if ($inSpan) {
            $html .= '</span>';
        }

        return $html;
    }

    /**
     * Get plain text from a line of cells.
     *
     * @param array<int, array{char: string, style: string}> $cells
     */
    private function getLineText(array $cells): string
    {
        if (!$cells) {
            return '';
        }

        $line = '';
        $maxCol = max(array_keys($cells));

        for ($col = 0; $col <= $maxCol; ++$col) {
            $char = $cells[$col]['char'] ?? ' ';
            // Skip wide character continuation cells (empty string placeholders)
            if ('' === $char) {
                continue;
            }
            $line .= $char;
        }

        return $line;
    }

    /**
     * Convert ANSI escape sequence to CSS style string.
     */
    private function ansiToCss(string $ansi): string
    {
        // Parse SGR parameters from the style string
        // Style is stored as the full escape sequence, e.g., "\x1b[1;32m"
        if (!preg_match('/\x1b\[([0-9;]*)m/', $ansi, $matches)) {
            return '';
        }

        $params = '' !== $matches[1] ? array_map('intval', explode(';', $matches[1])) : [0];
        $css = [];

        $i = -1;
        $paramCount = \count($params);
        while (++$i < $paramCount) {
            if (0 === $code = $params[$i]) {
                $css = []; // Reset
            } elseif (1 === $code) {
                $css['font-weight'] = 'bold';
            } elseif (2 === $code) {
                $css['opacity'] = '0.7'; // Dim
            } elseif (3 === $code) {
                $css['font-style'] = 'italic';
            } elseif (4 === $code) {
                $css['--underline'] = true;
            } elseif (7 === $code) {
                $css['--reverse'] = true; // Mark for fg/bg swap
            } elseif (27 === $code) {
                unset($css['--reverse']); // Reverse off
            } elseif (9 === $code) {
                $css['--strikethrough'] = true;
            } elseif ($color = Color::fromSgrForeground($code)) {
                $css['color'] = $color->toHex(); // Foreground colors (30-37, 90-97)
            } elseif ($color = Color::fromSgrBackground($code)) {
                $css['background-color'] = $color->toHex(); // Background colors (40-47, 100-107)
            } elseif (38 === $code || 48 === $code || 58 === $code) {
                // 256-color mode (38;5;N / 48;5;N) and RGB truecolor (38;2;R;G;B / 48;2;R;G;B)
                $cssProp = match ($code) {
                    38 => 'color',
                    48 => 'background-color',
                    58 => 'text-decoration-color',
                };
                if (isset($params[$i + 1]) && 5 === $params[$i + 1] && isset($params[$i + 2])) {
                    $css[$cssProp] = Color::palette($params[$i + 2])->toHex();
                    $i += 2;
                } elseif (isset($params[$i + 1]) && 2 === $params[$i + 1] && isset($params[$i + 4])) {
                    $css[$cssProp] = Color::rgb($params[$i + 2], $params[$i + 3], $params[$i + 4])->toHex();
                    $i += 4;
                }
            } elseif (59 === $code) {
                unset($css['text-decoration-color']);
            }
        }

        // Combine text-decoration from underline and strikethrough markers
        $decorations = [];
        if (isset($css['--underline'])) {
            $decorations[] = 'underline';
            unset($css['--underline']);
        }
        if (isset($css['--strikethrough'])) {
            $decorations[] = 'line-through';
            unset($css['--strikethrough']);
        }
        if ($decorations) {
            $css['text-decoration'] = implode(' ', $decorations);
        }

        // Handle reverse video: swap foreground and background colors
        if (isset($css['--reverse'])) {
            unset($css['--reverse']);
            $fg = $css['color'] ?? $this->defaultForeground->toHex();
            $bg = $css['background-color'] ?? $this->defaultBackground->toHex();
            $css['color'] = $bg;
            $css['background-color'] = $fg;
        }

        $cssStr = '';
        foreach ($css as $prop => $value) {
            $cssStr .= $prop.': '.$value.'; ';
        }

        return rtrim($cssStr);
    }
}
