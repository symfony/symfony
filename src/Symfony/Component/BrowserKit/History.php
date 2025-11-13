<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\BrowserKit;

use Symfony\Component\BrowserKit\Exception\LogicException;

/**
 * History.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class History
{
    protected array $stack = [];
    protected int $position = -1;

    /**
     * Clears the history.
     */
    public function clear(): void
    {
        $this->stack = [];
        $this->position = -1;
    }

    /**
     * Adds a Request to the history.
     */
    public function add(Request $request): void
    {
        $this->stack = \array_slice($this->stack, 0, $this->position + 1);
        $this->stack[] = clone $request;
        $this->position = \count($this->stack) - 1;
    }

    /**
     * Returns true if the history is empty.
     */
    public function isEmpty(): bool
    {
        return 0 === \count($this->stack);
    }

    /**
     * Returns true if the stack is on the first page.
     */
    public function isFirstPage(): bool
    {
        return $this->position < 1;
    }

    /**
     * Returns true if the stack is on the last page.
     */
    public function isLastPage(): bool
    {
        return $this->position > \count($this->stack) - 2;
    }

    /**
     * Goes back in the history.
     *
     * @throws LogicException if the stack is already on the first page
     */
    public function back(): Request
    {
        if ($this->isFirstPage()) {
            throw new LogicException('You are already on the first page.');
        }

        return clone $this->stack[--$this->position];
    }

    /**
     * Goes forward in the history.
     *
     * @throws LogicException if the stack is already on the last page
     */
    public function forward(): Request
    {
        if ($this->isLastPage()) {
            throw new LogicException('You are already on the last page.');
        }

        return clone $this->stack[++$this->position];
    }

    /**
     * Returns the current element in the history.
     *
     * @throws LogicException if the stack is empty
     */
    public function current(): Request
    {
        if (-1 === $this->position) {
            throw new LogicException('The page history is empty.');
        }

        return clone $this->stack[$this->position];
    }
}
