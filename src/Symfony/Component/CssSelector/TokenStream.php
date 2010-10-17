<?php

namespace Symfony\Component\CssSelector;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * TokenStream represents a stream of CSS Selector tokens.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TokenStream
{
    protected $used;
    protected $tokens;
    protected $source;
    protected $peeked;
    protected $peeking;

    public function __construct($tokens, $source = null)
    {
        $this->used = array();
        $this->tokens = $tokens;
        $this->source = $source;
        $this->peeked = null;
        $this->peeking = false;
    }

    public function getUsed()
    {
        return $this->used;
    }

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
