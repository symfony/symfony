<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

/** @internal */
final class TextMarkers
{
    public const SCALED_PREFIX = '_pi:s;';

    /**
     * The marker of the text of a line drawn larger by the terminal, with the text sizing protocol of kitty (OSC 66):
     * zero-width, it is followed by the spaces that keep its room; the ScreenWriter draws the text there once the
     * lines are written, as the erasing of the rows it covers would take it away.
     */
    public static function scaled(string $line, int $scale): string
    {
        // Its colors and attributes kept, each run of text in a sequence of its own; links are dropped
        $payload = '';
        foreach (preg_split('/(\x1b\[[0-9;:]*m)/', (string) preg_replace('/\x1b\][^\x07\x1b]*(?:\x07|\x1b\\\\)|\x1b_[^\x07]*\x07/', '', $line), -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (str_starts_with($part, "\x1b[")) {
                $payload .= $part;
                continue;
            }
            // Text only, in pieces short enough for one sequence
            foreach (mb_str_split((string) preg_replace('/[\x00-\x1f\x7f]/', '', $part), 32) as $piece) {
                $payload .= "\x1b]66;s={$scale};{$piece}\x07";
            }
        }

        return self::SCALED_PREFIX.base64_encode($payload."\x1b[0m")."\x07";
    }

    public static function cursor(\Symfony\Component\TuiHtml\Style\CursorShape $shape = \Symfony\Component\TuiHtml\Style\CursorShape::Block, ?\Symfony\Component\TuiHtml\Style\Color $color = null): string
    {
        return \Symfony\Component\Tui\Ansi\AnsiUtils::CURSOR_MARKER_PREFIX.$shape->value.(null === $color ? '' : ';'.$color->toHex())."\x07";
    }
}
