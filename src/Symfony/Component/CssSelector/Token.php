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
 * Token represents a CSS Selector token.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Token
{
    private $type;
    private $value;
    private $position;

    /**
     * Constructor.
     *
     * @param string  $type     The type of this token.
     * @param mixed   $value    The value of this token.
     * @param integer $position The order of this token.
     */
    public function __construct($type, $value, $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    /**
     * Gets a string representation of this token.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Answers whether this token's type equals to $type.
     *
     * @param  string $type The type to test against this token's one.
     *
     * @return Boolean
     */
    public function isType($type)
    {
        return $this->type == $type;
    }

    /**
     * Gets the position of this token.
     *
     * @return integer
     */
    public function getPosition()
    {
        return $this->position;
    }
}
