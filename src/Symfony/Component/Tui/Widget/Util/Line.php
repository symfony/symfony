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

/**
 * Grapheme-aware single-line text buffer with cursor position.
 *
 * Encapsulates text content and a byte-offset cursor. Every editing method
 * mutates internal state and returns whether the operation had an effect,
 * letting the caller decide whether to invalidate, push undo snapshots, etc.
 *
 * Both InputWidget (single-line) and EditorWidget (multi-line, per-line)
 * delegate grapheme-level operations here.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Line
{
    private string $text;
    private int $cursor;

    public function __construct(string $text = '', int $cursor = 0)
    {
        $this->text = $text;
        $this->cursor = max(0, min($cursor, \strlen($text)));
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getCursor(): int
    {
        return $this->cursor;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
        $this->cursor = min($this->cursor, \strlen($text));
    }

    public function setCursor(int $cursor): void
    {
        $this->cursor = max(0, min($cursor, \strlen($this->text)));
    }

    /**
     * Insert text at the cursor position.
     */
    public function insert(string $insertion): void
    {
        $this->text = substr($this->text, 0, $this->cursor).$insertion.substr($this->text, $this->cursor);
        $this->cursor += \strlen($insertion);
    }

    /**
     * Delete one grapheme backward (backspace).
     */
    public function deleteCharBackward(): bool
    {
        if (0 === $this->cursor) {
            return false;
        }

        $beforeCursor = substr($this->text, 0, $this->cursor);

        if (!$graphemes = grapheme_str_split($beforeCursor)) {
            return false;
        }

        $lastGrapheme = array_pop($graphemes);
        $this->text = implode('', $graphemes).substr($this->text, $this->cursor);
        $this->cursor -= \strlen($lastGrapheme);

        return true;
    }

    /**
     * Delete one grapheme forward (delete key).
     */
    public function deleteCharForward(): bool
    {
        if ($this->cursor >= \strlen($this->text)) {
            return false;
        }

        $afterCursor = substr($this->text, $this->cursor);

        if (!$graphemes = grapheme_str_split($afterCursor)) {
            return false;
        }

        $this->text = substr($this->text, 0, $this->cursor).substr($this->text, $this->cursor + \strlen($graphemes[0]));

        return true;
    }

    /**
     * Move one grapheme to the left.
     */
    public function moveCursorLeft(): bool
    {
        if (0 === $this->cursor) {
            return false;
        }

        $beforeCursor = substr($this->text, 0, $this->cursor);
        $graphemes = grapheme_str_split($beforeCursor);

        if (!$graphemes) {
            return false;
        }

        /** @var string $lastGrapheme */
        $lastGrapheme = array_pop($graphemes);
        $this->cursor -= \strlen($lastGrapheme);

        return true;
    }

    /**
     * Move one grapheme to the right.
     */
    public function moveCursorRight(): bool
    {
        if ($this->cursor >= \strlen($this->text)) {
            return false;
        }

        $afterCursor = substr($this->text, $this->cursor);
        $graphemes = grapheme_str_split($afterCursor);

        if (!$graphemes) {
            return false;
        }

        $this->cursor += \strlen($graphemes[0]);

        return true;
    }

    /**
     * Move cursor to the beginning of the line.
     */
    public function moveCursorToStart(): bool
    {
        if (0 === $this->cursor) {
            return false;
        }

        $this->cursor = 0;

        return true;
    }

    /**
     * Move cursor to the end of the line.
     */
    public function moveCursorToEnd(): bool
    {
        if ($this->cursor === $end = \strlen($this->text)) {
            return false;
        }

        $this->cursor = $end;

        return true;
    }

    /**
     * Move cursor one word backward.
     */
    public function moveWordBackward(): bool
    {
        if (0 === $this->cursor) {
            return false;
        }

        if ($this->cursor === $newCursor = WordNavigator::skipWordBackward($this->text, $this->cursor)) {
            return false;
        }

        $this->cursor = $newCursor;

        return true;
    }

    /**
     * Move cursor one word forward.
     */
    public function moveWordForward(): bool
    {
        if ($this->cursor >= \strlen($this->text)) {
            return false;
        }

        if ($this->cursor === $newCursor = WordNavigator::skipWordForward($this->text, $this->cursor)) {
            return false;
        }

        $this->cursor = $newCursor;

        return true;
    }

    /**
     * Delete one word backward and return the deleted text.
     */
    public function deleteWordBackward(): string
    {
        if (0 === $this->cursor) {
            return '';
        }

        $deleteFrom = WordNavigator::skipWordBackward($this->text, $this->cursor);
        $deletedText = substr($this->text, $deleteFrom, $this->cursor - $deleteFrom);

        $this->text = substr($this->text, 0, $deleteFrom).substr($this->text, $this->cursor);
        $this->cursor = $deleteFrom;

        return $deletedText;
    }

    /**
     * Delete one word forward and return the deleted text.
     */
    public function deleteWordForward(): string
    {
        if ($this->cursor >= \strlen($this->text)) {
            return '';
        }

        $deleteTo = WordNavigator::skipWordForward($this->text, $this->cursor);
        $deletedText = substr($this->text, $this->cursor, $deleteTo - $this->cursor);

        $this->text = substr($this->text, 0, $this->cursor).substr($this->text, $deleteTo);

        return $deletedText;
    }

    /**
     * Delete from cursor to end of line and return the deleted text.
     */
    public function deleteToEnd(): string
    {
        if ('' === $deletedText = substr($this->text, $this->cursor)) {
            return '';
        }

        $this->text = substr($this->text, 0, $this->cursor);

        return $deletedText;
    }

    /**
     * Delete from start of line to cursor and return the deleted text.
     */
    public function deleteToStart(): string
    {
        if ('' === $deletedText = substr($this->text, 0, $this->cursor)) {
            return '';
        }

        $this->text = substr($this->text, $this->cursor);
        $this->cursor = 0;

        return $deletedText;
    }
}
