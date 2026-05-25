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
 * Emacs-style kill ring for cut/paste operations.
 *
 * Consecutive kill operations are appended to the same entry.
 * Yank/yank-pop cycles through the ring.
 *
 * @experimental
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class KillRing
{
    /** @var string[] */
    private array $entries = [];
    private ?string $lastAction = null;

    /**
     * @var array{start_line: int, start_col: int, end_line: int, end_col: int}|null
     */
    private ?array $lastYank = null;

    public function __construct(
        private int $maxEntries = 50,
    ) {
    }

    /**
     * Add text to the kill ring.
     *
     * If the last action was also a kill, the text is appended/prepended
     * to the most recent entry (consecutive kills accumulate).
     */
    public function add(string $text, bool $prepend): void
    {
        if ('' === $text) {
            return;
        }

        if ('kill' === $this->lastAction && $this->entries) {
            $lastIndex = \count($this->entries) - 1;
            $current = $this->entries[$lastIndex];
            $this->entries[$lastIndex] = $prepend ? $text.$current : $current.$text;
        } else {
            $this->entries[] = $text;
            if (\count($this->entries) > $this->maxEntries) {
                array_shift($this->entries);
            }
        }

        $this->lastAction = 'kill';
        $this->lastYank = null;
    }

    /**
     * Get the most recent kill ring entry for yanking.
     */
    public function peek(): ?string
    {
        if (!$this->entries) {
            return null;
        }

        return $this->entries[\count($this->entries) - 1];
    }

    /**
     * Whether yank-pop is available (last action was yank and ring has > 1 entry).
     */
    public function canYankPop(): bool
    {
        return 'yank' === $this->lastAction && \count($this->entries) > 1 && null !== $this->lastYank;
    }

    /**
     * Rotate the ring for yank-pop and return the new top entry.
     *
     * Moves the last entry to the front and returns the new last entry.
     */
    public function rotate(): ?string
    {
        if (\count($this->entries) <= 1) {
            return null;
        }

        $lastEntry = array_pop($this->entries);
        array_unshift($this->entries, $lastEntry);

        return $this->entries[\count($this->entries) - 1];
    }

    /**
     * Record that a yank happened (for yank-pop tracking).
     *
     * @param array{start_line: int, start_col: int, end_line: int, end_col: int} $range
     */
    public function recordYank(array $range): void
    {
        $this->lastYank = $range;
        $this->lastAction = 'yank';
    }

    /**
     * Get the range of the last yank (for deletion before yank-pop).
     *
     * @return array{start_line: int, start_col: int, end_line: int, end_col: int}|null
     */
    public function getLastYankRange(): ?array
    {
        return $this->lastYank;
    }

    /**
     * Reset the action tracking (called after non-kill/yank operations).
     */
    public function resetAction(): void
    {
        $this->lastAction = null;
    }

    /**
     * Reset both action and yank tracking (called after undo).
     */
    public function resetAll(): void
    {
        $this->lastAction = null;
        $this->lastYank = null;
    }
}
