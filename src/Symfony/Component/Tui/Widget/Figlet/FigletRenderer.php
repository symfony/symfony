<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Figlet;

use Symfony\Component\Tui\Render\Compositor;
use Symfony\Component\Tui\Style\Color;

/**
 * Renders text using a FIGlet font.
 *
 * Concatenates the ASCII art for each character horizontally, line by line.
 * Strips trailing whitespace from each line and removes blank trailing lines.
 *
 * When a color is provided, each line is wrapped with the foreground ANSI
 * escape code. Since trailing whitespace is already stripped, the colored
 * output is safe for use with the {@see Compositor}
 * in transparent mode: spaces at the end of lines won't carry styling that
 * would prevent lower layers from showing through.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class FigletRenderer
{
    public function __construct(
        private FigletFont $font,
    ) {
    }

    /**
     * Render a string as FIGlet ASCII art.
     *
     * @param string|int|Color|null $color Optional foreground color applied to each line
     *
     * @return string[] One entry per output line
     */
    public function render(string $text, string|int|Color|null $color = null): array
    {
        if ('' === $text) {
            return [];
        }

        $height = $this->font->getHeight();
        $outputLines = array_fill(0, $height, '');

        // Build output by appending each character's art horizontally
        $length = mb_strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $char = mb_substr($text, $i, 1);
            $codepoint = mb_ord($char);
            $charLines = $this->font->getCharacter($codepoint);

            for ($row = 0; $row < $height; ++$row) {
                $outputLines[$row] .= $charLines[$row];
            }
        }

        // Strip trailing whitespace from each line
        $outputLines = array_map('rtrim', $outputLines);

        // Remove blank trailing lines
        while ($outputLines && '' === end($outputLines)) {
            array_pop($outputLines);
        }

        if (null !== $color) {
            $fgCode = Color::from($color)->toForegroundCode();
            $outputLines = array_map(
                static fn (string $line) => '' !== $line ? $fgCode.$line."\x1b[0m" : $line,
                $outputLines,
            );
        }

        return $outputLines;
    }
}
