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
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Widget\Util\Line;
use Symfony\Component\Tui\Widget\Util\StringUtils;

/**
 * Single-line text input with horizontal scrolling.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class InputWidget extends AbstractWidget implements FocusableInterface
{
    use BracketedPasteTrait;
    use FocusableTrait;
    use KeybindingsTrait;

    private Line $line;
    private string $prompt = '> ';
    private bool $submitted = false;
    private string $placeholder = '';
    private bool $readOnly = false;
    private bool $masked = false;
    private ?int $maxLength = null;
    /** @var list<string> */
    private array $suggestions = [];

    public function __construct(
        ?Keybindings $keybindings = null,
    ) {
        if (null !== $keybindings) {
            $this->setKeybindings($keybindings);
        }
        $this->line = new Line();
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

    public function getValue(): string
    {
        return $this->line->getText();
    }

    /**
     * Check if the input was submitted (Enter pressed) vs cancelled (Escape pressed).
     */
    public function wasSubmitted(): bool
    {
        return $this->submitted;
    }

    /**
     * Sets the prompt text rendered before the input field.
     *
     * The prompt is stored verbatim and rendered as-is. See {@see TextWidget}
     * for the raw-passthrough contract: sanitize untrusted input upstream via
     * {@see StringUtils::stripControlBytes()}.
     *
     * @return $this
     */
    public function setPrompt(string $prompt): static
    {
        if ($this->prompt !== $prompt) {
            $this->prompt = $prompt;
            $this->invalidate();
        }

        return $this;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    /**
     * Sets the hint drawn while the value is empty, styled by the "placeholder" element.
     *
     * @return $this
     */
    public function setPlaceholder(string $placeholder): static
    {
        $placeholder = self::singleLine($placeholder);
        if ($this->placeholder !== $placeholder) {
            $this->placeholder = $placeholder;
            $this->invalidate();
        }

        return $this;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    /**
     * A read-only input keeps the focus and its cursor, and ignores edits and pastes.
     *
     * @return $this
     */
    public function setReadOnly(bool $readOnly = true): static
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Limits the characters (grapheme clusters) typing, pasting or taking a suggestion can bring the value to; null
     * for no limit.
     *
     * A value given to setValue() is not limited.
     *
     * @return $this
     */
    public function setMaxLength(?int $maxLength): static
    {
        $this->maxLength = null !== $maxLength ? max(0, $maxLength) : null;

        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * A masked input draws a bullet for each character of its value, like a password prompt.
     *
     * @return $this
     */
    public function setMasked(bool $masked = true): static
    {
        if ($this->masked !== $masked) {
            $this->masked = $masked;
            $this->invalidate();
        }

        return $this;
    }

    public function isMasked(): bool
    {
        return $this->masked;
    }

    /**
     * Sets the values the input suggests: the first one that extends the value, ignoring case, is drawn after the
     * cursor, styled by the "suggestion" element; Tab, or Right at the end of the value, replaces the value with it.
     *
     * @param list<string> $suggestions
     *
     * @return $this
     */
    public function setSuggestions(array $suggestions): static
    {
        $suggestions = array_values(array_filter(array_map(self::singleLine(...), $suggestions), static fn (string $suggestion): bool => '' !== $suggestion));
        if ($this->suggestions !== $suggestions) {
            $this->suggestions = $suggestions;
            $this->invalidate();
        }

        return $this;
    }

    /**
     * Returns what the first suggestion extending the value adds to it, or null when none does or the input is masked.
     */
    public function getCompletion(): ?string
    {
        return null !== ($suggestion = $this->getSuggestion()) ? mb_substr($suggestion, mb_strlen($this->line->getText())) : null;
    }

    /**
     * @param bool $dispatch Whether a change dispatches a ChangeEvent, like an edit by the user
     *
     * @return $this
     */
    public function setValue(string $value, bool $dispatch = false): static
    {
        $value = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));
        // When setting a new value, move cursor to the end of the string
        $newCursor = \strlen($value);
        if ($this->line->getText() !== $value || $this->line->getCursor() !== $newCursor) {
            $changed = $this->line->getText() !== $value;
            $this->line->setText($value);
            $this->line->setCursor($newCursor);
            $this->invalidate();
            if ($dispatch && $changed) {
                $this->dispatch(new ChangeEvent($this, $value));
            }
        }

        return $this;
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

    public function handleInput(string $data): void
    {
        if (null !== $this->onInput && ($this->onInput)($data)) {
            return;
        }

        $beforeValue = $this->line->getText();
        $beforeCursor = $this->line->getCursor();

        try {
            // Handle bracketed paste mode
            if (null !== ($pastedText = $this->processBracketedPaste($data)) && !$this->readOnly) {
                $this->handlePaste($pastedText);
            }

            // Nothing left to route once the paste bytes are peeled off; an
            // empty chunk is not a keystroke and not text to insert.
            if ('' === $data) {
                return;
            }

            $kb = $this->getKeybindings();

            // Cancel
            if ($kb->matches($data, 'select_cancel')) {
                $this->submitted = false;
                $this->dispatch(new CancelEvent($this));

                return;
            }

            // Submit
            if ($kb->matches($data, 'submit') || "\n" === $data) {
                $this->submitted = true;
                $this->dispatch(new SubmitEvent($this, $this->line->getText()));

                return;
            }

            // Deletion (line-level, then word-level, then char-level)
            if (!$this->readOnly && $kb->matches($data, 'delete_to_line_start')) {
                if ('' !== $this->line->deleteToStart()) {
                    $this->notifyChange();
                }

                return;
            }

            if (!$this->readOnly && $kb->matches($data, 'delete_to_line_end')) {
                if ('' !== $this->line->deleteToEnd()) {
                    $this->notifyChange();
                }

                return;
            }

            if (!$this->readOnly && $kb->matches($data, 'delete_word_backward')) {
                if ('' !== $this->line->deleteWordBackward()) {
                    $this->notifyChange();
                }

                return;
            }

            if (!$this->readOnly && $kb->matches($data, 'delete_char_backward')) {
                if ($this->line->deleteCharBackward()) {
                    $this->notifyChange();
                }

                return;
            }

            if (!$this->readOnly && $kb->matches($data, 'delete_char_forward')) {
                if ($this->line->deleteCharForward()) {
                    $this->notifyChange();
                }

                return;
            }

            // Take the suggestion: Tab, or Right at the end of the value
            if (!$this->readOnly && ("\t" === $data || $kb->matches($data, 'cursor_right')) && $this->line->getCursor() === \strlen($this->line->getText()) && null !== $suggestion = $this->getSuggestion()) {
                // As written in the suggestion, cut to the maximum length like typing
                if (null !== $this->maxLength) {
                    $suggestion = (string) grapheme_substr($suggestion, 0, max($this->maxLength, (int) grapheme_strlen($this->line->getText())));
                }
                $this->setValue($suggestion, true);

                return;
            }

            // Cursor movement
            if ($kb->matches($data, 'cursor_left')) {
                $this->line->moveCursorLeft();

                return;
            }

            if ($kb->matches($data, 'cursor_right')) {
                $this->line->moveCursorRight();

                return;
            }

            if ($kb->matches($data, 'cursor_line_start')) {
                $this->line->moveCursorToStart();

                return;
            }

            if ($kb->matches($data, 'cursor_line_end')) {
                $this->line->moveCursorToEnd();

                return;
            }

            if ($kb->matches($data, 'cursor_word_left')) {
                $this->line->moveWordBackward();

                return;
            }

            if ($kb->matches($data, 'cursor_word_right')) {
                $this->line->moveWordForward();

                return;
            }

            // Regular character input
            $data = $kb->getParser()->decodePrintable($data) ?? $data;
            if (!$this->readOnly && !StringUtils::hasControlChars($data) && '' !== $data = $this->fit($data)) {
                $this->line->insert($data);
                $this->notifyChange();
            }
        } finally {
            if ($this->line->getText() === $beforeValue && $this->line->getCursor() !== $beforeCursor) {
                $this->invalidate();
            }
        }
    }

    /**
     * @return string[]
     */
    public function render(RenderContext $context): array
    {
        $columns = $context->getColumns();
        $prompt = $this->prompt;
        $availableColumns = $columns - AnsiUtils::visibleWidth($prompt);

        if ($availableColumns <= 0) {
            return [AnsiUtils::truncateToWidth($prompt, $columns, '', true)];
        }

        $value = $this->line->getText();
        $cursor = $this->line->getCursor();

        // Split into graphemes for width-aware scrolling
        $graphemes = grapheme_str_split($value) ?: [];

        // Find cursor grapheme index from byte offset
        $cursorGraphemeIndex = \count($graphemes);
        $bytePos = 0;
        foreach ($graphemes as $i => $g) {
            if ($cursor < $bytePos + \strlen($g)) {
                $cursorGraphemeIndex = $i;
                break;
            }
            $bytePos += \strlen($g);
        }

        // One bullet per character, the cursor where it was
        if ($this->masked) {
            $graphemes = array_fill(0, \count($graphemes), '•');
            $value = implode('', $graphemes);
        }

        $totalWidth = AnsiUtils::visibleWidth($value);

        if ($totalWidth < $availableColumns) {
            $visibleGraphemes = $graphemes;
            $cursorVisibleIndex = $cursorGraphemeIndex;
        } else {
            // Horizontal scrolling in grapheme/display-width space
            $atEnd = $cursorGraphemeIndex === \count($graphemes);
            $scrollColumns = $atEnd ? $availableColumns - 1 : $availableColumns;
            $halfColumns = (int) floor($scrollColumns / 2);

            // Measure display width of graphemes before cursor
            $widthBeforeCursor = AnsiUtils::visibleWidth(implode('', \array_slice($graphemes, 0, $cursorGraphemeIndex)));

            if ($widthBeforeCursor < $halfColumns) {
                // Cursor near start, take graphemes from the beginning that fit
                $visibleGraphemes = self::takeGraphemesByWidth($graphemes, 0, $scrollColumns);
                $cursorVisibleIndex = $cursorGraphemeIndex;
            } elseif ($widthBeforeCursor > $totalWidth - $halfColumns) {
                // Cursor near end, take graphemes from the end that fit
                $visibleGraphemes = self::takeGraphemesFromEndByWidth($graphemes, $scrollColumns);
                $startIndex = \count($graphemes) - \count($visibleGraphemes);
                $cursorVisibleIndex = $cursorGraphemeIndex - $startIndex;
            } else {
                // Cursor in middle, center around cursor
                [$visibleGraphemes, $startIndex] = self::takeGraphemesCenteredByWidth($graphemes, $cursorGraphemeIndex, $scrollColumns);
                $cursorVisibleIndex = $cursorGraphemeIndex - $startIndex;
            }
        }

        // Build before/at/after cursor from visible graphemes
        $beforeCursor = implode('', \array_slice($visibleGraphemes, 0, $cursorVisibleIndex));
        $atCursor = $visibleGraphemes[$cursorVisibleIndex] ?? ' ';
        $afterCursor = implode('', \array_slice($visibleGraphemes, $cursorVisibleIndex + 1));

        $cursorStyle = $this->resolveElement('cursor');
        $marker = $this->focused ? AnsiUtils::cursorMarker($cursorStyle->getCursorShape() ?? CursorShape::Block) : '';
        if ('' === $value && '' !== $this->placeholder) {
            // The cursor on the first character of the hint
            $textWithCursor = $marker.$this->applyElement('placeholder', AnsiUtils::truncateToWidth($this->placeholder, $availableColumns, ''));
        } elseif ($cursor === \strlen($this->line->getText()) && $cursorVisibleIndex === \count($visibleGraphemes) && null !== $completion = $this->getCompletion()) {
            // The cursor on the first character of the suggestion, drawn as far as there is room
            $room = max(0, $availableColumns - AnsiUtils::visibleWidth($beforeCursor));
            $textWithCursor = $beforeCursor.$marker.$this->applyElement('suggestion', AnsiUtils::truncateToWidth($completion, $room, ''));
        } else {
            $textWithCursor = $beforeCursor.$marker.$atCursor.$afterCursor;
        }

        // Pad to width
        $visualLength = AnsiUtils::visibleWidth($textWithCursor);
        $padding = str_repeat(' ', max(0, $availableColumns - $visualLength));

        $line = $prompt.$textWithCursor.$padding;

        return [$line];
    }

    /**
     * @return array<string, string[]>
     */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'cursor_left' => [Key::LEFT, 'ctrl+b'],
            'cursor_right' => [Key::RIGHT, 'ctrl+f'],
            'cursor_word_left' => ['alt+left', 'ctrl+left', 'alt+b'],
            'cursor_word_right' => ['alt+right', 'ctrl+right', 'alt+f'],
            'cursor_line_start' => [Key::HOME, 'ctrl+a'],
            'cursor_line_end' => [Key::END, 'ctrl+e'],
            'delete_char_backward' => [Key::BACKSPACE, 'shift+backspace'],
            'delete_char_forward' => [Key::DELETE, 'ctrl+d', 'shift+delete'],
            'delete_word_backward' => ['ctrl+w', 'alt+backspace'],
            'delete_to_line_start' => ['ctrl+u'],
            'delete_to_line_end' => ['ctrl+k'],
            'submit' => [Key::ENTER],
            'select_cancel' => [Key::ESCAPE, 'ctrl+c'],
        ];
    }

    /**
     * Take graphemes from $startIndex forward that fit within $maxWidth display columns.
     *
     * @param string[] $graphemes
     *
     * @return string[]
     */
    private static function takeGraphemesByWidth(array $graphemes, int $startIndex, int $maxWidth): array
    {
        $result = [];
        $width = 0;
        for ($i = $startIndex; $i < \count($graphemes); ++$i) {
            $gw = AnsiUtils::visibleWidth($graphemes[$i]);
            if ($width + $gw > $maxWidth) {
                break;
            }
            $result[] = $graphemes[$i];
            $width += $gw;
        }

        return $result;
    }

    /**
     * Take graphemes from the end that fit within $maxWidth display columns.
     *
     * @param string[] $graphemes
     *
     * @return string[]
     */
    private static function takeGraphemesFromEndByWidth(array $graphemes, int $maxWidth): array
    {
        $result = [];
        $width = 0;
        for ($i = \count($graphemes) - 1; $i >= 0; --$i) {
            $gw = AnsiUtils::visibleWidth($graphemes[$i]);
            if ($width + $gw > $maxWidth) {
                break;
            }
            array_unshift($result, $graphemes[$i]);
            $width += $gw;
        }

        return $result;
    }

    /**
     * Take graphemes centered around $centerIndex that fit within $maxWidth display columns.
     *
     * @param string[] $graphemes
     *
     * @return array{string[], int} The visible graphemes and the start index in the original array
     */
    private static function takeGraphemesCenteredByWidth(array $graphemes, int $centerIndex, int $maxWidth): array
    {
        $halfWidth = (int) floor($maxWidth / 2);

        // Expand left from center until we reach halfWidth
        $startIndex = $centerIndex;
        $leftWidth = 0;
        while ($startIndex > 0) {
            $gw = AnsiUtils::visibleWidth($graphemes[$startIndex - 1]);
            if ($leftWidth + $gw > $halfWidth) {
                break;
            }
            --$startIndex;
            $leftWidth += $gw;
        }

        // Take graphemes from startIndex that fit within maxWidth
        return [self::takeGraphemesByWidth($graphemes, $startIndex, $maxWidth), $startIndex];
    }

    private function handlePaste(string $text): void
    {
        // Pasted bytes are untrusted: a paste can carry escape sequences that
        // would be replayed verbatim to the terminal on the next render.
        $cleanText = StringUtils::decodeCtrlLetterKeys(StringUtils::sanitizeUtf8($text));
        // Clean pasted text - remove newlines
        $cleanText = str_replace(["\r\n", "\r", "\n"], '', $cleanText);
        $cleanText = $this->fit(StringUtils::stripControlBytes($cleanText));

        if ('' === $cleanText) {
            return;
        }

        $this->line->insert($cleanText);
        $this->notifyChange();
    }

    /**
     * Cuts what an edit inserts to the room the maximum length leaves.
     */
    /**
     * The first suggestion that extends the value, ignoring case.
     */
    private function getSuggestion(): ?string
    {
        $value = $this->line->getText();
        if ('' === $value || $this->masked) {
            return null;
        }
        foreach ($this->suggestions as $suggestion) {
            if (mb_strlen($suggestion) > mb_strlen($value) && 0 === mb_stripos($suggestion, $value)) {
                return $suggestion;
            }
        }

        return null;
    }

    /**
     * Cuts what an edit inserts to the room the maximum length leaves, in characters (grapheme clusters).
     */
    private function fit(string $text): string
    {
        if (null === $this->maxLength) {
            return $text;
        }
        $room = $this->maxLength - (int) grapheme_strlen($this->line->getText());

        return $room > 0 ? (string) grapheme_substr($text, 0, $room) : '';
    }

    private static function singleLine(string $text): string
    {
        return StringUtils::stripControlBytes(StringUtils::sanitizeUtf8(str_replace(["\r\n", "\r", "\n"], ' ', $text)));
    }

    private function notifyChange(): void
    {
        $this->invalidate();
        $this->dispatch(new ChangeEvent($this, $this->line->getText()));
    }
}
