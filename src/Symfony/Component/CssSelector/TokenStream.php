<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector;

/**
 * TokenStream represents a stream of CSS Selector tokens.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TokenStream
{
    private $used;
    private $tokens;
    private $source;
    private $peeked;
    private $peeking;

    /**
     * Constructor.
     *
     * @param array $tokens The tokens that make the stream.
     * @param mixed $source The source of the stream.
     */
    public function __construct($tokens, $source = null)
    {
        $this->used = array();
        $this->tokens = $tokens;
        $this->source = $source;
        $this->peeked = null;
        $this->peeking = false;
    }

    /**
     * Gets the tokens that have already been visited in this stream.
     *
     * @return array
     */
    public function getUsed()
    {
        return $this->used;
    }

    /**
     * Gets the next token in the stream or null if there is none.
     * Note that if this stream was set to be peeking its behavior
     * will be restored to not peeking after this operation.
     *
     * @return mixed
     */
    public function next()
    {
        if ($this->peeking) {
            $this->peeking = false;
            $this->used[] = $this->peeked;

            return $this->peeked;
        }

        if (!count($this->tokens)) {
            return null;
        }

        $next = array_shift($this->tokens);
        $this->used[] = $next;

        return $next;
    }

    /**
     * Peeks for the next token in this stream. This means that the next token
     * will be returned but it won't be considered as used (visited) until the
     * next() method is invoked.
     * If there are no remaining tokens null will be returned.
     *
     * @see next()
     *
     * @return mixed
     */
    public function peek()
    {
        if (!$this->peeking) {
            if (!count($this->tokens)) {
                return null;
            }

            $this->peeked = array_shift($this->tokens);

            $this->peeking = true;
        }

        return $this->peeked;
    }
}
