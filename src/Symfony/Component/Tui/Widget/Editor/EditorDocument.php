<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Editor;

use Symfony\Component\Tui\Widget\Util\KillRing;
use Symfony\Component\Tui\Widget\Util\Line;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Multi-line text buffer with cursor, undo/redo, and kill ring.
 *
 * Pure model: no rendering, no terminal I/O, no scroll/viewport logic.
 * The EditorWidget orchestrates input → document → viewport → render.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class EditorDocument
{
    /** @var string[] */
    private array $lines = [''];
    private int $cursorLine = 0;
    private int $cursorCol = 0;

    private KillRing $killRing;

    // Undo/Redo
    /** @var array<int, array{lines: string[], cursor_line: int, cursor_col: int}> */
    private array $undoStack = [];
    /** @var array<int, array{lines: string[], cursor_line: int, cursor_col: int}> */
    private array $redoStack = [];

    // Character jump mode
    private ?string $jumpMode = null; // 'forward' or 'backward'

    // Paste handling
    private int $pasteCount = 0;
    /** @var array<array{marker: string, content: string}> */
    private array $pasteMarkers = [];

    public function __construct(?KillRing $killRing = null)
    {
        $this->killRing = $killRing ?? new KillRing();
    }

    // --- Accessors ---

    /**
     * @return string[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getCursorLine(): int
    {
        return $this->cursorLine;
    }

    public function getCursorCol(): int
    {
        return $this->cursorCol;
    }

    public function setCursorLine(int $line): void
    {
        $this->cursorLine = $line;
    }

    public function setCursorCol(int $col): void
    {
        $this->cursorCol = $col;
    }

    public function getKillRing(): KillRing
    {
        return $this->killRing;
    }

    /**
     * Get paste markers for large pastes.
     *
     * @return array<array{marker: string, content: string}>
     */
    public function getPasteMarkers(): array
    {
        return $this->pasteMarkers;
    }

    public function getText(): string
    {
        $text = implode("\n", $this->lines);

        if (!$this->pasteMarkers) {
            return $text;
        }

        $replacements = [];
        foreach ($this->pasteMarkers as $pasteMarker) {
            $replacements[$pasteMarker['marker']] = $pasteMarker['content'];
        }

        return strtr($text, $replacements);
    }

    // --- Character Jump Mode ---

    public function getJumpMode(): ?string
    {
        return $this->jumpMode;
    }

    public function setJumpMode(?string $mode): void
    {
        $this->jumpMode = $mode;
    }

    // --- Text Mutation ---

    public function setText(string $text): bool
    {
        $text = StringUtils::sanitizeUtf8($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = StringUtils::stripControlBytes($text);
        $lines = '' === $text ? [''] : explode("\n", $text);
        $needsReset = $lines !== $this->lines || 0 !== $this->cursorLine || 0 !== $this->cursorCol;

        $this->lines = $lines;
        $this->cursorLine = 0;
        $this->cursorCol = 0;
        $this->undoStack = [];
        $this->redoStack = [];
        $this->pasteMarkers = [];
        $this->pasteCount = 0;

        return $needsReset;
    }

    public function insertText(string $text): void
    {
        $text = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($text));
        if ('' === $text) {
            return;
        }

        $this->pushUndoSnapshot();
        $this->killRing->resetAction();

        $this->insertTextAtCursor($text);
    }

    public function insertNewLine(): void
    {
        $this->pushUndoSnapshot();
        $this->killRing->resetAction();

        $currentLine = $this->lines[$this->cursorLine];
        $beforeCursor = substr($currentLine, 0, $this->cursorCol);
        $afterCursor = substr($currentLine, $this->cursorCol);

        $this->lines[$this->cursorLine] = $beforeCursor;
        array_splice($this->lines, $this->cursorLine + 1, 0, [$afterCursor]);

        ++$this->cursorLine;
        $this->cursorCol = 0;
    }

    public function deleteCharBackward(): bool
    {
        if (0 === $this->cursorCol && 0 === $this->cursorLine) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->killRing->resetAction();

        if ($this->cursorCol > 0) {
            $line = $this->currentLine();
            $line->deleteCharBackward();
            $this->applyLine($line);
        } elseif ($this->cursorLine > 0) {
            // Merge with previous line
            $currentLine = $this->lines[$this->cursorLine];
            $prevLine = $this->lines[$this->cursorLine - 1];

            $this->cursorCol = \strlen($prevLine);
            $this->lines[$this->cursorLine - 1] = $prevLine.$currentLine;

            array_splice($this->lines, $this->cursorLine, 1);
            --$this->cursorLine;
        }

        return true;
    }

    public function deleteCharForward(): bool
    {
        $currentLine = $this->lines[$this->cursorLine];

        if ($this->cursorCol >= \strlen($currentLine) && $this->cursorLine >= \count($this->lines) - 1) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->killRing->resetAction();

        if ($this->cursorCol < \strlen($currentLine)) {
            $line = $this->currentLine();
            $line->deleteCharForward();
            $this->applyLine($line);
        } elseif ($this->cursorLine < \count($this->lines) - 1) {
            // Merge with next line
            $nextLine = $this->lines[$this->cursorLine + 1];
            $this->lines[$this->cursorLine] = $currentLine.$nextLine;
            array_splice($this->lines, $this->cursorLine + 1, 1);
        }

        return true;
    }

    public function deleteLine(): bool
    {
        if (1 === \count($this->lines) && '' === $this->lines[0]) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->killRing->resetAction();

        if (\count($this->lines) > 1) {
            array_splice($this->lines, $this->cursorLine, 1);
            if ($this->cursorLine >= \count($this->lines)) {
                --$this->cursorLine;
            }
        } else {
            $this->lines = [''];
        }

        $this->cursorCol = min($this->cursorCol, \strlen($this->lines[$this->cursorLine]));

        return true;
    }

    public function deleteToLineEnd(): bool
    {
        $line = $this->currentLine();
        if ('' === $deletedText = $line->deleteToEnd()) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->killRing->add($deletedText, false);
        $this->applyLine($line);

        return true;
    }

    public function deleteToLineStart(): bool
    {
        $line = $this->currentLine();
        if ('' === $deletedText = $line->deleteToStart()) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->killRing->add($deletedText, true);
        $this->applyLine($line);

        return true;
    }

    public function deleteWordBackward(): bool
    {
        if (0 === $this->cursorCol) {
            return $this->deleteCharBackward();
        }

        $this->pushUndoSnapshot();

        $line = $this->currentLine();
        $deletedText = $line->deleteWordBackward();
        $this->killRing->add($deletedText, true);
        $this->applyLine($line);

        return true;
    }

    public function deleteWordForward(): bool
    {
        $currentLine = $this->lines[$this->cursorLine];

        if ($this->cursorCol >= \strlen($currentLine)) {
            return $this->deleteCharForward();
        }

        $this->pushUndoSnapshot();

        $line = $this->currentLine();
        $deletedText = $line->deleteWordForward();
        $this->killRing->add($deletedText, false);
        $this->applyLine($line);

        return true;
    }

    // --- Cursor Navigation ---

    public function isOnFirstLine(): bool
    {
        return 0 === $this->cursorLine;
    }

    public function isOnLastLine(): bool
    {
        return $this->cursorLine >= \count($this->lines) - 1;
    }

    public function moveToLineStart(): bool
    {
        if (0 !== $this->cursorCol) {
            $this->cursorCol = 0;

            return true;
        }

        return false;
    }

    public function moveToLineEnd(): bool
    {
        if ($this->cursorCol !== $lineLength = \strlen($this->lines[$this->cursorLine])) {
            $this->cursorCol = $lineLength;

            return true;
        }

        return false;
    }

    public function moveCursorUp(): bool
    {
        if ($this->cursorLine > 0) {
            --$this->cursorLine;
            $this->cursorCol = min($this->cursorCol, \strlen($this->lines[$this->cursorLine]));

            return true;
        }

        return false;
    }

    public function moveCursorDown(): bool
    {
        if ($this->cursorLine < \count($this->lines) - 1) {
            ++$this->cursorLine;
            $this->cursorCol = min($this->cursorCol, \strlen($this->lines[$this->cursorLine]));

            return true;
        }

        return false;
    }

    public function moveCursorLeft(): bool
    {
        $line = $this->currentLine();
        if ($line->moveCursorLeft()) {
            $this->cursorCol = $line->getCursor();

            return true;
        }

        if ($this->cursorLine > 0) {
            --$this->cursorLine;
            $this->cursorCol = \strlen($this->lines[$this->cursorLine]);

            return true;
        }

        return false;
    }

    public function moveCursorRight(): bool
    {
        $line = $this->currentLine();
        if ($line->moveCursorRight()) {
            $this->cursorCol = $line->getCursor();

            return true;
        }

        if ($this->cursorLine < \count($this->lines) - 1) {
            ++$this->cursorLine;
            $this->cursorCol = 0;

            return true;
        }

        return false;
    }

    public function moveWordBackwards(): bool
    {
        $this->killRing->resetAction();
        $oldLine = $this->cursorLine;
        $oldCol = $this->cursorCol;

        if (0 === $this->cursorCol) {
            if ($this->cursorLine > 0) {
                --$this->cursorLine;
                $this->cursorCol = \strlen($this->lines[$this->cursorLine]);
            }

            return $this->cursorLine !== $oldLine || $this->cursorCol !== $oldCol;
        }

        $line = $this->currentLine();
        $line->moveWordBackward();
        $this->cursorCol = $line->getCursor();

        return $this->cursorLine !== $oldLine || $this->cursorCol !== $oldCol;
    }

    public function moveWordForwards(): bool
    {
        $this->killRing->resetAction();
        $oldLine = $this->cursorLine;
        $oldCol = $this->cursorCol;

        if ($this->cursorCol >= \strlen($this->lines[$this->cursorLine])) {
            if ($this->cursorLine < \count($this->lines) - 1) {
                ++$this->cursorLine;
                $this->cursorCol = 0;
            }

            return $this->cursorLine !== $oldLine || $this->cursorCol !== $oldCol;
        }

        $line = $this->currentLine();
        $line->moveWordForward();
        $this->cursorCol = $line->getCursor();

        return $this->cursorLine !== $oldLine || $this->cursorCol !== $oldCol;
    }

    /**
     * Jump to the first occurrence of a character in the specified direction.
     * Multi-line search. Case-sensitive. Skips the current cursor position.
     */
    public function jumpToChar(string $char, string $direction): bool
    {
        $this->killRing->resetAction();
        $isForward = 'forward' === $direction;
        $lineCount = \count($this->lines);

        $end = $isForward ? $lineCount : -1;
        $step = $isForward ? 1 : -1;

        for ($lineIdx = $this->cursorLine; $lineIdx !== $end; $lineIdx += $step) {
            $line = $this->lines[$lineIdx];
            $isCurrentLine = $lineIdx === $this->cursorLine;

            if ($isCurrentLine) {
                if ($isForward) {
                    $nextByteOffset = $this->cursorCol;
                    grapheme_extract($line, 1, \GRAPHEME_EXTR_COUNT, $nextByteOffset, $nextByteOffset);
                    $idx = strpos($line, $char, $nextByteOffset);
                } else {
                    $searchIn = 0 === $this->cursorCol ? false : substr($line, 0, $this->cursorCol);
                    $idx = false !== $searchIn ? strrpos($searchIn, $char) : false;
                }
            } else {
                $idx = $isForward ? strpos($line, $char) : strrpos($line, $char);
            }

            if (false !== $idx) {
                $this->cursorLine = $lineIdx;
                $this->cursorCol = $idx;

                return true;
            }
        }

        return false;
    }

    // --- Undo/Redo ---

    public function undo(): bool
    {
        if (!$this->undoStack) {
            return false;
        }

        $this->redoStack[] = $this->createSnapshot();

        $snapshot = array_pop($this->undoStack);
        $this->restoreSnapshot($snapshot);
        $this->killRing->resetAll();

        return true;
    }

    public function redo(): bool
    {
        if (!$this->redoStack) {
            return false;
        }

        $this->undoStack[] = $this->createSnapshot();

        $snapshot = array_pop($this->redoStack);
        $this->restoreSnapshot($snapshot);
        $this->killRing->resetAll();

        return true;
    }

    // --- Kill Ring ---

    public function yank(): bool
    {
        if (null === $text = $this->killRing->peek()) {
            return false;
        }

        $this->pushUndoSnapshot();

        $startLine = $this->cursorLine;
        $startCol = $this->cursorCol;

        $this->insertTextAtCursor($text);

        $this->killRing->recordYank([
            'start_line' => $startLine,
            'start_col' => $startCol,
            'end_line' => $this->cursorLine,
            'end_col' => $this->cursorCol,
        ]);

        return true;
    }

    public function yankPop(): bool
    {
        if (!$this->killRing->canYankPop()) {
            return false;
        }

        $this->pushUndoSnapshot();
        $this->deleteYankedText();

        $text = $this->killRing->rotate();
        if (null === $text) {
            return false;
        }

        $startLine = $this->cursorLine;
        $startCol = $this->cursorCol;

        $this->insertTextAtCursor($text);

        $this->killRing->recordYank([
            'start_line' => $startLine,
            'start_col' => $startCol,
            'end_line' => $this->cursorLine,
            'end_col' => $this->cursorCol,
        ]);

        return true;
    }

    // --- Paste Handling ---

    /**
     * Handle pasted content.
     *
     * Large pastes (>10 lines) create a marker for efficient display.
     */
    public function handlePaste(string $content): void
    {
        $content = str_replace(["\r\n", "\r"], "\n", StringUtils::sanitizeUtf8($content));
        $content = StringUtils::stripControlBytes($content);

        if ('' === $content) {
            return;
        }

        $lines = explode("\n", $content);

        // For large pastes, create a marker
        if (\count($lines) > 10) {
            ++$this->pasteCount;
            $id = bin2hex(random_bytes(8));
            $marker = \sprintf('[paste #%d +%d lines <%s>]', $this->pasteCount, \count($lines), $id);
            $this->pasteMarkers[] = ['marker' => $marker, 'content' => $content];
            $this->insertText($marker);

            return;
        }

        // Insert first line at cursor
        $this->insertText($lines[0]);

        // Insert remaining lines
        for ($i = 1; $i < \count($lines); ++$i) {
            $this->insertNewLine();
            $this->insertText($lines[$i]);
        }
    }

    // --- Internal ---

    private function insertTextAtCursor(string $text): void
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);

        if (1 === \count($lines)) {
            $currentLine = $this->lines[$this->cursorLine];
            $this->lines[$this->cursorLine] = substr($currentLine, 0, $this->cursorCol)
                .$text
                .substr($currentLine, $this->cursorCol);
            $this->cursorCol += \strlen($text);

            return;
        }

        $currentLine = $this->lines[$this->cursorLine];
        $before = substr($currentLine, 0, $this->cursorCol);
        $after = substr($currentLine, $this->cursorCol);

        $this->lines[$this->cursorLine] = $before.$lines[0];

        for ($i = 1; $i < \count($lines) - 1; ++$i) {
            array_splice($this->lines, $this->cursorLine + $i, 0, [$lines[$i]]);
        }

        $lastLineIndex = $this->cursorLine + \count($lines) - 1;
        array_splice($this->lines, $lastLineIndex, 0, [($lines[\count($lines) - 1] ?? '').$after]);

        $this->cursorLine = $lastLineIndex;
        $this->cursorCol = \strlen($lines[\count($lines) - 1] ?? '');
    }

    private function currentLine(): Line
    {
        return new Line($this->lines[$this->cursorLine], $this->cursorCol);
    }

    private function applyLine(Line $line): void
    {
        $this->lines[$this->cursorLine] = $line->getText();
        $this->cursorCol = $line->getCursor();
    }

    private function pushUndoSnapshot(): void
    {
        $this->undoStack[] = $this->createSnapshot();

        if (\count($this->undoStack) > 100) {
            array_shift($this->undoStack);
        }

        $this->redoStack = [];
    }

    /**
     * @return array{lines: string[], cursor_line: int, cursor_col: int}
     */
    private function createSnapshot(): array
    {
        return [
            'lines' => $this->lines,
            'cursor_line' => $this->cursorLine,
            'cursor_col' => $this->cursorCol,
        ];
    }

    /**
     * @param array{lines: string[], cursor_line: int, cursor_col: int} $snapshot
     */
    private function restoreSnapshot(array $snapshot): void
    {
        $this->lines = $snapshot['lines'];
        $this->cursorLine = $snapshot['cursor_line'];
        $this->cursorCol = $snapshot['cursor_col'];
    }

    private function deleteYankedText(): void
    {
        if (null === $range = $this->killRing->getLastYankRange()) {
            return;
        }

        $startLine = $range['start_line'];
        $startCol = $range['start_col'];
        $endLine = $range['end_line'];
        $endCol = $range['end_col'];

        if ($startLine === $endLine) {
            $line = $this->lines[$startLine];
            $this->lines[$startLine] = substr($line, 0, $startCol)
                .substr($line, $endCol);
        } else {
            $startText = substr($this->lines[$startLine], 0, $startCol);
            $endText = substr($this->lines[$endLine], $endCol);
            $this->lines[$startLine] = $startText.$endText;

            $removeCount = $endLine - $startLine;
            array_splice($this->lines, $startLine + 1, $removeCount);
        }

        $this->cursorLine = $startLine;
        $this->cursorCol = $startCol;
    }
}
