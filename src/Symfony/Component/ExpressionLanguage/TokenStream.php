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
    /** @var Token */
    public $current;

    /** @var Token[] */
    private $tokens;

    /** @var int */
    private $position = 0;

    /**
     * Constructor.
     *
     * @param Token[] $tokens An array of tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
        $this->rewind();
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
     * Sets the pointer to the next token.
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
     * Tests a token and moves to next one.
     *
     * @param array|int   $type    The type to test
     * @param string|null $value   The token value
     * @param string|null $message The syntax error message
     */
    public function expect($type, $value = null, $message = null)
    {
        $token = $this->current;
        if (!$token->test($type, $value)) {
            throw new SyntaxError(
                sprintf(
                    '%sUnexpected token "%s" of value "%s" ("%s" expected%s)',
                    $message ? $message.'. ' : '',
                    $token->type,
                    $token->value,
                    $type,
                    $value ? sprintf(' with value "%s"', $value) : ''
                ),
                $token->cursor
            );
        }
        $this->next();
    }

    /**
     * Checks if end of stream was reached.
     *
     * @return bool
     */
    public function isEOF()
    {
        return $this->current->type === Token::EOF_TYPE;
    }

    /**
     * Move stream pointer to the beginning.
     */
    public function rewind()
    {
        $this->position = 0;
        $this->current = $this->tokens[0];
    }

    /**
     * Move to a particular position in the stream.
     *
     * @param int $offset The offset relative to $whence.
     * @param int $whence One of SEEK_SET, SEEK_CUR or SEEK_END constants.
     */
    public function seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_CUR:
                $this->position += $offset;
                break;

            case SEEK_END:
                $this->position = count($this->tokens) - 1 + $offset;
                break;

            case SEEK_SET:
                $this->position = $offset;
                break;

            default:
                throw new \InvalidArgumentException('Value of argument $whence is not valid.');
        }

        if (!isset($this->tokens[$this->position])) {
            throw new SyntaxError(
                sprintf('Cannot seek to %s of expression', $this->position > 0 ? 'beyond end' : 'before start'),
                $this->position
            );
        }

        $this->current = $this->tokens[$this->position];
    }

    /**
     * Sets the pointer to the previous token.
     */
    public function prev()
    {
        if (!isset($this->tokens[$this->position])) {
            throw new SyntaxError('Unexpected start of expression', $this->current->cursor);
        }

        --$this->position;

        $this->current = $this->tokens[$this->position];
    }

    /**
     * Tests a token and moves to previous one.
     *
     * @param array|int   $type    The type to test
     * @param string|null $value   The token value
     * @param string|null $message The syntax error message
     */
    public function expectPrev($type, $value = null, $message = null)
    {
        $token = $this->current;
        if (!$token->test($type, $value)) {
            throw new SyntaxError(
                sprintf(
                    '%sUnexpected token "%s" of value "%s" ("%s" expected%s)',
                    $message ? $message.'. ' : '',
                    $token->type,
                    $token->value,
                    $type,
                    $value ? sprintf(' with value "%s"', $value) : ''
                ),
                $token->cursor
            );
        }
        $this->prev();
    }

    /**
     * Returns new TokenStream with tokens replaced by some others.
     *
     * @param int   $offset
     * @param int   $length
     * @param array $replacements
     *
     * @return \static
     */
    public function splice($offset, $length, $replacements)
    {
        $tokens = $this->tokens;
        array_splice($tokens, $offset, $length, $replacements);

        return new static($tokens);
    }

    /**
     * Returns the current position.
     *
     * @return int
     */
    public function position()
    {
        return $this->position;
    }
}
