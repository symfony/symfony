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

use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Widget\Editor\EditorDocument;
use Symfony\Component\Tui\Widget\Editor\EditorRenderer;
use Symfony\Component\Tui\Widget\Editor\EditorViewport;
use Symfony\Component\Tui\Widget\Util\KillRing;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Multi-line text editor.
 *
 * Orchestrates input routing between collaborators:
 * - {@see EditorDocument}: text buffer, cursor, undo/redo, kill ring
 * - {@see EditorViewport}: scroll offset, viewport calculations
 * - {@see EditorRenderer}: line rendering with cursor and word-wrap
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EditorWidget extends AbstractWidget implements FocusableInterface, VerticallyExpandableInterface
{
    use BracketedPasteTrait;
    use FocusableTrait;
    use KeybindingsTrait;

    private EditorDocument $document;
    private EditorViewport $viewport;
    private EditorRenderer $editorRenderer;
    private int $minVisibleLines = 0;
    private ?int $maxVisibleLines = null;
    private bool $verticallyExpanded = false;
    private ?int $lastMaxVisibleLines = null;

    private bool $submitted = false;

    public function __construct(
        ?Keybindings $keybindings = null,
        ?KillRing $killRing = null,
    ) {
        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
        $this->document = new EditorDocument($killRing);
        $this->viewport = new EditorViewport();
        $this->editorRenderer = new EditorRenderer();
    }

    public function getText(): string
    {
        return $this->document->getText();
    }

    /**
     * Check if the editor was submitted (Ctrl+Enter) vs cancelled (Escape).
     */
    public function wasSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * Get paste markers for large pastes.
     *
     * @return array<array{marker: string, content: string}>
     */
    public function getPasteMarkers(): array
    {
        return $this->document->getPasteMarkers();
    }

    /**
     * @return $this
     */
    public function setText(string $text): static
    {
        if ($this->document->setText($text)) {
            $this->viewport->reset();
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setMinVisibleLines(int $minVisibleLines): static
    {
        $minVisibleLines = max(0, $minVisibleLines);
        if ($this->minVisibleLines !== $minVisibleLines) {
            $this->minVisibleLines = $minVisibleLines;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setMaxVisibleLines(?int $maxVisibleLines): static
    {
        if (null !== $maxVisibleLines) {
            $maxVisibleLines = max(1, $maxVisibleLines);
        }

        if ($this->maxVisibleLines !== $maxVisibleLines) {
            $this->maxVisibleLines = $maxVisibleLines;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function expandVertically(bool $fill): static
    {
        if ($this->verticallyExpanded !== $fill) {
            $this->verticallyExpanded = $fill;
            $this->invalidate();
        }

        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return $this->verticallyExpanded;
    }

    public function setFocused(bool $focused): static
    {
        if ($this->focused !== $focused) {
            $this->focused = $focused;
            $this->invalidate();
            $this->getContext()?->requestRender();
        }

        return $this;
    }

    /**
     * @param callable(SubmitEvent): void $callback
     *
     * @return $this
     */
    public function onSubmit(callable $callback): static
    {
        return $this->on(SubmitEvent::class, $callback);
    }

    /**
     * @param callable(CancelEvent): void $callback
     *
     * @return $this
     */
    public function onCancel(callable $callback): static
    {
        return $this->on(CancelEvent::class, $callback);
    }

    /**
     * @param callable(ChangeEvent): void $callback
     *
     * @return $this
     */
    public function onChange(callable $callback): static
    {
        return $this->on(ChangeEvent::class, $callback);
    }

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        // Handle bracketed paste
        if (null !== $pastedText = $this->processBracketedPaste($data)) {
            $this->document->handlePaste($pastedText);
            $this->notifyChange();
            if ('' === $data) {
                return;
            }
        } elseif ($this->isBufferingPaste()) {
            return;
        }

        $kb = $this->getKeybindings();

        // Handle character jump mode (awaiting next character to jump to)
        if (null !== $this->document->getJumpMode()) {
            if ($kb->matches($data, 'jump_forward') || $kb->matches($data, 'jump_backward')) {
                $this->document->setJumpMode(null);

                return;
            }

            if (\ord($data[0]) >= 32 && !str_starts_with($data, "\x1b")) {
                $direction = $this->document->getJumpMode();
                $this->document->setJumpMode(null);
                if ($this->document->jumpToChar($data, $direction)) {
                    $this->invalidate();
                }

                return;
            }

            // Control character - cancel and fall through to normal handling
            $this->document->setJumpMode(null);
        }

        // Copy (leave to parent)
        if ($kb->matches($data, 'copy')) {
            return;
        }

        // Undo/Redo
        if ($kb->matches($data, 'undo')) {
            if ($this->document->undo()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'redo')) {
            if ($this->document->redo()) {
                $this->notifyChange();
            }

            return;
        }

        // Kill ring
        if ($kb->matches($data, 'yank')) {
            if ($this->document->yank()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'yank_pop')) {
            if ($this->document->yankPop()) {
                $this->notifyChange();
            }

            return;
        }

        // New line (check before submit to allow Shift+Enter)
        if ($kb->matches($data, 'new_line')) {
            $this->document->insertNewLine();
            $this->notifyChange();

            return;
        }

        // Submit
        if ($kb->matches($data, 'submit')) {
            $this->submitted = true;
            $this->dispatch(new SubmitEvent($this, $this->getText()));

            return;
        }

        // Navigation
        if ($kb->matches($data, 'cursor_up')) {
            if ($this->document->isOnFirstLine()) {
                $this->document->moveToLineStart();
            } else {
                $this->document->moveCursorUp();
            }
            $this->invalidate();

            return;
        }

        if ($kb->matches($data, 'cursor_down')) {
            if ($this->document->isOnLastLine()) {
                $this->document->moveToLineEnd();
            } else {
                $this->document->moveCursorDown();
            }
            $this->invalidate();

            return;
        }

        if ($kb->matches($data, 'cursor_left')) {
            if ($this->document->moveCursorLeft()) {
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'cursor_right')) {
            if ($this->document->moveCursorRight()) {
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'cursor_line_start')) {
            if ($this->document->moveToLineStart()) {
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'cursor_line_end')) {
            if ($this->document->moveToLineEnd()) {
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'cursor_word_left')) {
            if ($this->document->moveWordBackwards()) {
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'cursor_word_right')) {
            if ($this->document->moveWordForwards()) {
                $this->invalidate();
            }

            return;
        }

        // Page scroll
        if ($kb->matches($data, 'page_up')) {
            $result = $this->viewport->pageScroll($this->document->getLines(), -1, $this->getPageSize(), $this->document->getCursorLine(), $this->document->getCursorCol());
            if (null !== $result) {
                $this->document->setCursorLine($result['cursor_line']);
                $this->document->setCursorCol($result['cursor_col']);
                $this->invalidate();
            }

            return;
        }

        if ($kb->matches($data, 'page_down')) {
            $result = $this->viewport->pageScroll($this->document->getLines(), 1, $this->getPageSize(), $this->document->getCursorLine(), $this->document->getCursorCol());
            if (null !== $result) {
                $this->document->setCursorLine($result['cursor_line']);
                $this->document->setCursorCol($result['cursor_col']);
                $this->invalidate();
            }

            return;
        }

        // Character jump mode triggers
        if ($kb->matches($data, 'jump_forward')) {
            $this->document->setJumpMode('forward');

            return;
        }

        if ($kb->matches($data, 'jump_backward')) {
            $this->document->setJumpMode('backward');

            return;
        }

        // Deletion (line-level, then word-level, then char-level)
        if ($kb->matches($data, 'delete_line')) {
            if ($this->document->deleteLine()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_to_line_end')) {
            if ($this->document->deleteToLineEnd()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_to_line_start')) {
            if ($this->document->deleteToLineStart()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_word_backward')) {
            if ($this->document->deleteWordBackward()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_word_forward')) {
            if ($this->document->deleteWordForward()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_char_backward')) {
            if ($this->document->deleteCharBackward()) {
                $this->notifyChange();
            }

            return;
        }

        if ($kb->matches($data, 'delete_char_forward')) {
            if ($this->document->deleteCharForward()) {
                $this->notifyChange();
            }

            return;
        }

        // Cancel
        if ($kb->matches($data, 'select_cancel')) {
            $this->submitted = false;
            $this->dispatch(new CancelEvent($this));

            return;
        }

        // Shift+Space - insert regular space
        if ($this->getKeybindings()->matches($data, 'insert_space')) {
            $this->document->insertText(' ');
            $this->notifyChange();

            return;
        }

        // Regular character input
        if (!StringUtils::hasControlChars($data)) {
            $data = StringUtils::sanitizeUtf8($data);
            if ('' === $data) {
                return;
            }

            $this->document->insertText($data);
            $this->notifyChange();
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();

        // Calculate max visible lines based on context height or terminal
        $minVisibleLines = $this->minVisibleLines;
        if ($this->verticallyExpanded && $context->getRows() > 0) {
            $maxDisplayRows = max(5, $context->getRows() - 2);
        } else {
            $terminalRows = $this->getContext()?->getTerminalRows() ?? 24;
            $maxDisplayRows = max(5, $minVisibleLines, (int) floor($terminalRows * 0.3));
        }

        if (null !== $this->maxVisibleLines) {
            $maxDisplayRows = min($maxDisplayRows, $this->maxVisibleLines);
        }

        $this->lastMaxVisibleLines = $maxDisplayRows;

        // Compute viewport (adjusts scroll offset, returns visible range)
        $viewport = $this->viewport->computeViewport(
            $this->document->getLines(),
            $this->document->getCursorLine(),
            $maxDisplayRows,
            $columns,
            $this->verticallyExpanded && $context->getRows() > 0,
            $minVisibleLines,
        );

        // Render content
        $cursorStyle = $this->resolveElement('cursor');
        $result = $this->editorRenderer->render(
            $this->document->getLines(),
            $viewport,
            $this->document->getCursorLine(),
            $this->document->getCursorCol(),
            $columns,
            $maxDisplayRows,
            $this->verticallyExpanded && $context->getRows() > 0,
            $this->focused,
            $cursorStyle->getCursorShape() ?? CursorShape::Block,
            $this->resolveElement('frame'),
        );

        return $result;
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            // Cursor movement
            'cursor_up' => [Key::UP],
            'cursor_down' => [Key::DOWN],
            'cursor_left' => [Key::LEFT, 'ctrl+b'],
            'cursor_right' => [Key::RIGHT, 'ctrl+f'],
            'cursor_word_left' => ['alt+left', 'ctrl+left', 'alt+b'],
            'cursor_word_right' => ['alt+right', 'ctrl+right', 'alt+f'],
            'cursor_line_start' => [Key::HOME, 'ctrl+a'],
            'cursor_line_end' => [Key::END, 'ctrl+e'],
            'jump_forward' => ['ctrl+]'],
            'jump_backward' => ['ctrl+alt+]'],
            'page_up' => [Key::PAGE_UP],
            'page_down' => [Key::PAGE_DOWN],

            // Deletion
            'delete_char_backward' => [Key::BACKSPACE, 'shift+backspace'],
            'delete_char_forward' => [Key::DELETE, 'ctrl+d', 'shift+delete'],
            'delete_word_backward' => ['ctrl+w', 'alt+backspace'],
            'delete_word_forward' => ['alt+d', 'alt+delete'],
            'delete_line' => ['ctrl+shift+k'],
            'delete_to_line_start' => ['ctrl+u'],
            'delete_to_line_end' => ['ctrl+k'],

            // Text input
            'insert_space' => ['shift+space'],
            'new_line' => ['shift+enter'],
            'submit' => [Key::ENTER],
            'select_cancel' => [Key::ESCAPE, 'ctrl+c'],

            // Clipboard
            'copy' => ['ctrl+c'],

            // Kill ring
            'yank' => ['ctrl+y'],
            'yank_pop' => ['alt+y'],

            // Undo/Redo
            'undo' => ['ctrl+-'],
            'redo' => ['ctrl+shift+z'],

            // Tool output
            'expand_tools' => ['ctrl+o'],
        ];
    }

    private function notifyChange(): void
    {
        $this->invalidate();
        $this->dispatch(new ChangeEvent($this, $this->getText()));
    }

    private function getPageSize(): int
    {
        if (null !== $this->lastMaxVisibleLines) {
            return $this->lastMaxVisibleLines;
        }

        $terminalRows = $this->getContext()?->getTerminalRows() ?? 24;

        return max(5, (int) floor($terminalRows * 0.3));
    }
}
