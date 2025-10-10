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
    public Token $current;

    private int $position = 0;

    public function __construct(
        private array $tokens,
        private string $expression = '',
    ) {
        $this->current = $tokens[0];
    }

    /**
     * Returns a string representation of the token stream.
     */
    public function __toString(): string
    {
        return implode("\n", $this->tokens);
    }

    /**
     * Sets the pointer to the next token and returns the old one.
     */
    public function next(): void
    {
        ++$this->position;

        if (!isset($this->tokens[$this->position])) {
            throw new SyntaxError('Unexpected end of expression.', $this->current->cursor, $this->expression);
        }

        $this->current = $this->tokens[$this->position];
    }

    /**
     * @param string|null $message The syntax error message
     */
    public function expect(string $type, ?string $value = null, ?string $message = null): void
    {
        $token = $this->current;
        if (!$token->test($type, $value)) {
            throw new SyntaxError(\sprintf('%sUnexpected token "%s" of value "%s" ("%s" expected%s).', $message ? $message.'. ' : '', $token->type, $token->value, $type, $value ? \sprintf(' with value "%s"', $value) : ''), $token->cursor, $this->expression);
        }
        $this->next();
    }

    /**
     * Checks if end of stream was reached.
     */
    public function isEOF(): bool
    {
        return Token::EOF_TYPE === $this->current->type;
    }

    /**
     * @internal
     */
    public function getExpression(): string
    {
        return $this->expression;
    }
}
