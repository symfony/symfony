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
 * Represents a Token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Token
{
    public $value;
    public $type;
    public $cursor;

    const EOF_TYPE         = 'end of expression';
    const NAME_TYPE        = 'name';
    const NUMBER_TYPE      = 'number';
    const STRING_TYPE      = 'string';
    const OPERATOR_TYPE    = 'operator';
    const PUNCTUATION_TYPE = 'punctuation';

    /**
     * Constructor.
     *
     * @param int     $type   The type of the token
     * @param string  $value  The token value
     * @param int     $cursor The cursor position in the source
     */
    public function __construct($type, $value, $cursor)
    {
        $this->type = $type;
        $this->value = $value;
        $this->cursor = $cursor;
    }

    /**
     * Returns a string representation of the token.
     *
     * @return string A string representation of the token
     */
    public function __toString()
    {
        return sprintf('%3d %-11s %s', $this->cursor, strtoupper($this->type), $this->value);
    }

    /**
     * Tests the current token for a type and/or a value.
     *
     * @param array|int     $type  The type to test
     * @param string|null   $value The token value
     *
     * @return bool
     */
    public function test($type, $value = null)
    {
        return $this->type === $type && (null === $value || $this->value == $value);
    }
}
