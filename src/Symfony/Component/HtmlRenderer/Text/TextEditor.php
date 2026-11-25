<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Text;

use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;

/** @internal */
final class TextEditor
{
    private int $anchor;
    private int $focus;
    private array $undo = [];
    private array $redo = [];
    private int $undoBytes = 0;

    public function __construct(private string $text, private readonly int $maxBytes = 4194304)
    {
        if (\strlen($text) > $maxBytes) {
            throw new \LengthException('The edited value exceeds the configured size limit.');
        }
        $this->anchor = $this->focus = \count(self::graphemes($text));
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getSelection(): array
    {
        return [$this->anchor, $this->focus];
    }

    public function setSelection(int $anchor, int $focus): void
    {
        $count = \count(self::graphemes($this->text));
        $this->anchor = max(0, min($count, $anchor));
        $this->focus = max(0, min($count, $focus));
    }

    public function moveLines(int $lines, bool $extend = false): void
    {
        $characters = self::graphemes($this->text);
        $count = \count($characters);
        $start = $this->focus;
        while ($start > 0 && "\n" !== $characters[$start - 1]) {
            --$start;
        }
        $column = $this->focus - $start;
        while ($lines < 0 && $start > 0) {
            --$start;
            while ($start > 0 && "\n" !== $characters[$start - 1]) {
                --$start;
            }
            ++$lines;
        }
        while ($lines > 0) {
            $end = $start;
            while ($end < $count && "\n" !== $characters[$end]) {
                ++$end;
            }
            if ($end >= $count) {
                break;
            }
            $start = $end + 1;
            --$lines;
        }
        $end = $start;
        while ($end < $count && "\n" !== $characters[$end]) {
            ++$end;
        }
        $this->focus = min($end, $start + $column);
        if (!$extend) {
            $this->anchor = $this->focus;
        }
    }

    public function key(KeyboardEvent $event, bool $multiline = true, bool $readOnly = false, ?int $maxLength = null): bool
    {
        $command = $event->control || $event->meta;
        if ($command && 'a' === strtolower($event->key)) {
            $this->setSelection(0, \count(self::graphemes($this->text)));

            return false;
        }
        if ($command && \in_array(strtolower($event->key), ['z', 'y'], true)) {
            return !$readOnly && $this->history('y' === strtolower($event->key) || $event->shift);
        }
        $characters = self::graphemes($this->text);
        $start = min($this->anchor, $this->focus);
        $end = max($this->anchor, $this->focus);
        $position = $this->focus;
        if (\in_array($event->key, ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'], true)) {
            $lineStart = $this->focus;
            while ($lineStart > 0 && "\n" !== $characters[$lineStart - 1]) {
                --$lineStart;
            }
            $lineEnd = $this->focus;
            while ($lineEnd < \count($characters) && "\n" !== $characters[$lineEnd]) {
                ++$lineEnd;
            }
            $position = match ($event->key) {
                'Home' => $command ? 0 : $lineStart,
                'End' => $command ? \count($characters) : $lineEnd,
                'ArrowLeft' => !$event->shift && $start !== $end ? $start : max(0, $position - 1),
                'ArrowRight' => !$event->shift && $start !== $end ? $end : min(\count($characters), $position + 1),
                'ArrowUp' => $lineStart > 0 ? $this->previousLinePosition($characters, $lineStart, $position - $lineStart) : $position,
                'ArrowDown' => $lineEnd < \count($characters) ? $this->nextLinePosition($characters, $lineEnd + 1, $position - $lineStart) : $position,
            };
            if ($command && \in_array($event->key, ['ArrowLeft', 'ArrowRight'], true)) {
                $position = $this->wordPosition($characters, $this->focus, 'ArrowRight' === $event->key);
            }
            $this->setSelection($event->shift ? $this->anchor : $position, $position);

            return false;
        }
        if ($readOnly) {
            return false;
        }
        $insert = $event->text ?? ('Space' === $event->key ? ' ' : null);
        if ('Enter' === $event->key && $multiline) {
            $insert = "\n";
        }
        if (\in_array($event->key, ['Backspace', 'Delete'], true)) {
            if ($start === $end) {
                if ('Backspace' === $event->key) {
                    $start = $command ? $this->wordPosition($characters, $start, false) : max(0, $start - 1);
                } else {
                    $end = $command ? $this->wordPosition($characters, $end, true) : min(\count($characters), $end + 1);
                }
            }
            $insert = '';
        } elseif (null === $insert || $command || $event->alt) {
            return false;
        }
        $insert = str_replace(["\r\n", "\r"], "\n", $insert);
        if (!$multiline) {
            $insert = str_replace("\n", '', $insert);
        }
        if (null !== $maxLength) {
            if (1 !== preg_match('//u', $insert)) {
                throw new \InvalidArgumentException('Text must be valid UTF-8.');
            }
            $removed = implode('', \array_slice($characters, $start, $end - $start));
            $remaining = max(0, $maxLength - (mb_strlen($this->text) - mb_strlen($removed)));
            $insert = mb_substr($insert, 0, $remaining);
        }
        if (\strlen($insert) > $this->maxBytes) {
            throw new \LengthException('The edited value exceeds the configured size limit.');
        }
        $replacement = self::graphemes($insert);
        array_splice($characters, $start, $end - $start, $replacement);
        $text = implode('', $characters);
        if (\strlen($text) > $this->maxBytes) {
            throw new \LengthException('The edited value exceeds the configured size limit.');
        }
        if ($text === $this->text) {
            return false;
        }
        $caretBytes = 0;
        for ($index = 0, $end = $start + \count($replacement); $index < $end; ++$index) {
            $caretBytes += \strlen($characters[$index]);
        }
        $caret = min(preg_match_all('/\X/u', substr($text, 0, $caretBytes)), preg_match_all('/\X/u', $text));
        $this->undo[] = [$this->text, $this->anchor, $this->focus];
        $this->undoBytes += \strlen($this->text);
        while (\count($this->undo) > 256 || $this->undoBytes > 33554432) {
            $this->undoBytes -= \strlen(array_shift($this->undo)[0]);
        }
        $this->redo = [];
        $this->text = $text;
        $this->anchor = $this->focus = $caret;

        return true;
    }

    private static function graphemes(string $text): array
    {
        if (false === preg_match_all('/\X/u', $text, $matches)) {
            throw new \InvalidArgumentException('The edited value must be valid UTF-8.');
        }

        return $matches[0];
    }

    private function previousLinePosition(array $characters, int $end, int $column): int
    {
        $start = --$end;
        while ($start > 0 && "\n" !== $characters[$start - 1]) {
            --$start;
        }

        return min($end, $start + $column);
    }

    private function nextLinePosition(array $characters, int $start, int $column): int
    {
        $end = $start;
        while ($end < \count($characters) && "\n" !== $characters[$end]) {
            ++$end;
        }

        return min($end, $start + $column);
    }

    private function wordPosition(array $characters, int $position, bool $forward): int
    {
        if ($forward) {
            while ($position < \count($characters) && !preg_match('/^\s/u', $characters[$position])) {
                ++$position;
            }
            while ($position < \count($characters) && preg_match('/^\s/u', $characters[$position])) {
                ++$position;
            }
        } else {
            while ($position > 0 && preg_match('/^\s/u', $characters[$position - 1])) {
                --$position;
            }
            while ($position > 0 && !preg_match('/^\s/u', $characters[$position - 1])) {
                --$position;
            }
        }

        return $position;
    }

    private function history(bool $redo): bool
    {
        $source = $redo ? $this->redo : $this->undo;
        if (!$source) {
            return false;
        }
        $entry = array_pop($source);
        if ($redo) {
            $this->redo = $source;
            $this->undo[] = [$this->text, $this->anchor, $this->focus];
            $this->undoBytes += \strlen($this->text);
        } else {
            $this->undo = $source;
            $this->undoBytes -= \strlen($entry[0]);
            $this->redo[] = [$this->text, $this->anchor, $this->focus];
        }
        [$this->text, $this->anchor, $this->focus] = $entry;

        return true;
    }
}
