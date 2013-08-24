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

/**
 * History.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.0.0
 */
class History
{
    protected $stack = array();
    protected $position = -1;

    /**
     * Constructor.
     *
     * @since v2.0.0
     */
    public function __construct()
    {
        $this->clear();
    }

    /**
     * Clears the history.
     *
     * @since v2.0.0
     */
    public function clear()
    {
        $this->stack = array();
        $this->position = -1;
    }

    /**
     * Adds a Request to the history.
     *
     * @param Request $request A Request instance
     *
     * @since v2.0.0
     */
    public function add(Request $request)
    {
        $this->stack = array_slice($this->stack, 0, $this->position + 1);
        $this->stack[] = clone $request;
        $this->position = count($this->stack) - 1;
    }

    /**
     * Returns true if the history is empty.
     *
     * @return Boolean true if the history is empty, false otherwise
     *
     * @since v2.0.0
     */
    public function isEmpty()
    {
        return count($this->stack) == 0;
    }

    /**
     * Goes back in the history.
     *
     * @return Request A Request instance
     *
     * @throws \LogicException if the stack is already on the first page
     *
     * @since v2.0.0
     */
    public function back()
    {
        if ($this->position < 1) {
            throw new \LogicException('You are already on the first page.');
        }

        return clone $this->stack[--$this->position];
    }

    /**
     * Goes forward in the history.
     *
     * @return Request A Request instance
     *
     * @throws \LogicException if the stack is already on the last page
     *
     * @since v2.0.0
     */
    public function forward()
    {
        if ($this->position > count($this->stack) - 2) {
            throw new \LogicException('You are already on the last page.');
        }

        return clone $this->stack[++$this->position];
    }

    /**
     * Returns the current element in the history.
     *
     * @return Request A Request instance
     *
     * @throws \LogicException if the stack is empty
     *
     * @since v2.0.0
     */
    public function current()
    {
        if (-1 == $this->position) {
            throw new \LogicException('The page history is empty.');
        }

        return clone $this->stack[$this->position];
    }
}
