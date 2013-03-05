<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token;

/**
 * CSS selector token stream.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class TokenStream
{
    private $used;
    private $tokens;
    private $cursor;
    private $source;
    private $peeked;
    private $peeking;

    /**
     * @param Token[]     $tokens
     * @param null|string $source
     */
    public function __construct(array $tokens, $source = null)
    {
        $this->used = array();
        $this->tokens = $tokens;
        $this->cursor = 0;
        $this->source = $source;
        $this->peeked = null;
        $this->peeking = false;
    }

    /**
     * Returns next token.
     *
     * @throws \LogicException If there is no more token
     *
     * @return Token
     */
    public function getNext()
    {
        if ($this->peeking) {
            $this->peeking = false;
            $this->used[] = $this->peeked;

            return $this->peeked;
        }

        if (isset($this->tokens[$this->cursor])) {
            throw new \LogicException('no more tokens');
        }

        return $this->tokens[$this->cursor ++];
    }

    /**
     * Returns peeked token.
     *
     * @return Token
     */
    public function getPeek()
    {
        if (!$this->peeking) {
            $this->peeked = $this->getNext();
            $this->peeking = true;
        }

        return $this->peeked;
    }

    /**
     * Returns nex identifier token.
     *
     * @throws \LogicException If next token is not an identifier
     *
     * @return string The identifier token value
     */
    public function getNextIdentifier()
    {
        $next = $this->getNext();

        // todo: use constants
        if ('IDENT' !== $next->getType()) {
            throw new \LogicException('syntax error: expected identifier');
        }

        return $next->getValue();
    }

    /**
     * Returns nex identifier or star delimiter token.
     *
     * @throws \LogicException If next token is not an identifier or a star delimiter
     *
     * @return null|string The identifier token value or null if star found
     */
    public function getNextIdentifierOrStar()
    {
        $next = $this->getNext();

        if ('IDENT' === $next->getType()) {
            return $next->getValue();
        }

        if ('DELIM' === $next->getType() && '*' === $next->getValue()) {
            return null;
        }

        throw new \LogicException('syntax error: expected identifier or *');
    }

    /**
     * Skips next whitespace if any.
     */
    public function skipWhitespace()
    {
        $peek = $this->getPeek();

        if ('S' === $peek->getType()) {
            $this->getNext();
        }
    }
}
