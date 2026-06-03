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

    /**
     * @return $this
     */
    public function setValue(string $value): static
    {
        $value = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));
        // When setting a new value, move cursor to the end of the string
        $newCursor = \strlen($value);
        if ($this->line->getText() !== $value || $this->line->getCursor() !== $newCursor) {
            $this->line->setText($value);
            $this->line->setCursor($newCursor);
            $this->invalidate();
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
            $pastedText = $this->processBracketedPaste($data);
            if (null !== $pastedText) {
                $this->handlePaste($pastedText);
                if ('' === $data) {
                    return;
                }
            } elseif ('' === $data && $this->isBufferingPaste()) {
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
            if ($kb->matches($data, 'delete_to_line_start')) {
                if ('' !== $this->line->deleteToStart()) {
                    $this->notifyChange();
                }

                return;
            }

            if ($kb->matches($data, 'delete_to_line_end')) {
                if ('' !== $this->line->deleteToEnd()) {
                    $this->notifyChange();
                }

                return;
            }

            if ($kb->matches($data, 'delete_word_backward')) {
                if ('' !== $this->line->deleteWordBackward()) {
                    $this->notifyChange();
                }

                return;
            }

            if ($kb->matches($data, 'delete_char_backward')) {
                if ($this->line->deleteCharBackward()) {
                    $this->notifyChange();
                }

                return;
            }

            if ($kb->matches($data, 'delete_char_forward')) {
                if ($this->line->deleteCharForward()) {
                    $this->notifyChange();
                }

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
            if (!StringUtils::hasControlChars($data)) {
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
            return [$prompt];
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
        $textWithCursor = $beforeCursor.$marker.$atCursor.$afterCursor;

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
        // Clean pasted text - remove newlines
        $cleanText = str_replace(["\r\n", "\r", "\n"], '', $text);

        $this->line->insert($cleanText);
        $this->notifyChange();
    }

    private function notifyChange(): void
    {
        $this->invalidate();
        $this->dispatch(new ChangeEvent($this, $this->line->getText()));
    }
}
