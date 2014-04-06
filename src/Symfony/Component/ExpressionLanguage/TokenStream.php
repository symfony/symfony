<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage;

/**
 * Represents a token stream.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TokenStream
{
    public $current;

    private $tokens;
    private $position = 0;

    /**
     * Constructor.
     *
     * @param array $tokens An array of tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->current = $tokens[0];
    }

    /**
     * Returns a string representation of the token stream.
     *
     * @return string
     */
    public function __toString()
    {
        return implode("\n", $this->tokens);
    }

    /**
     * Sets the pointer to the next token and returns the old one.
     */
    public function next()
    {
        if (!isset($this->tokens[$this->position])) {
            throw new SyntaxError('Unexpected end of expression', $this->current->cursor);
        }

        ++$this->position;

        $this->current = $this->tokens[$this->position];
    }

    /**
     * Tests a token.
     */
    public function expect($type, $value = null, $message = null)
    {
        $token = $this->current;
        if (!$token->test($type, $value)) {
            throw new SyntaxError(sprintf('%sUnexpected token "%s" of value "%s" ("%s" expected%s)', $message ? $message.'. ' : '', $token->type, $token->value, $type, $value ? sprintf(' with value "%s"', $value) : ''), $token->cursor);
        }
        $this->next();
    }

    /**
     * Checks if end of stream was reached
     *
     * @return bool
     */
    public function isEOF()
    {
        return $this->current->type === Token::EOF_TYPE;
    }
}
