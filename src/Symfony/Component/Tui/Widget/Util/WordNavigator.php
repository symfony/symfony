<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Util;

use Symfony\Component\Tui\Ansi\AnsiUtils;

/**
 * Grapheme-aware word navigation for text editing.
 *
 * Provides cursor movement logic: skip whitespace, then skip a punctuation run
 * or a word run. Both InputWidget and EditorWidget delegate to this class for
 * within-line word navigation.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class WordNavigator
{
    /**
     * Returns the new cursor position after moving one word backward within
     * the given text. The cursor is a byte offset.
     *
     * Algorithm: skip trailing whitespace, then skip a punctuation run or a
     * word-character run.
     */
    public static function skipWordBackward(string $text, int $cursor): int
    {
        if (0 === $cursor) {
            return 0;
        }

        if (false === $graphemes = grapheme_str_split(substr($text, 0, $cursor))) {
            return $cursor;
        }

        $newCursor = $cursor;

        // Skip trailing whitespace
        while ($graphemes && AnsiUtils::isWhitespace(end($graphemes))) {
            $newCursor -= \strlen(array_pop($graphemes));
        }

        if ($graphemes) {
            /** @var string $lastGrapheme */
            $lastGrapheme = end($graphemes);
            if (AnsiUtils::isPunctuation($lastGrapheme)) {
                // Skip punctuation run
                while ($graphemes && AnsiUtils::isPunctuation(end($graphemes))) {
                    $newCursor -= \strlen(array_pop($graphemes));
                }
            } else {
                // Skip word run
                while ($graphemes
                    && !AnsiUtils::isWhitespace(end($graphemes))
                    && !AnsiUtils::isPunctuation(end($graphemes))) {
                    $newCursor -= \strlen(array_pop($graphemes));
                }
            }
        }

        return max(0, $newCursor);
    }

    /**
     * Returns the new cursor position after moving one word forward within
     * the given text. The cursor is a byte offset.
     *
     * Algorithm: skip leading whitespace, then skip a punctuation run or a
     * word-character run.
     */
    public static function skipWordForward(string $text, int $cursor): int
    {
        $textLength = \strlen($text);
        if ($cursor >= $textLength) {
            return $cursor;
        }

        if (false === $graphemes = grapheme_str_split(substr($text, $cursor))) {
            return $cursor;
        }

        $newCursor = $cursor;
        $index = 0;
        $count = \count($graphemes);

        // Skip leading whitespace
        while ($index < $count && AnsiUtils::isWhitespace($graphemes[$index])) {
            $newCursor += \strlen($graphemes[$index]);
            ++$index;
        }

        if ($index < $count) {
            if (AnsiUtils::isPunctuation($graphemes[$index])) {
                // Skip punctuation run
                while ($index < $count && AnsiUtils::isPunctuation($graphemes[$index])) {
                    $newCursor += \strlen($graphemes[$index]);
                    ++$index;
                }
            } else {
                // Skip word run
                while ($index < $count) {
                    $segment = $graphemes[$index];
                    if (AnsiUtils::isWhitespace($segment) || AnsiUtils::isPunctuation($segment)) {
                        break;
                    }
                    $newCursor += \strlen($segment);
                    ++$index;
                }
            }
        }

        return $newCursor;
    }
}
